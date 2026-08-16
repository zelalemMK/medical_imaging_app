"""Load a DICOM series from a zip archive or a set of .dcm files into a 3D volume."""

from __future__ import annotations

import io
import zipfile
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, Sequence

import numpy as np
import pydicom
from pydicom.dataset import FileDataset


@dataclass
class Volume:
    """A 3D scalar volume plus the geometry needed to render it to real-world scale.

    Attributes
    ----------
    data:
        3D array indexed as ``[slice, row, col]`` (i.e. ``[z, y, x]``) of
        Hounsfield-like scalar values (rescale slope/intercept applied).
    spacing:
        Physical voxel size in millimetres as ``(z, y, x)``.
    metadata:
        A few human-readable tags describing where the scan came from.
    """

    data: np.ndarray
    spacing: tuple[float, float, float]
    metadata: dict


# DICOM files do not need a .dcm extension; the reliable signal is the
# "DICM" magic number at byte offset 128. We accept either.
_DICM_MAGIC_OFFSET = 128
_DICM_MAGIC = b"DICM"


def _looks_like_dicom(blob: bytes) -> bool:
    return len(blob) > _DICM_MAGIC_OFFSET + 4 and (
        blob[_DICM_MAGIC_OFFSET : _DICM_MAGIC_OFFSET + 4] == _DICM_MAGIC
    )


def _iter_zip_dicoms(zip_path: Path) -> Iterable[FileDataset]:
    with zipfile.ZipFile(zip_path) as zf:
        for info in zf.infolist():
            if info.is_dir():
                continue
            name = Path(info.filename).name
            if name.startswith("._") or name == ".DS_Store":
                continue  # macOS resource-fork / metadata junk
            blob = zf.read(info)
            if not (_looks_like_dicom(blob) or info.filename.lower().endswith(".dcm")):
                continue
            try:
                yield pydicom.dcmread(io.BytesIO(blob), force=True)
            except Exception:
                continue  # skip non-image or corrupt members silently


def _iter_file_dicoms(paths: Sequence[Path]) -> Iterable[FileDataset]:
    for p in paths:
        try:
            yield pydicom.dcmread(str(p), force=True)
        except Exception:
            continue


def _is_image_slice(ds: FileDataset) -> bool:
    """True if the dataset carries actual pixel data we can stack."""
    return "PixelData" in ds and int(getattr(ds, "Rows", 0)) > 0


def _slice_sort_key(ds: FileDataset):
    """Order slices along the acquisition axis.

    Prefer ImagePositionPatient projected onto the slice normal (robust to
    gantry tilt); fall back to SliceLocation, then InstanceNumber.
    """
    ipp = getattr(ds, "ImagePositionPatient", None)
    iop = getattr(ds, "ImageOrientationPatient", None)
    if ipp is not None and iop is not None and len(iop) == 6:
        row = np.array(iop[:3], dtype=float)
        col = np.array(iop[3:], dtype=float)
        normal = np.cross(row, col)
        return float(np.dot(np.array(ipp, dtype=float), normal))
    if getattr(ds, "SliceLocation", None) is not None:
        return float(ds.SliceLocation)
    return float(getattr(ds, "InstanceNumber", 0))


def _rescale(pixel: np.ndarray, ds: FileDataset) -> np.ndarray:
    slope = float(getattr(ds, "RescaleSlope", 1) or 1)
    intercept = float(getattr(ds, "RescaleIntercept", 0) or 0)
    return pixel.astype(np.float32) * slope + intercept


def _slice_spacing(slices: list[FileDataset]) -> float:
    """Millimetres between consecutive slices, derived from positions if possible."""
    positions = []
    for ds in slices:
        ipp = getattr(ds, "ImagePositionPatient", None)
        if ipp is not None:
            positions.append(_slice_sort_key(ds))
    if len(positions) >= 2:
        diffs = np.diff(np.sort(np.array(positions, dtype=float)))
        diffs = diffs[diffs > 1e-4]
        if diffs.size:
            return float(np.median(diffs))
    for attr in ("SpacingBetweenSlices", "SliceThickness"):
        val = getattr(slices[0], attr, None)
        if val:
            return float(val)
    return 1.0


def build_volume(datasets: Iterable[FileDataset]) -> Volume:
    """Stack sorted image slices into a :class:`Volume`.

    Raises
    ------
    ValueError
        If fewer than two image slices with pixel data are found.
    """
    slices = [ds for ds in datasets if _is_image_slice(ds)]
    if len(slices) < 2:
        raise ValueError(
            f"Need at least 2 DICOM image slices to build a volume, found {len(slices)}. "
            "The input may contain only non-image DICOM objects (e.g. reports/dose)."
        )

    # All slices in a series should share dimensions; group by the most common
    # shape so a stray localizer of a different size can't break the stack.
    from collections import Counter

    shape_counts = Counter((int(s.Rows), int(s.Columns)) for s in slices)
    keep_shape, _ = shape_counts.most_common(1)[0]
    slices = [s for s in slices if (int(s.Rows), int(s.Columns)) == keep_shape]

    slices.sort(key=_slice_sort_key)

    volume = np.stack([_rescale(s.pixel_array, s) for s in slices], axis=0)

    ref = slices[0]
    px = getattr(ref, "PixelSpacing", [1.0, 1.0])
    row_mm, col_mm = float(px[0]), float(px[1])
    z_mm = _slice_spacing(slices)

    metadata = {
        "patient_id": str(getattr(ref, "PatientID", "") or "anonymous"),
        "study": str(getattr(ref, "StudyDescription", "") or ""),
        "series": str(getattr(ref, "SeriesDescription", "") or ""),
        "modality": str(getattr(ref, "Modality", "") or ""),
        "num_slices": len(slices),
        "shape": tuple(int(x) for x in volume.shape),
    }
    return Volume(data=volume, spacing=(z_mm, row_mm, col_mm), metadata=metadata)


def load_volume(source: str | Path, extra_files: Sequence[str | Path] = ()) -> Volume:
    """Load a :class:`Volume` from a zip archive, a directory, or explicit files.

    Parameters
    ----------
    source:
        Path to a ``.zip`` archive, a directory of DICOM files, or a single
        ``.dcm`` file.
    extra_files:
        Additional individual DICOM file paths to include (used when the caller
        passes several ``.dcm`` files instead of an archive).
    """
    source = Path(source)
    datasets: list[FileDataset] = []

    if source.suffix.lower() == ".zip":
        datasets.extend(_iter_zip_dicoms(source))
    elif source.is_dir():
        datasets.extend(_iter_file_dicoms(sorted(source.rglob("*"))))
    else:
        datasets.extend(_iter_file_dicoms([source]))

    if extra_files:
        datasets.extend(_iter_file_dicoms([Path(p) for p in extra_files]))

    return build_volume(datasets)
