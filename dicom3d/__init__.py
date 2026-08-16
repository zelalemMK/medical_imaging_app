"""dicom3d — turn a DICOM series (zip or loose .dcm files) into a 3D model.

Public API
----------
>>> from dicom3d import load_volume, generate_mesh, export_mesh
>>> vol = load_volume("scan.zip")
>>> mesh = generate_mesh(vol, iso="bone")
>>> export_mesh(mesh, "out/", formats=("stl", "glb"))
"""

from .loader import Volume, build_volume, load_volume
from .mesh import (
    HU_PRESETS,
    SUPPORTED_FORMATS,
    Mesh,
    export_mesh,
    generate_mesh,
)
from .render import export_nifti, preview_png

__all__ = [
    "Volume",
    "Mesh",
    "load_volume",
    "build_volume",
    "generate_mesh",
    "export_mesh",
    "preview_png",
    "export_nifti",
    "HU_PRESETS",
    "SUPPORTED_FORMATS",
]

__version__ = "0.1.0"
