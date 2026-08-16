"""Generate a synthetic CT-like DICOM series for testing (no real patient data).

Creates a hollow sphere ("skull") with a denser core, written as a stack of
CT DICOM slices. Produces both a directory of .dcm files and a .zip.
"""

from __future__ import annotations

import sys
import zipfile
from pathlib import Path

import numpy as np
import pydicom
from pydicom.dataset import Dataset, FileMetaDataset
from pydicom.uid import ExplicitVRLittleEndian, generate_uid


def make_series(out_dir: Path, n=64, size=96, spacing=(2.0, 1.5, 1.5)):
    out_dir.mkdir(parents=True, exist_ok=True)
    z_mm, y_mm, x_mm = spacing

    study_uid = generate_uid()
    series_uid = generate_uid()

    zz, yy, xx = np.mgrid[0:n, 0:size, 0:size].astype(np.float32)
    cz, cy, cx = n / 2, size / 2, size / 2
    # physical-space radius so the sphere isn't squashed by anisotropic spacing
    r = np.sqrt(
        ((zz - cz) * z_mm) ** 2 + ((yy - cy) * y_mm) ** 2 + ((xx - cx) * x_mm) ** 2
    )
    # Size the sphere to the smallest physical half-extent so it always fits
    # inside the volume (no clipping) regardless of the requested dimensions.
    half_extent = min(n * z_mm, size * y_mm, size * x_mm) / 2.0
    outer = 0.82 * half_extent
    inner = 0.66 * half_extent
    vol = np.full((n, size, size), -1000.0, dtype=np.float32)  # air
    vol[r < outer] = 40.0  # soft tissue
    vol[(r < outer) & (r > inner)] = 800.0  # bony shell
    vol += np.random.default_rng(0).normal(0, 15, vol.shape)  # mild noise

    slope, intercept = 1.0, -1024.0
    stored = np.clip(np.round(vol - intercept), 0, 4095).astype(np.uint16)

    paths = []
    for i in range(n):
        ds = Dataset()
        ds.file_meta = FileMetaDataset()
        ds.file_meta.MediaStorageSOPClassUID = "1.2.840.10008.5.1.4.1.1.2"  # CT
        ds.file_meta.MediaStorageSOPInstanceUID = generate_uid()
        ds.file_meta.TransferSyntaxUID = ExplicitVRLittleEndian
        ds.file_meta.ImplementationClassUID = generate_uid()

        ds.SOPClassUID = "1.2.840.10008.5.1.4.1.1.2"
        ds.SOPInstanceUID = ds.file_meta.MediaStorageSOPInstanceUID
        ds.StudyInstanceUID = study_uid
        ds.SeriesInstanceUID = series_uid
        ds.PatientID = "TEST-PHANTOM"
        ds.PatientName = "Sphere^Phantom"
        ds.Modality = "CT"
        ds.StudyDescription = "Synthetic head phantom"
        ds.SeriesDescription = "Hollow sphere"
        ds.InstanceNumber = i + 1

        ds.Rows, ds.Columns = size, size
        ds.PixelSpacing = [y_mm, x_mm]
        ds.SliceThickness = z_mm
        ds.SpacingBetweenSlices = z_mm
        ds.ImagePositionPatient = [0.0, 0.0, i * z_mm]
        ds.ImageOrientationPatient = [1, 0, 0, 0, 1, 0]
        ds.SliceLocation = i * z_mm

        ds.SamplesPerPixel = 1
        ds.PhotometricInterpretation = "MONOCHROME2"
        ds.BitsAllocated = 16
        ds.BitsStored = 12
        ds.HighBit = 11
        ds.PixelRepresentation = 0
        ds.RescaleSlope = slope
        ds.RescaleIntercept = intercept
        ds.PixelData = stored[i].tobytes()

        ds.is_little_endian = True
        ds.is_implicit_VR = False

        path = out_dir / f"slice_{i:03d}.dcm"
        ds.save_as(path, enforce_file_format=True)
        paths.append(path)

    return paths


