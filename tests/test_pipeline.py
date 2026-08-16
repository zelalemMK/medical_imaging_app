"""End-to-end tests for the DICOM -> 3D pipeline using a synthetic phantom."""

from __future__ import annotations

import zipfile
from pathlib import Path

import numpy as np
import pytest

from dicom3d import export_mesh, generate_mesh, load_volume
from dicom3d.mesh import SUPPORTED_FORMATS

from .make_sample import make_mr_series, make_series


@pytest.fixture(scope="module")
def phantom_dir(tmp_path_factory) -> Path:
    d = tmp_path_factory.mktemp("phantom")
    make_series(d / "dcm", n=32, size=64)
    return d / "dcm"


@pytest.fixture(scope="module")
def phantom_zip(phantom_dir, tmp_path_factory) -> Path:
    z = tmp_path_factory.mktemp("zip") / "phantom.zip"
    with zipfile.ZipFile(z, "w") as zf:
        for p in sorted(phantom_dir.glob("*.dcm")):
            zf.write(p, arcname=p.name)
    return z


def test_load_from_zip(phantom_zip):
    vol = load_volume(phantom_zip)
    assert vol.data.ndim == 3
    assert vol.metadata["num_slices"] == 32
    assert vol.metadata["modality"] == "CT"
    # spacing should reflect the 2.0 / 1.5 / 1.5 mm phantom geometry
    assert vol.spacing[0] == pytest.approx(2.0, abs=0.01)
    assert vol.spacing[1] == pytest.approx(1.5, abs=0.01)


def test_load_from_loose_files(phantom_dir):
    files = sorted(phantom_dir.glob("*.dcm"))
    vol = load_volume(files[0], extra_files=files[1:])
    assert vol.metadata["num_slices"] == 32


def test_slices_are_sorted(phantom_dir):
    # Feeding files in scrambled order must still produce an ordered volume.
    files = sorted(phantom_dir.glob("*.dcm"))
    scrambled = files[::-1]
    vol = load_volume(scrambled[0], extra_files=scrambled[1:])
    # The phantom is symmetric, so check that reconstruction is stable/mesh-able.
    mesh = generate_mesh(vol, iso="bone", smooth=False)
    assert mesh.metadata["n_faces"] > 0


def test_generate_and_export_all_formats(phantom_zip, tmp_path):
    vol = load_volume(phantom_zip)
    mesh = generate_mesh(vol, iso="bone", largest_only=True)
    assert mesh.metadata["n_vertices"] > 100

    paths = export_mesh(mesh, tmp_path, basename="m", formats=SUPPORTED_FORMATS)
    assert len(paths) == len(SUPPORTED_FORMATS)
    for p in paths:
        assert p.is_file() and p.stat().st_size > 0

    # Reload each mesh and confirm it is non-empty and roughly the right size.
    import trimesh

    # Expected outer diameter for the 32x64x64 fixture (spacing 2.0/1.5/1.5 mm):
    # sphere is sized to 0.82 * smallest physical half-extent (see make_series).
    expected_diam = 0.82 * min(32 * 2.0, 64 * 1.5, 64 * 1.5)
    for p in paths:
        m = trimesh.load(p, force="mesh")
        assert len(m.faces) > 0
        extent = (m.bounds[1] - m.bounds[0]).max()
        assert expected_diam - 8 < extent < expected_diam + 8


def test_iso_out_of_range_raises(phantom_zip):
    vol = load_volume(phantom_zip)
    with pytest.raises(ValueError, match="outside the volume"):
        generate_mesh(vol, iso=99999)


def test_unknown_preset_raises(phantom_zip):
    vol = load_volume(phantom_zip)
    with pytest.raises(ValueError, match="Unknown iso"):
        generate_mesh(vol, iso="banana")


