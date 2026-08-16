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


def _series_uid(ds: FileDataset) -> str:
    return str(getattr(ds, "SeriesInstanceUID", "") or "")


def _group_by_series(slices: list[FileDataset]) -> "dict[str, list[FileDataset]]":
    """Group image slices by SeriesInstanceUID.

    A single DICOM export/zip frequently contains several distinct acquisitions
    (e.g. T1, T2, FLAIR, pre/post-contrast). They must never be stacked together
    — even when two series happen to share the same rows/cols — so we separate on
    the SeriesInstanceUID, which uniquely identifies one acquisition.
    """
    from collections import defaultdict

    groups: dict[str, list[FileDataset]] = defaultdict(list)
    for ds in slices:
        groups[_series_uid(ds)].append(ds)
    return dict(groups)


def summarize_series(slices: list[FileDataset]) -> dict:
    """Human-readable summary of one series' slices."""
    from collections import Counter

    ref = slices[0]
    shapes = Counter((int(getattr(s, "Rows", 0)), int(getattr(s, "Columns", 0))) for s in slices)
    (rows, cols), _ = shapes.most_common(1)[0]
    return {
        "series_uid": _series_uid(ref),
        "description": str(getattr(ref, "SeriesDescription", "") or ""),
        "modality": str(getattr(ref, "Modality", "") or ""),
        "num_slices": len(slices),
        "rows": rows,
        "cols": cols,
    }


def list_series(source: "str | Path", extra_files: "Sequence[str | Path]" = ()) -> list[dict]:
    """List the distinct image series found in ``source`` (largest first).

    Useful before :func:`load_volume` to see what's in a multi-series export and
    decide which one to reconstruct.
    """
    slices = [ds for ds in _gather_datasets(source, extra_files) if _is_image_slice(ds)]
    groups = _group_by_series(slices)
    infos = [summarize_series(s) for s in groups.values()]
    infos.sort(key=lambda i: (-i["num_slices"], i["description"]))
    return infos


def _select_series(
    groups: "dict[str, list[FileDataset]]", series: "int | str | None"
) -> tuple[str, list[FileDataset]]:
    """Pick one series from ``groups`` given a selector.

    ``series`` may be:
      * ``None``  -> the largest series by slice count (the usual main scan);
      * an ``int`` -> index into the size-sorted list (0 = largest);
      * a ``str`` -> exact SeriesInstanceUID, else a case-insensitive substring
        match on the SeriesDescription.
    """
    ordered = sorted(
        groups.items(), key=lambda kv: (-len(kv[1]), _series_uid(kv[1][0]))
    )
    if series is None:
        return ordered[0]
    if isinstance(series, int):
        if not (0 <= series < len(ordered)):
            raise ValueError(
                f"Series index {series} out of range (found {len(ordered)} series)."
            )
        return ordered[series]
    key = str(series).strip()
    for uid, sl in ordered:
        if uid == key:
            return uid, sl
    matches = [
        (uid, sl)
        for uid, sl in ordered
        if key.lower() in str(getattr(sl[0], "SeriesDescription", "") or "").lower()
    ]
    if not matches:
        available = ", ".join(
            f"{s['description'] or '(no description)'} [{s['num_slices']}]"
            for s in (summarize_series(sl) for _, sl in ordered)
        )
        raise ValueError(
            f"No series matched {series!r}. Available series: {available}."
        )
    if len(matches) > 1:
        names = ", ".join(
            f"{summarize_series(sl)['description']!r}" for _, sl in matches
        )
        raise ValueError(
            f"Series selector {series!r} is ambiguous, matched: {names}. "
            "Be more specific or pass an index / SeriesInstanceUID."
        )
    return matches[0]


