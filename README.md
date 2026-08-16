# dicom3d — DICOM → 3D model

Turn a CT/MR **DICOM series** into a **3D surface model**, exported in several
widely-supported formats so it opens almost anywhere.

**Input**
- a `.zip` folder containing `.dcm` files, **or**
- multiple individual `.dcm` files, **or**
- a directory of DICOM files.

**Output** (all in real-world millimetre scale)
- `model.stl` — 3D printing, CAD, every mesh viewer
- `model.obj` — Blender, Maya, Cinema 4D, game engines, web
- `model.ply` — MeshLab, CloudCompare, research tooling
- `model.glb` — glTF binary: Windows **3D Viewer**, Blender, web/AR, Quick Look
- `model.nii.gz` — *(optional)* NIfTI volume for **3D Slicer** / **ITK-SNAP**
- `model_preview.png` — a shaded thumbnail so you can eyeball the result

Every mesh file is **self-contained** (no sidecar files) and **watertight**
where the anatomy allows — ready for slicing/printing.

---

## Install

```bash
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt      # or:  pip install -e .[web,test]
```

Works on Python 3.10–3.14.

## Command line

```bash
# A zip of DICOMs -> all formats in ./output
python -m dicom3d.cli scan.zip -o output

# Several loose .dcm files, bone surface, also write a NIfTI volume
python -m dicom3d.cli slice_*.dcm --iso bone --nifti

# A study with several series (T1/T2/FLAIR/...): see what's inside, then pick one
python -m dicom3d.cli study.zip --list-series
python -m dicom3d.cli study.zip --series "T2 TRA" -o output   # or --series 3

# A directory, lighter mesh, keep only the main object (drops CT table/noise)
python -m dicom3d.cli ./series_dir/ --step 2 --largest-only
```

If installed with `pip install -e .`, the `dicom3d` command is available directly.

### Key options

| Option | Meaning |
|---|---|
| `--iso` | Iso-surface level: a number, `auto`, or a CT preset: `bone` (300 HU), `skin` (-300), `soft-tissue` (40), `lung` (-600). Default `auto` = bone for CT; for **MRI** an automatic foreground/outer-surface threshold (see below). |
| `--series` | Which acquisition to reconstruct when a study holds several: an index (`0` = largest), a `SeriesInstanceUID`, or a description substring (e.g. `"T1 SAG"`). Default: the largest series. |
| `--list-series` | List the image series in the input and exit (no reconstruction). |
| `-f, --formats` | Comma-separated subset of `stl,obj,ply,glb`. |
| `--step` | Marching-cubes step; `>1` = coarser, lighter, faster. |
| `--smooth-volume` | Gaussian pre-smoothing (voxels) before surfacing. Default is modality-aware (~1 for MR to reduce speckle, 0 for CT). |
| `--no-smooth` | Disable Laplacian (Taubin) mesh smoothing. |
| `--largest-only` | Keep only the largest connected component. |
| `--nifti` | Also export the raw volume as `.nii.gz`. |
| `--no-preview` | Skip the PNG preview. |

### Studies with multiple series

A single DICOM export often bundles several acquisitions (T1, T2, FLAIR, DWI,
pre/post-contrast, localizers) — sometimes with the *same* image dimensions.
These must never be stacked together. dicom3d groups slices by
`SeriesInstanceUID` and reconstructs **one** series (the largest by default);
use `--list-series` / `--series` (CLI) or the series dropdown (web UI) to choose
a different one. In the web UI, switching series re-runs on the already-uploaded
files — no re-upload.

## Web UI

```bash
python -m dicom3d.webapp        # open http://127.0.0.1:5000
```

Drag-and-drop a `.zip` or select multiple `.dcm` files, choose the tissue
surface and detail, then:

- **rotate/zoom the model interactively** in the browser (an embedded
  `<model-viewer>` renders the generated GLB with orbit controls, auto-rotate,
  and AR on supported devices), and
- **download every format** — STL, OBJ, PLY, GLB (+ optional NIfTI).

A "Show static preview" toggle falls back to the rendered PNG. The
`model-viewer` web component is **vendored locally** (`dicom3d/static/`), so the
interactive viewer works fully **offline** — no CDN or internet required.

## Docker

```bash
docker compose up --build       # then open http://127.0.0.1:5000
# or:
docker build -t dicom3d .
docker run --rm -p 5000:5000 dicom3d
```

The image serves the web UI with gunicorn (2 workers, 600 s timeout) as a
non-root user.

## Python API

```python
from dicom3d import load_volume, generate_mesh, export_mesh

vol  = load_volume("scan.zip")            # or load_volume(files[0], extra_files=files[1:])
mesh = generate_mesh(vol, iso="bone", largest_only=True)
paths = export_mesh(mesh, "out/", formats=("stl", "glb"))
```

## How it works

1. **Load** — reads every DICOM (by `DICM` magic number, extension optional),
   keeps the image slices, and **sorts them along the acquisition axis** using
   `ImagePositionPatient` projected on the slice normal (robust to gantry tilt),
   falling back to `SliceLocation` / `InstanceNumber`.
2. **Volume** — stacks slices into a 3D array, applies rescale slope/intercept
   (→ Hounsfield Units for CT), and derives true voxel spacing in mm.
3. **Surface** — extracts an iso-surface with marching cubes at the chosen
   tissue level, optionally smooths and keeps the largest component.
4. **Export** — writes STL/OBJ/PLY/GLB (+ optional NIfTI) and a preview PNG.

### CT vs MRI thresholding

CT is calibrated in **Hounsfield Units**, so a fixed level means the same tissue
on every scanner — `bone = 300 HU` just works, and it's the default.

**MRI has no HU scale.** Intensities are arbitrary and sequence-dependent, and
bone is actually *dark* (signal void) on most MR sequences — so the CT presets
are meaningless for MR. Applying a fixed CT-style level to MR only catches the
brightest voxels (fat, flow) and yields a broken, holey shell. For MR (and any
non-CT modality) the tool therefore **auto-selects an Otsu threshold** that
separates the imaged anatomy from background air, giving a clean **outer
(skin/scalp) surface**. Just leave `--iso` on `auto`; CT presets are ignored for
MR. You can always pass a raw `--iso <number>` to target a specific intensity.

## Testing

```bash
pytest -q            # generates a synthetic phantom, runs the full pipeline
```

`tests/make_sample.py` also doubles as a sample generator:

```bash
python tests/make_sample.py samples   # writes samples/phantom.zip + samples/phantom_dcm/
```

## Notes & limitations

- The synthetic phantom and sample data contain **no real patient information**.
- Output meshes carry no PHI beyond geometry; DICOM tags are not embedded in
  the mesh files. Handle source DICOMs according to your local regulations.
- Marching cubes produces a *surface*, not a volumetric/segmented model. For
  clinical segmentation use dedicated tools (3D Slicer) on the exported NIfTI.
- **Compressed DICOM is supported out of the box.** The decompression backends
  (`pylibjpeg`, `pylibjpeg-libjpeg`, `pylibjpeg-openjpeg`, `python-gdcm`) are
  installed as dependencies, covering JPEG Lossless (Process 14 SV1), JPEG 2000,
  JPEG-LS, and RLE transfer syntaxes. If a backend is somehow missing, the
  loader raises a clear message telling you exactly what to `pip install`.