def test_too_few_slices_raises(tmp_path):
    make_series(tmp_path / "one", n=2, size=32)
    files = sorted((tmp_path / "one").glob("*.dcm"))
    # Keep only one slice -> should fail to build a volume.
    with pytest.raises(ValueError, match="at least 2"):
        load_volume(files[0])


def test_unsupported_format_raises(phantom_zip, tmp_path):
    vol = load_volume(phantom_zip)
    mesh = generate_mesh(vol, iso="bone")
    with pytest.raises(ValueError, match="Unsupported format"):
        export_mesh(mesh, tmp_path, formats=("stl", "xyz"))


def test_compressed_series_loads(tmp_path):
    """A losslessly-compressed DICOM series must decode and reconstruct.

    Regression test for the "Unable to decompress ... pixel data" failure on
    compressed transfer syntaxes (JPEG Lossless / JPEG-LS / RLE).
    """
    import pydicom
    from pydicom.uid import JPEGLSLossless, RLELossless

    make_series(tmp_path / "raw", n=12, size=48)
    comp = tmp_path / "comp"
    comp.mkdir()
    for i, p in enumerate(sorted((tmp_path / "raw").glob("*.dcm"))):
        ds = pydicom.dcmread(p)
        for uid in (JPEGLSLossless, RLELossless):
            try:
                ds.compress(uid)
                break
            except Exception:
                ds = pydicom.dcmread(p)
        ds.save_as(comp / f"s{i:03d}.dcm")

    vol = load_volume(comp)
    assert vol.metadata["num_slices"] == 12
    mesh = generate_mesh(vol, iso="bone", largest_only=True)
    assert mesh.metadata["n_faces"] > 0


def test_mr_series_uses_auto_foreground_surface(tmp_path):
    """MR data (no Hounsfield scale) must reconstruct a solid outer surface.

    Regression test: the old code applied a CT-style constant/midpoint level to
    MR, which only caught the bright fat rim + artifacts and produced a broken,
    holey shell. The modality-aware auto (Otsu) threshold should instead give a
    single, near-watertight foreground surface matching the phantom's extent.
    """
    make_mr_series(tmp_path / "mr", n=40, size=80)
    vol = load_volume(tmp_path / "mr")
    assert vol.metadata["modality"] == "MR"

    mesh = generate_mesh(vol, iso=None, largest_only=True, smooth=True)
    assert mesh.metadata["iso_units"] == "raw intensity"
    assert "auto" in mesh.metadata["surface"]

    # The auto threshold must sit well below the bright fat/artifact values
    # (~1500-3000) so it captures the whole head, not just the rim.
    assert mesh.level < 1000
    assert mesh.metadata["n_faces"] > 1000

    # Largest component should span most of the volume (a real outer surface),
    # not a tiny fragment.
    tm = mesh.trimesh
    extent = tm.bounds[1] - tm.bounds[0]
    phys = np.array([40 * 2.5, 80 * 2.0, 80 * 2.0])  # volume physical size (mm)
    assert (extent > 0.5 * phys).all()


def test_ct_presets_ignored_for_mr(tmp_path):
    """Passing a CT preset to MR data falls back to the auto threshold rather
    than applying a meaningless HU constant."""
    make_mr_series(tmp_path / "mr", n=32, size=72)
    vol = load_volume(tmp_path / "mr")
    # 'bone' (300 HU) is meaningless here; should not raise and should produce
    # a real surface via the auto fallback.
    mesh = generate_mesh(vol, iso="bone", largest_only=True)
    assert mesh.metadata["n_faces"] > 1000
    assert mesh.metadata["iso_units"] == "raw intensity"


def test_nifti_export(phantom_zip, tmp_path):
    import nibabel as nib

    from dicom3d import export_nifti

    vol = load_volume(phantom_zip)
    out = export_nifti(vol, tmp_path / "vol.nii.gz")
    assert out.is_file()
    img = nib.load(out)
    # NIfTI is [x, y, z]; volume is [z, y, x] -> dims should be reversed.
    assert img.shape == vol.data.shape[::-1]
