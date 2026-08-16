"""A small Flask web UI: upload a DICOM zip (or several .dcm files), get back a
3D model in every format plus an in-browser preview.

Run with:  python -m dicom3d.webapp   (then open http://127.0.0.1:5000)
"""

from __future__ import annotations

import shutil
import tempfile
import uuid
import zipfile
from pathlib import Path

from flask import (
    Flask,
    abort,
    jsonify,
    render_template_string,
    request,
    send_from_directory,
)

from .loader import load_volume
from .mesh import SUPPORTED_FORMATS, export_mesh, generate_mesh
from .render import export_nifti, preview_png

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 1024 * 1024 * 1024  # 1 GB uploads

# Jobs live under a temp root; each job gets its own subdirectory of outputs.
JOBS_ROOT = Path(tempfile.gettempdir()) / "dicom3d_jobs"
JOBS_ROOT.mkdir(parents=True, exist_ok=True)


PAGE = """<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DICOM &rarr; 3D Model</title>
<script type="module" src="/static/model-viewer.min.js"></script>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif;
         background: #0e1014; color: #e7e9ee; }
  header { padding: 28px 24px 12px; text-align: center; }
  h1 { margin: 0; font-size: 1.5rem; letter-spacing: .3px; }
  p.sub { margin: 6px 0 0; color: #9aa1ad; font-size: .92rem; }
  main { max-width: 720px; margin: 0 auto; padding: 16px 20px 60px; }
  .drop { border: 2px dashed #3a4150; border-radius: 14px; padding: 42px 20px;
          text-align: center; transition: .15s; cursor: pointer; background: #141821; }
  .drop.hover { border-color: #6ea8fe; background: #182034; }
  .drop strong { color: #6ea8fe; }
  input[type=file] { display: none; }
  .opts { display: flex; flex-wrap: wrap; gap: 14px; margin: 18px 0; }
  .opt { flex: 1 1 200px; background: #141821; border: 1px solid #262c38;
         border-radius: 10px; padding: 12px 14px; }
  .opt label { display: block; font-size: .78rem; color: #9aa1ad; margin-bottom: 6px; }
  select, input[type=text] { width: 100%; background: #0e1014; color: #e7e9ee;
         border: 1px solid #313849; border-radius: 7px; padding: 8px; font-size: .92rem; }
  button.go { width: 100%; margin-top: 6px; padding: 13px; font-size: 1rem; font-weight: 600;
         color: #06121f; background: #6ea8fe; border: 0; border-radius: 10px; cursor: pointer; }
  button.go:disabled { opacity: .5; cursor: default; }
  #status { margin-top: 18px; min-height: 24px; color: #9aa1ad; font-size: .92rem; }
  .result { margin-top: 22px; display: none; }
  .viewer-wrap { position: relative; border-radius: 12px; overflow: hidden; background: #000; }
  model-viewer { width: 100%; height: 460px; background: #0b0d11; --poster-color: #0b0d11; }
  .viewer-hint { position: absolute; bottom: 8px; left: 12px; color: #6b7280;
         font-size: .74rem; pointer-events: none; }
  .result img { width: 100%; border-radius: 12px; background: #000; display: none; }
  .toggle { background: none; border: 1px solid #313849; color: #9aa1ad;
         border-radius: 7px; padding: 5px 10px; font-size: .78rem; cursor: pointer;
         margin: 10px 0 0; }
  .series-pick { background: #182034; border: 1px solid #2a3550; border-radius: 10px;
         padding: 12px 14px; margin-bottom: 14px; }
  .series-pick label { display: block; font-size: .82rem; color: #b9c2d4; margin-bottom: 8px; }
  .series-pick select { width: 100%; }
  .files { list-style: none; padding: 0; margin: 16px 0 0; }
  .files li { display: flex; justify-content: space-between; align-items: center;
         padding: 10px 14px; background: #141821; border: 1px solid #262c38;
         border-radius: 9px; margin-bottom: 8px; }
  .files a { color: #6ea8fe; text-decoration: none; font-weight: 600; }
  .spin { display: inline-block; width: 14px; height: 14px; border: 2px solid #3a4150;
         border-top-color: #6ea8fe; border-radius: 50%; animation: s .8s linear infinite;
         vertical-align: -2px; margin-right: 8px; }
  @keyframes s { to { transform: rotate(360deg); } }
  .meta { color: #6b7280; font-size: .8rem; }
</style>
</head>
<body>
<header>
  <h1>DICOM &rarr; 3D Model</h1>
  <p class="sub">Drop a <code>.zip</code> of DICOMs or select multiple <code>.dcm</code> files.
     Get STL, OBJ, PLY, GLB (+ optional NIfTI).</p>
</header>
<main>
  <form id="form">
    <div class="drop" id="drop">
      <p>Drag &amp; drop here, or <strong>click to browse</strong></p>
      <p class="meta" id="picked">No files selected</p>
      <input type="file" id="file" name="files" multiple accept=".zip,.dcm,application/zip">
    </div>

    <div class="opts">
      <div class="opt">
        <label>Iso surface</label>
        <select name="iso">
          <option value="">Auto (bone for CT · outer surface for MRI)</option>
          <optgroup label="CT presets (Hounsfield Units)">
            <option value="bone">Bone (300 HU)</option>
            <option value="skin">Skin (-300 HU)</option>
            <option value="soft-tissue">Soft tissue (40 HU)</option>
            <option value="lung">Lung (-600 HU)</option>
          </optgroup>
        </select>
        <div class="meta" style="margin-top:6px">MRI has no HU scale — leave on
          Auto; CT presets are ignored for MR.</div>
      </div>
      <div class="opt">
        <label>Detail</label>
        <select name="step">
          <option value="1">Full resolution</option>
          <option value="2">Medium (lighter)</option>
          <option value="3">Coarse (fastest)</option>
        </select>
      </div>
      <div class="opt">
        <label>Options</label>
        <div style="font-size:.85rem;line-height:1.9">
          <label style="display:inline"><input type="checkbox" name="largest" checked> Largest part only</label><br>
          <label style="display:inline"><input type="checkbox" name="nifti"> Also export NIfTI</label>
        </div>
      </div>
    </div>

    <button class="go" id="go" type="submit">Generate 3D model</button>
  </form>

  <div id="status"></div>

  <div class="result" id="result">
    <div class="series-pick" id="seriesPick" style="display:none">
      <label for="seriesSel">This study has multiple series — choose one to reconstruct:</label>
      <select id="seriesSel"></select>
      <span class="spin" id="seriesSpin" style="display:none"></span>
    </div>
    <div class="viewer-wrap">
      <model-viewer id="viewer" camera-controls auto-rotate
                    shadow-intensity="0.9" exposure="0.85" tone-mapping="neutral"
                    interaction-prompt="none" alt="Interactive 3D model"></model-viewer>
      <span class="viewer-hint">drag to rotate &middot; scroll to zoom</span>
    </div>
    <img id="preview" alt="3D preview">
    <button class="toggle" id="toggle" type="button">Show static preview instead</button>
    <ul class="files" id="files"></ul>
  </div>
</main>

<script>
const drop = document.getElementById('drop');
const fileInput = document.getElementById('file');
const picked = document.getElementById('picked');
const form = document.getElementById('form');
const statusEl = document.getElementById('status');
const go = document.getElementById('go');

drop.addEventListener('click', () => fileInput.click());
['dragover','dragenter'].forEach(e => drop.addEventListener(e, ev => {
  ev.preventDefault(); drop.classList.add('hover');
}));
['dragleave','drop'].forEach(e => drop.addEventListener(e, ev => {
  ev.preventDefault(); drop.classList.remove('hover');
}));
drop.addEventListener('drop', ev => { fileInput.files = ev.dataTransfer.files; showPicked(); });
fileInput.addEventListener('change', showPicked);
function showPicked() {
  const n = fileInput.files.length;
  picked.textContent = n ? (n === 1 ? fileInput.files[0].name : n + ' files selected') : 'No files selected';
}

let currentJob = null;

function currentOpts() {
  // Snapshot the form's reconstruction settings (not the files) for regenerate.
  const fd = new FormData(form);
  const o = new URLSearchParams();
  for (const k of ['iso','step']) o.set(k, fd.get(k) ?? '');
  if (fd.get('largest') !== null) o.set('largest', 'on');
  if (fd.get('nifti') !== null) o.set('nifti', 'on');
  return o;
}

function renderResult(data) {
  statusEl.textContent = data.summary;
  currentJob = data.job_id;

  // Series picker: only shown when the study holds more than one series.
  const pick = document.getElementById('seriesPick');
  const sel = document.getElementById('seriesSel');
  if (data.series && data.series.length > 1) {
    sel.innerHTML = '';
    data.series.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.index;
      opt.textContent = `${s.description} — ${s.modality} ${s.num_slices} slices`;
      if (s.used) opt.selected = true;
      sel.appendChild(opt);
    });
    pick.style.display = 'block';
  } else {
    pick.style.display = 'none';
  }

  const files = document.getElementById('files');
  files.innerHTML = '';
  data.files.forEach(f => {
    const li = document.createElement('li');
    li.innerHTML = `<span>${f.name} <span class="meta">${f.size}</span></span>` +
                   `<a href="${f.url}" download>Download</a>`;
    files.appendChild(li);
  });
  const viewer = document.getElementById('viewer');
  const preview = document.getElementById('preview');
  preview.src = data.preview + '?t=' + Date.now();
  if (data.model) {
    viewer.setAttribute('src', data.model + '?t=' + Date.now());
    viewer.setAttribute('poster', data.preview + '?t=' + Date.now());
    viewer.parentElement.style.display = 'block';
    preview.style.display = 'none';
    document.getElementById('toggle').style.display = 'inline-block';
  } else {
    viewer.parentElement.style.display = 'none';
    preview.style.display = 'block';
    document.getElementById('toggle').style.display = 'none';
  }
  document.getElementById('result').style.display = 'block';
}

form.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  if (!fileInput.files.length) { statusEl.textContent = 'Please choose a file first.'; return; }
  const fd = new FormData(form);
  for (const f of fileInput.files) fd.append('files', f);
  go.disabled = true;
  document.getElementById('result').style.display = 'none';
  statusEl.innerHTML = '<span class="spin"></span> Uploading and reconstructing (this can take a moment)...';
  try {
    const res = await fetch('/api/convert', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Conversion failed');
    renderResult(data);
  } catch (e) {
    statusEl.textContent = 'Error: ' + e.message;
  } finally {
    go.disabled = false;
  }
});

document.getElementById('seriesSel').addEventListener('change', async (ev) => {
  if (!currentJob) return;
  const spin = document.getElementById('seriesSpin');
  spin.style.display = 'inline-block';
  const body = currentOpts();
  body.set('job_id', currentJob);
  body.set('series', ev.target.value);
  try {
    const res = await fetch('/api/regenerate', { method: 'POST', body });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Regeneration failed');
    renderResult(data);
  } catch (e) {
    statusEl.textContent = 'Error: ' + e.message;
  } finally {
    spin.style.display = 'none';
  }
});

document.getElementById('toggle').addEventListener('click', () => {
  const wrap = document.querySelector('.viewer-wrap');
  const preview = document.getElementById('preview');
  const btn = document.getElementById('toggle');
  const showingStatic = preview.style.display === 'block';
  preview.style.display = showingStatic ? 'none' : 'block';
  wrap.style.display = showingStatic ? 'block' : 'none';
  btn.textContent = showingStatic ? 'Show static preview instead' : 'Show interactive 3D instead';
});
</script>
</body>
</html>"""


