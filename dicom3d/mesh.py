"""Turn a scalar :class:`~dicom3d.loader.Volume` into a surface mesh and export it
to many widely-supported 3D file formats."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import numpy as np
import trimesh
from skimage import measure

from .loader import Volume

# Preset iso-values (in Hounsfield Units) for CT tissue boundaries. These are
# rough but sensible starting points; callers can override with a raw number.
HU_PRESETS = {
    "bone": 300.0,
    "skin": -300.0,
    "soft-tissue": 40.0,
    "lung": -600.0,
}

# Formats we can write. Every one of these opens in a broad range of tools and
# is self-contained (single file, no sidecars):
#   stl -> 3D printing, CAD, every mesh viewer
#   obj -> universal interchange (Blender, Maya, Cinema4D, web)
#   ply -> MeshLab, CloudCompare, research tooling
#   glb -> glTF binary: Windows 3D Viewer, Blender, web/AR, Google/Apple
# (Plain-text .gltf is intentionally excluded: trimesh emits it with external
#  .bin buffers, which is fragile. GLB serves the same ecosystem in one file.)
SUPPORTED_FORMATS = ("stl", "obj", "ply", "glb")


@dataclass
class Mesh:
    vertices: np.ndarray  # (N, 3) float, in millimetres
    faces: np.ndarray  # (M, 3) int
    level: float
    metadata: dict

    @property
    def trimesh(self) -> trimesh.Trimesh:
        return trimesh.Trimesh(
            vertices=self.vertices, faces=self.faces, process=False
        )


def _is_hu_modality(volume: Volume) -> bool:
    """CT is calibrated in Hounsfield Units; MR/US/etc. are not.

    Only for HU-calibrated data do the numeric tissue presets (bone = 300 HU,
    ...) mean anything. MR intensities are arbitrary and sequence-dependent, so
    a fixed constant threshold produces a wrong or empty surface there.
    """
    return volume.metadata.get("modality", "") in ("CT", "CTPROTOCOL")


def _auto_level(volume: Volume) -> float:
    """Pick a sensible iso-value automatically for the given modality.

    * CT  -> the bone preset (300 HU), the usual first thing people want.
    * MR/other -> an Otsu threshold separating the imaged anatomy (bright) from
      background air (dark), i.e. the outer skin/scalp surface. This is the
      right default for MR, where there is no Hounsfield scale to key off and
      the naive (min+max)/2 midpoint is easily thrown off by a few bright
      voxels (fat, flow, hyperintensities).
    """
    if _is_hu_modality(volume):
        return HU_PRESETS["bone"]

    finite = volume.data[np.isfinite(volume.data)].astype(np.float32)
    lo, hi = float(finite.min()), float(finite.max())
    if hi <= lo:
        raise ValueError("Volume has no intensity variation; nothing to extract.")
    try:
        from skimage.filters import threshold_otsu

        level = float(threshold_otsu(finite))
    except Exception:
        # Fallback: a low percentile still separates foreground from air.
        level = float(np.percentile(finite, 60))
    # Keep the threshold off the extreme ends so marching cubes has a surface.
    margin = 0.02 * (hi - lo)
    return min(max(level, lo + margin), hi - margin)


def _resolve_level(volume: Volume, iso: str | float | None) -> float:
    if iso is None:
        return _auto_level(volume)
    if isinstance(iso, str):
        key = iso.strip().lower()
        if key in ("", "auto"):
            return _auto_level(volume)
        if key in HU_PRESETS:
            # HU presets only make sense for HU-calibrated data. For MR/other,
            # fall back to the automatic foreground threshold rather than
            # applying a meaningless constant that yields a wrong surface.
            if _is_hu_modality(volume):
                return HU_PRESETS[key]
            return _auto_level(volume)
        try:
            return float(key)
        except ValueError as exc:
            raise ValueError(
                f"Unknown iso level {iso!r}. Use a number, 'auto', or one of: "
                f"{', '.join(HU_PRESETS)}."
            ) from exc
    return float(iso)


def generate_mesh(
    volume: Volume,
    iso: str | float | None = None,
    step_size: int = 1,
    smooth: bool = True,
    largest_only: bool = False,
) -> Mesh:
    """Extract an iso-surface from ``volume`` using marching cubes.

    Parameters
    ----------
    iso:
        Iso-value: a raw scalar, ``"auto"``/``None``, or a named CT preset
        (``bone``, ``skin``, ``soft-tissue``, ``lung``). Default (auto) is the
        bone preset for CT, or an Otsu foreground threshold for MR/other
        modalities, which have no Hounsfield scale for the presets to key off.
    step_size:
        Marching-cubes step. ``>1`` yields a coarser, lighter mesh (faster,
        smaller files); ``1`` is full resolution.
    smooth:
        Apply Laplacian smoothing to reduce the voxel "staircase".
    largest_only:
        Keep only the largest connected component (drops table, noise specks).
    """
    level = _resolve_level(volume, iso)
    data = volume.data

    if not (data.min() < level < data.max()):
        raise ValueError(
            f"Iso level {level:g} is outside the volume intensity range "
            f"[{data.min():g}, {data.max():g}]; nothing to extract."
        )

    # spacing is (z, y, x) matching the volume axes, so vertices come out in mm.
    verts, faces, normals, _ = measure.marching_cubes(
        data, level=level, spacing=volume.spacing, step_size=max(1, int(step_size))
    )

    mesh = trimesh.Trimesh(vertices=verts, faces=faces, vertex_normals=normals)

    if largest_only:
        parts = mesh.split(only_watertight=False)
        if len(parts) > 1:
            mesh = max(parts, key=lambda m: m.area)

    if smooth:
        try:
            trimesh.smoothing.filter_taubin(mesh, iterations=10)
        except Exception:
            pass  # smoothing is best-effort; never fail the export for it

    meta = dict(volume.metadata)
    meta.update(
        {
            "iso_level": level,
            "iso_units": "HU" if _is_hu_modality(volume) else "raw intensity",
            "surface": "bone (HU preset)" if _is_hu_modality(volume)
            else "foreground / outer surface (auto)",
            "n_vertices": int(len(mesh.vertices)),
            "n_faces": int(len(mesh.faces)),
            "watertight": bool(mesh.is_watertight),
        }
    )
    return Mesh(
        vertices=np.asarray(mesh.vertices),
        faces=np.asarray(mesh.faces),
        level=level,
        metadata=meta,
    )


def export_mesh(
    mesh: Mesh,
    out_dir: str | Path,
    basename: str = "model",
    formats: tuple[str, ...] = SUPPORTED_FORMATS,
) -> list[Path]:
    """Write ``mesh`` to ``out_dir`` in each requested format. Returns the paths."""
    out_dir = Path(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    unknown = set(f.lower() for f in formats) - set(SUPPORTED_FORMATS)
    if unknown:
        raise ValueError(
            f"Unsupported format(s): {', '.join(sorted(unknown))}. "
            f"Choose from: {', '.join(SUPPORTED_FORMATS)}."
        )

    tm = mesh.trimesh
    _apply_bone_material(tm)
    written: list[Path] = []
    for fmt in formats:
        fmt = fmt.lower()
        path = out_dir / f"{basename}.{fmt}"
        if fmt == "obj":
            # OBJ carries colour only via a separate .mtl file. Suppress it so
            # every output stays a single self-contained file; GLB and PLY
            # already embed the colour inline.
            tm.export(path, include_texture=False, write_texture=False)
        else:
            tm.export(path)  # trimesh dispatches on the file extension
        written.append(path)
    return written


def _apply_bone_material(tm: trimesh.Trimesh) -> None:
    """Give the mesh a warm bone-coloured PBR material.

    This makes the GLB render pleasantly in glTF viewers (Windows 3D Viewer,
    model-viewer, Quick Look) instead of a blown-out default white, and carries
    a matching colour into PLY/OBJ. STL is geometry-only and ignores it.
    """
    bone = [222, 205, 184, 255]  # RGBA, 0-255
    try:
        from trimesh.visual.material import PBRMaterial

        tm.visual = trimesh.visual.TextureVisuals(
            material=PBRMaterial(
                name="bone",
                baseColorFactor=bone,
                metallicFactor=0.0,
                roughnessFactor=0.75,
            )
        )
    except Exception:
        # Fall back to per-vertex colours (still exports to PLY/GLB).
        tm.visual.vertex_colors = bone
