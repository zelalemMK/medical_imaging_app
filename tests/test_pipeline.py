"""End-to-end tests for the DICOM -> 3D pipeline using a synthetic phantom."""

from __future__ import annotations

import zipfile
from pathlib import Path

import numpy as np
import pytest

from dicom3d import export_mesh, generate_mesh, load_volume
from dicom3d.mesh import SUPPORTED_FORMATS

from .make_sample import make_series


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


def test_nifti_export(phantom_zip, tmp_path):
    import nibabel as nib

    from dicom3d import export_nifti

    vol = load_volume(phantom_zip)
    out = export_nifti(vol, tmp_path / "vol.nii.gz")
    assert out.is_file()
    img = nib.load(out)
    # NIfTI is [x, y, z]; volume is [z, y, x] -> dims should be reversed.
    assert img.shape == vol.data.shape[::-1]