@app.get("/")
def index():
    return render_template_string(PAGE)


def _human(n: int) -> str:
    return f"{n / 1_048_576:.1f} MB" if n >= 1_048_576 else f"{n / 1024:.0f} KB"


def _source_from_upload(upload_dir: Path) -> tuple[Path, list[Path]]:
    """Derive (primary source, extra files) from a job's uploaded files.

    A single uploaded .zip is the source; otherwise every upload is a loose
    DICOM file.
    """
    saved = sorted(p for p in upload_dir.iterdir() if p.is_file())
    zips = [p for p in saved if p.suffix.lower() == ".zip"]
    if zips:
        return zips[0], []
    return saved[0], saved[1:]


def _run_job(job_id: str, opts: dict):
    """Build the volume/mesh for a job and return the JSON-able response dict.

    Reused by both /api/convert (first run) and /api/regenerate (re-run with a
    different series/settings on the already-uploaded files).
    """
    job_dir = JOBS_ROOT / job_id
    upload_dir = job_dir / "upload"
    out_dir = job_dir / "out"
    if not upload_dir.is_dir():
        return jsonify(error="Job not found or expired."), 404

    source, extra = _source_from_upload(upload_dir)
    try:
        vol = load_volume(source, extra_files=extra, series=opts["series"])
        mesh = generate_mesh(
            vol,
            iso=opts["iso"],
            step_size=opts["step"],
            smooth=True,
            largest_only=opts["largest"],
        )
        if out_dir.exists():
            shutil.rmtree(out_dir, ignore_errors=True)
        written = export_mesh(mesh, out_dir, basename="model", formats=SUPPORTED_FORMATS)
        if opts["nifti"]:
            written.append(export_nifti(vol, out_dir / "model.nii.gz"))
        preview_png(mesh, out_dir / "preview.png")
    except Exception as exc:
        return jsonify(error=str(exc)), 422

    m = mesh.metadata
    available = m.get("series_available", [])
    used_uid = m.get("series_uid")
    series_list = [
        {
            "index": i,
            "description": s["description"] or "(no description)",
            "modality": s["modality"],
            "num_slices": s["num_slices"],
            "used": s["series_uid"] == used_uid,
        }
        for i, s in enumerate(available)
    ]
    summary = (
        f"{m.get('modality') or 'Scan'} · {m.get('series') or 'series'} · "
        f"{m['num_slices']} slices · {mesh.level:g} {m.get('iso_units', '')} · "
        f"{m['n_vertices']:,} verts / {m['n_faces']:,} faces"
    )
    file_list = [
        {
            "name": p.name,
            "size": _human(p.stat().st_size),
            "url": f"/download/{job_id}/{p.name}",
        }
        for p in written
    ]
    glb = next((f["url"] for f in file_list if f["name"].endswith(".glb")), None)
    return jsonify(
        job_id=job_id,
        summary=summary,
        files=file_list,
        model=glb,  # GLB drives the interactive <model-viewer>
        preview=f"/download/{job_id}/preview.png",
        series=series_list,
    )


