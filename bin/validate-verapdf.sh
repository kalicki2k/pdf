#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
IMAGE="verapdf/cli:v1.28.2"

if [ "$#" -lt 1 ]; then
    echo "Usage: bin/validate-verapdf.sh <pdf-file> [additional veraPDF args]" >&2
    exit 1
fi

PDF_PATH="$1"
shift

if [ ! -f "${PDF_PATH}" ]; then
    echo "PDF file not found: ${PDF_PATH}" >&2
    exit 1
fi

ABSOLUTE_PDF_PATH="$(cd "$(dirname "${PDF_PATH}")" && pwd)/$(basename "${PDF_PATH}")"
CONTAINER_PDF_PATH="/data${ABSOLUTE_PDF_PATH#${PROJECT_ROOT}}"

docker run --rm \
    -v "${PROJECT_ROOT}:/data" \
    "${IMAGE}" \
    --format text \
    --verbose \
    "${CONTAINER_PDF_PATH}" \
    "$@"