def make_mr_series(out_dir: Path, n=48, size=88, spacing=(2.5, 2.0, 2.0)):
    """Write a synthetic MR series: a bright ellipsoidal 'head' on a dark
    background with a brighter fat rim and a few flow/artifact hot voxels.

    Crucially the intensities are on an arbitrary scale with **no** Hounsfield
    rescale (RescaleSlope=1, Intercept=0) and Modality='MR' — so it exercises
    the MR (non-HU) auto-thresholding path, where a fixed CT constant fails.
    """
    out_dir.mkdir(parents=True, exist_ok=True)
    z_mm, y_mm, x_mm = spacing
    study_uid, series_uid = generate_uid(), generate_uid()
    rng = np.random.default_rng(1)

    zz, yy, xx = np.mgrid[0:n, 0:size, 0:size].astype(np.float32)
    cz, cy, cx = n / 2, size / 2, size / 2
    half = min(n * z_mm, size * y_mm, size * x_mm) / 2.0
    rad = 0.82 * half
    e = (
        ((zz - cz) * z_mm) ** 2 + ((yy - cy) * y_mm) ** 2 + ((xx - cx) * x_mm) ** 2
    ) / rad**2
    head = e < 1.0
    vol = rng.normal(18, 6, (n, size, size)).astype(np.float32)  # dark air
    vol[head] = rng.normal(620, 55, head.sum())  # bright tissue
    vol[e < 0.25] = rng.normal(300, 40, (e < 0.25).sum())  # inner structure
    vol[head & ~(e < 0.85)] = rng.normal(1500, 120, (head & ~(e < 0.85)).sum())  # fat
    vol.flat[rng.integers(0, vol.size, 150)] = 3000.0  # flow/artifact hot voxels
    stored = np.clip(vol, 0, 4095).astype(np.uint16)

    paths = []
    for i in range(n):
        ds = Dataset()
        ds.file_meta = FileMetaDataset()
        ds.file_meta.MediaStorageSOPClassUID = "1.2.840.10008.5.1.4.1.1.4"  # MR
        ds.file_meta.MediaStorageSOPInstanceUID = generate_uid()
        ds.file_meta.TransferSyntaxUID = ExplicitVRLittleEndian
        ds.file_meta.ImplementationClassUID = generate_uid()

        ds.SOPClassUID = "1.2.840.10008.5.1.4.1.1.4"
        ds.SOPInstanceUID = ds.file_meta.MediaStorageSOPInstanceUID
        ds.StudyInstanceUID = study_uid
        ds.SeriesInstanceUID = series_uid
        ds.PatientID = "TEST-MR-PHANTOM"
        ds.PatientName = "Ellipsoid^MR"
        ds.Modality = "MR"
        ds.SeriesDescription = "T1 phantom"
        ds.InstanceNumber = i + 1

        ds.Rows, ds.Columns = size, size
        ds.PixelSpacing = [y_mm, x_mm]
        ds.SliceThickness = z_mm
        ds.SpacingBetweenSlices = z_mm
        ds.ImagePositionPatient = [0.0, 0.0, i * z_mm]
        ds.ImageOrientationPatient = [1, 0, 0, 0, 1, 0]
        ds.SliceLocation = i * z_mm

        ds.SamplesPerPixel = 1
        ds.PhotometricInterpretation = "MONOCHROME2"
        ds.BitsAllocated = 16
        ds.BitsStored = 12
        ds.HighBit = 11
        ds.PixelRepresentation = 0
        # No meaningful Hounsfield scale for MR.
        ds.RescaleSlope = 1.0
        ds.RescaleIntercept = 0.0
        ds.PixelData = stored[i].tobytes()

        ds.is_little_endian = True
        ds.is_implicit_VR = False

        path = out_dir / f"mr_{i:03d}.dcm"
        ds.save_as(path, enforce_file_format=True)
        paths.append(path)

    return paths


def main():
    root = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("samples")
    ddir = root / "phantom_dcm"
    paths = make_series(ddir)
    zip_path = root / "phantom.zip"
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for p in paths:
            zf.write(p, arcname=p.name)
    print(f"Wrote {len(paths)} CT slices to {ddir}")
    print(f"Wrote zip {zip_path}")

    mrdir = root / "phantom_mr_dcm"
    mr_paths = make_mr_series(mrdir)
    mr_zip = root / "phantom_mr.zip"
    with zipfile.ZipFile(mr_zip, "w", zipfile.ZIP_DEFLATED) as zf:
        for p in mr_paths:
            zf.write(p, arcname=p.name)
    print(f"Wrote {len(mr_paths)} MR slices to {mrdir}")
    print(f"Wrote zip {mr_zip}")


if __name__ == "__main__":
    main()
