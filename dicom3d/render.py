"""Optional extras: a PNG preview of the mesh and a NIfTI volume export.

Both are best-effort conveniences layered on top of the mesh pipeline:

* ``preview_png`` renders a shaded thumbnail with matplotlib (headless-safe) so
  users can eyeball the result without opening a 3D program.
* ``export_nifti`` writes the raw volume as ``.nii.gz`` — the native format of
  medical viewers such as 3D Slicer and ITK-SNAP.
"""

from __future__ import annotations

from pathlib import Path

import numpy as np

from .loader import Volume
from .mesh import Mesh


def preview_png(mesh: Mesh, out_path: str | Path, size: int = 900) -> Path:
    """Render a shaded preview of ``mesh`` to a PNG using matplotlib (no display)."""
    import matplotlib

    matplotlib.use("Agg")  # headless backend; must precede pyplot import
    import matplotlib.pyplot as plt
    from mpl_toolkits.mplot3d.art3d import Poly3DCollection

    out_path = Path(out_path)
    verts, faces = mesh.vertices, mesh.faces

    # A dense mesh makes matplotlib crawl. Decimate by keeping the largest
    # (nearest-camera) triangles so the silhouette stays coherent rather than
    # randomly punching holes that ruin the shading.
    max_faces = 90_000
    if len(faces) > max_faces:
        tri = verts[faces]
        area = np.linalg.norm(
            np.cross(tri[:, 1] - tri[:, 0], tri[:, 2] - tri[:, 0]), axis=1
        )
        faces = faces[np.argsort(area)[-max_faces:]]

    bg = "#101216"
    fig = plt.figure(figsize=(size / 100, size / 100), dpi=100, facecolor=bg)
    ax = fig.add_subplot(111, projection="3d", facecolor=bg)
    for pane in (ax.xaxis, ax.yaxis, ax.zaxis):
        pane.pane.set_visible(False)

    tris = verts[faces]
    coll = Poly3DCollection(tris, alpha=1.0, linewidths=0)

    # Simple lambertian shading from a fixed light so features read clearly.
    v0, v1, v2 = tris[:, 0], tris[:, 1], tris[:, 2]
    normals = np.cross(v1 - v0, v2 - v0)
    lengths = np.linalg.norm(normals, axis=1, keepdims=True)
    normals = np.divide(normals, lengths, out=np.zeros_like(normals), where=lengths > 0)
    light = np.array([0.3, 0.4, 0.85])
    light = light / np.linalg.norm(light)
    shade = np.clip(normals @ light, 0.15, 1.0)
    base = np.array([0.86, 0.80, 0.72])  # warm bone-ish tone
    coll.set_facecolor(shade[:, None] * base[None, :])
    coll.set_edgecolor("none")
    ax.add_collection3d(coll)

    mins, maxs = verts.min(axis=0), verts.max(axis=0)
    ax.set_xlim(mins[0], maxs[0])
    ax.set_ylim(mins[1], maxs[1])
    ax.set_zlim(mins[2], maxs[2])
    ax.set_box_aspect(maxs - mins)
    ax.set_axis_off()
    ax.view_init(elev=12, azim=-70)

    fig.subplots_adjust(left=0, right=1, bottom=0, top=1)
    fig.savefig(out_path, facecolor=bg)
    plt.close(fig)
    return out_path


def export_nifti(volume: Volume, out_path: str | Path) -> Path:
    """Write ``volume`` as a NIfTI ``.nii.gz`` file for medical image viewers."""
    import nibabel as nib

    out_path = Path(out_path)
    # NIfTI expects [x, y, z]; our volume is [z, y, x]. Transpose to match, and
    # build an affine that encodes the real voxel spacing (mm).
    data = np.transpose(volume.data, (2, 1, 0)).astype(np.float32)
    z, y, x = volume.spacing
    affine = np.diag([x, y, z, 1.0])
    img = nib.Nifti1Image(data, affine)
    img.header.set_xyzt_units("mm")
    nib.save(img, str(out_path))
    return out_path
