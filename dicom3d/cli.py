"""Command-line interface: DICOM (zip / .dcm files) -> 3D model in many formats."""

from __future__ import annotations

import argparse
import sys
import time
from pathlib import Path

from .loader import list_series, load_volume
from .mesh import HU_PRESETS, SUPPORTED_FORMATS, export_mesh, generate_mesh
from .render import export_nifti, preview_png


def _build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="dicom3d",
        description=(
            "Convert a DICOM series into a 3D surface model, exported in "
            "multiple widely-supported formats (STL, OBJ, PLY, GLB, glTF)."
        ),
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    p.add_argument(
        "inputs",
        nargs="+",
        help="A .zip of DICOMs, a directory, or one or more .dcm files.",
    )
    p.add_argument(
        "-o", "--out", default="output", help="Output directory for the models."
    )
    p.add_argument(
        "-n", "--name", default="model", help="Base filename for the outputs."
    )
    p.add_argument(
        "--iso",
        default=None,
        help=(
            "Iso-surface level. A raw number, 'auto', or a CT preset "
            f"({', '.join(HU_PRESETS)}). Default 'auto' = bone (300 HU) for CT, "
            "or an automatic foreground/outer-surface threshold for MR (which has "
            "no Hounsfield scale, so the CT presets don't apply to it)."
        ),
    )
    p.add_argument(
        "--series",
        default=None,
        help=(
            "Which acquisition to reconstruct when the input holds several "
            "(a DICOM export often mixes T1/T2/FLAIR/etc.). An index (0 = largest), "
            "a SeriesInstanceUID, or a description substring (e.g. 'T1 SAG'). "
            "Default: the largest series."
        ),
    )
    p.add_argument(
        "--list-series",
        action="store_true",
        help="List the image series found in the input and exit (no reconstruction).",
    )
    p.add_argument(
        "-f",
        "--formats",
        default=",".join(SUPPORTED_FORMATS),
        help=f"Comma-separated mesh formats from: {', '.join(SUPPORTED_FORMATS)}.",
    )
    p.add_argument(
        "--step",
        type=int,
        default=1,
        help="Marching-cubes step size; >1 = coarser/lighter mesh.",
    )
    p.add_argument(
        "--no-smooth", action="store_true", help="Disable Laplacian smoothing."
    )
    p.add_argument(
        "--smooth-volume",
        type=float,
        default=None,
        metavar="SIGMA",
        help=(
            "Gaussian pre-smoothing (voxels) before surfacing. Default is "
            "modality-aware (~1 for MR to reduce speckle, 0 for CT). Set 0 to "
            "disable, or a larger value for a smoother/cleaner surface."
        ),
    )
    p.add_argument(
        "--largest-only",
        action="store_true",
        help="Keep only the largest connected component (drops table/noise).",
    )
    p.add_argument(
        "--nifti", action="store_true", help="Also export the volume as NIfTI (.nii.gz)."
    )
    p.add_argument(
        "--no-preview", action="store_true", help="Skip the PNG preview render."
    )
    return p


def _resolve_inputs(inputs: list[str]) -> tuple[str, list[str]]:
    """Return (primary source, extra files) for load_volume."""
    if len(inputs) == 1:
        return inputs[0], []
    # Multiple paths given -> treat them all as individual DICOM files.
    return inputs[0], inputs[1:]


def main(argv: list[str] | None = None) -> int:
    args = _build_parser().parse_args(argv)
    formats = tuple(f.strip().lower() for f in args.formats.split(",") if f.strip())

    for path in args.inputs:
        if not Path(path).exists():
            print(f"error: input not found: {path}", file=sys.stderr)
            return 2

    source, extra = _resolve_inputs(args.inputs)

    # Optionally just enumerate the series and exit.
    if args.list_series:
        try:
            series = list_series(source, extra_files=extra)
        except Exception as exc:
            print(f"error: could not read input: {exc}", file=sys.stderr)
            return 1
        print(f"Found {len(series)} image series:")
        for i, s in enumerate(series):
            print(
                f"  [{i}] {s['description'] or '(no description)':32} "
                f"{s['modality']:3} {s['num_slices']:4d} slices  "
                f"{s['rows']}x{s['cols']}"
            )
        print("\nReconstruct one with:  --series <index | description substring>")
        return 0

    t0 = time.time()
    print(f"[1/4] Loading DICOM from {len(args.inputs)} input(s)...")
    try:
        # Accept an integer index or a text selector for --series.
        sel: object = args.series
        if isinstance(sel, str) and sel.strip().lstrip("-").isdigit():
            sel = int(sel)
        vol = load_volume(source, extra_files=extra, series=sel)
    except Exception as exc:
        print(f"error: could not load volume: {exc}", file=sys.stderr)
        return 1
    m = vol.metadata
    n_series = m.get("num_series_available", 1)
    if n_series > 1 and args.series is None:
        others = ", ".join(
            f"{s['description'] or '(no desc)'} [{s['num_slices']}]"
            for s in m.get("series_available", [])
        )
        print(
            f"      note: input has {n_series} series; auto-selected the largest. "
            f"Use --list-series / --series to choose. All: {others}"
        )
    print(
        f"      {m['modality'] or 'scan'} '{m['series'] or m['study'] or 'series'}' "
        f"- {m['num_slices']} slices, shape {m['shape']}, "
        f"voxel {vol.spacing[0]:.2f}x{vol.spacing[1]:.2f}x{vol.spacing[2]:.2f} mm"
    )

    print(f"[2/4] Extracting surface (iso={args.iso or 'auto'})...")
    try:
        mesh = generate_mesh(
            vol,
            iso=args.iso,
            step_size=args.step,
            smooth=not args.no_smooth,
            largest_only=args.largest_only,
            prefilter_sigma=args.smooth_volume,
        )
    except Exception as exc:
        print(f"error: mesh generation failed: {exc}", file=sys.stderr)
        return 1
    print(
        f"      {mesh.metadata.get('surface', 'surface')} at level "
        f"{mesh.level:g} {mesh.metadata.get('iso_units', '')} -> "
        f"{mesh.metadata['n_vertices']:,} vertices, "
        f"{mesh.metadata['n_faces']:,} faces"
    )

    print(f"[3/4] Exporting formats: {', '.join(formats)}...")
    try:
        written = export_mesh(mesh, args.out, basename=args.name, formats=formats)
    except Exception as exc:
        print(f"error: export failed: {exc}", file=sys.stderr)
        return 1

    if args.nifti:
        written.append(export_nifti(vol, Path(args.out) / f"{args.name}.nii.gz"))

    print("[4/4] Rendering preview..." if not args.no_preview else "[4/4] Done.")
    if not args.no_preview:
        try:
            preview = preview_png(mesh, Path(args.out) / f"{args.name}_preview.png")
            written.append(preview)
        except Exception as exc:
            print(f"      (preview skipped: {exc})", file=sys.stderr)

    print(f"\nDone in {time.time() - t0:.1f}s. Wrote {len(written)} file(s):")
    for path in written:
        size = path.stat().st_size
        unit = f"{size / 1_048_576:.1f} MB" if size >= 1_048_576 else f"{size / 1024:.0f} KB"
        print(f"  - {path}  ({unit})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