def build_volume(
    datasets: Iterable[FileDataset], series: "int | str | None" = None
) -> Volume:
    """Stack sorted image slices from one series into a :class:`Volume`.

    Parameters
    ----------
    series:
        Which series to reconstruct when the input holds more than one. See
        :func:`_select_series`. Defaults to the largest series.

    Raises
    ------
    ValueError
        If fewer than two image slices with pixel data are found.
    """
    all_slices = [ds for ds in datasets if _is_image_slice(ds)]
    if len(all_slices) < 2:
        raise ValueError(
            f"Need at least 2 DICOM image slices to build a volume, found {len(all_slices)}. "
            "The input may contain only non-image DICOM objects (e.g. reports/dose)."
        )

    # Separate distinct acquisitions and reconstruct just one of them.
    groups = _group_by_series(all_slices)
    all_series = sorted(
        (summarize_series(s) for s in groups.values()),
        key=lambda i: (-i["num_slices"], i["description"]),
    )
    _, slices = _select_series(groups, series)

    if len(slices) < 2:
        raise ValueError(
            f"Selected series has only {len(slices)} slice(s); need at least 2."
        )

    # Within the chosen series, keep the dominant shape so a stray localizer of
    # a different size can't break the stack.
    from collections import Counter

    shape_counts = Counter((int(s.Rows), int(s.Columns)) for s in slices)
    keep_shape, _ = shape_counts.most_common(1)[0]
    slices = [s for s in slices if (int(s.Rows), int(s.Columns)) == keep_shape]

    slices.sort(key=_slice_sort_key)

    # Decode pixel data slice by slice. Compressed transfer syntaxes (JPEG
    # Lossless, JPEG 2000, JPEG-LS, RLE) are decoded here via pydicom's plugin
    # backends; surface a clear, actionable message if a backend is missing.
    planes = []
    for s in slices:
        try:
            planes.append(_rescale(s.pixel_array, s))
        except Exception as exc:
            ts = getattr(getattr(s, "file_meta", None), "TransferSyntaxUID", None)
            ts_name = getattr(ts, "name", str(ts))
            raise ValueError(
                f"Could not decode pixel data (transfer syntax: {ts_name}). "
                "This usually means a decompression backend is missing. Install "
                "the decoders with:  pip install pylibjpeg pylibjpeg-libjpeg "
                "pylibjpeg-openjpeg python-gdcm\n"
                f"Underlying error: {exc}"
            ) from exc
    volume = np.stack(planes, axis=0)

    ref = slices[0]
    px = getattr(ref, "PixelSpacing", [1.0, 1.0])
    row_mm, col_mm = float(px[0]), float(px[1])
    z_mm = _slice_spacing(slices)

    metadata = {
        "patient_id": str(getattr(ref, "PatientID", "") or "anonymous"),
        "study": str(getattr(ref, "StudyDescription", "") or ""),
        "series": str(getattr(ref, "SeriesDescription", "") or ""),
        "series_uid": _series_uid(ref),
        "modality": str(getattr(ref, "Modality", "") or ""),
        "num_slices": len(slices),
        "shape": tuple(int(x) for x in volume.shape),
        "num_series_available": len(all_series),
        "series_available": all_series,
    }
    return Volume(data=volume, spacing=(z_mm, row_mm, col_mm), metadata=metadata)


def _gather_datasets(
    source: "str | Path", extra_files: "Sequence[str | Path]" = ()
) -> list[FileDataset]:
    """Read all DICOM datasets from a zip, directory, or explicit file(s)."""
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
    return datasets


def load_volume(
    source: str | Path,
    extra_files: Sequence[str | Path] = (),
    series: "int | str | None" = None,
) -> Volume:
    """Load a :class:`Volume` from a zip archive, a directory, or explicit files.

    Parameters
    ----------
    source:
        Path to a ``.zip`` archive, a directory of DICOM files, or a single
        ``.dcm`` file.
    extra_files:
        Additional individual DICOM file paths to include (used when the caller
        passes several ``.dcm`` files instead of an archive).
    series:
        Which acquisition to reconstruct when the input holds several. ``None``
        (default) picks the largest by slice count; an ``int`` indexes the
        size-sorted list (0 = largest); a ``str`` matches a SeriesInstanceUID or
        a substring of the SeriesDescription. Use :func:`list_series` to see the
        options.
    """
    return build_volume(_gather_datasets(source, extra_files), series=series)
