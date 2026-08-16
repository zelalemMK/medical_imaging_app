# DICOM -> 3D model: web UI in a container.
FROM python:3.12-slim

# libgomp1 is the OpenMP runtime some scikit-image routines link against.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgomp1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install dependencies first so this layer is cached across code changes.
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt gunicorn

# Now the application itself.
COPY pyproject.toml README.md ./
COPY dicom3d ./dicom3d
RUN pip install --no-cache-dir --no-deps -e .

# matplotlib needs a writable config dir when running as a non-root user.
ENV MPLCONFIGDIR=/tmp/mpl
RUN useradd --create-home appuser && mkdir -p /tmp/mpl && chown -R appuser /tmp/mpl
USER appuser

EXPOSE 5000

# 2 workers, long timeout: reconstructing a large series can take a while.
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "--workers", "2", \
     "--timeout", "600", "dicom3d.webapp:app"]