def _opts_from_form() -> dict:
    return {
        "iso": request.form.get("iso") or None,
        "step": int(request.form.get("step", 1) or 1),
        "largest": request.form.get("largest") is not None,
        "nifti": request.form.get("nifti") is not None,
        "series": _parse_series(request.form.get("series")),
    }


def _parse_series(value):
    """Series selector: blank -> None (largest); digits -> int index; else text."""
    if value is None or value == "":
        return None
    value = value.strip()
    if value.lstrip("-").isdigit():
        return int(value)
    return value


@app.post("/api/convert")
def convert():
    files = request.files.getlist("files")
    if not files or all(not f.filename for f in files):
        return jsonify(error="No files uploaded."), 400

    job_id = uuid.uuid4().hex[:12]
    upload_dir = JOBS_ROOT / job_id / "upload"
    upload_dir.mkdir(parents=True, exist_ok=True)

    for f in files:
        if not f.filename:
            continue
        f.save(upload_dir / Path(f.filename).name)  # strip any path components

    resp = _run_job(job_id, _opts_from_form())
    if resp.status_code >= 400:
        shutil.rmtree(JOBS_ROOT / job_id, ignore_errors=True)
    return resp


@app.post("/api/regenerate")
def regenerate():
    """Re-run an existing job with a different series/settings, reusing the
    already-uploaded files (no re-upload)."""
    job_id = request.form.get("job_id", "")
    if not job_id.isalnum() or not (JOBS_ROOT / job_id / "upload").is_dir():
        return jsonify(error="Job not found or expired; please re-upload."), 404
    return _run_job(job_id, _opts_from_form())


@app.get("/download/<job_id>/<path:filename>")
def download(job_id: str, filename: str):
    # job_id is a generated hex string; reject anything else to avoid traversal.
    if not job_id.isalnum():
        abort(404)
    out_dir = JOBS_ROOT / job_id / "out"
    if not (out_dir / filename).is_file():
        abort(404)
    return send_from_directory(out_dir, filename, as_attachment=False)


def main():
    import argparse

    ap = argparse.ArgumentParser(description="Run the DICOM->3D web UI.")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=5000)
    ap.add_argument("--debug", action="store_true")
    args = ap.parse_args()
    print(f"Serving DICOM->3D UI at http://{args.host}:{args.port}")
    app.run(host=args.host, port=args.port, debug=args.debug)


if __name__ == "__main__":
    main()
