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

# A directory, lighter mesh, keep only the main object (drops CT table/noise)
python -m dicom3d.cli ./series_dir/ --step 2 --largest-only
```

If installed with `pip install -e .`, the `dicom3d` command is available directly.

### Key options

| Option | Meaning |
|---|---|
| `--iso` | Iso-surface level: a number in Hounsfield Units, or a preset: `bone` (300), `skin` (-300), `soft-tissue` (40), `lung` (-600). Default: bone for CT, mid-range otherwise. |
| `-f, --formats` | Comma-separated subset of `stl,obj,ply,glb`. |
| `--step` | Marching-cubes step; `>1` = coarser, lighter, faster. |
| `--no-smooth` | Disable Laplacian (Taubin) smoothing. |
| `--largest-only` | Keep only the largest connected component. |
| `--nifti` | Also export the raw volume as `.nii.gz`. |
| `--no-preview` | Skip the PNG preview. |

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
- Multi-frame (enhanced) DICOM and compressed transfer syntaxes rely on
  `pydicom`'s pixel handlers; install `pylibjpeg`/`gdcm` if you hit compressed
  data.
