#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ "$#" -lt 1 ]; then
    echo "Usage: bin/assert-verapdf-invalid.sh <pdf-file> [additional veraPDF args]" >&2
    exit 1
fi

PDF_PATH="$1"
shift

if [ ! -f "${PDF_PATH}" ]; then
    echo "PDF file not found: ${PDF_PATH}" >&2
    exit 1
fi

ABSOLUTE_PDF_PATH="$(cd "$(dirname "${PDF_PATH}")" && pwd)/$(basename "${PDF_PATH}")"

case "${ABSOLUTE_PDF_PATH}" in
    "${PROJECT_ROOT}"/*) ;;
    *)
        echo "PDF file must be inside the repository: ${PDF_PATH}" >&2
        exit 1
        ;;
esac

CONTAINER_PDF_PATH="/app${ABSOLUTE_PDF_PATH#${PROJECT_ROOT}}"

OUTPUT="$(env UID="$(id -u)" GID="$(id -g)" docker compose run --rm verapdf --format xml "${CONTAINER_PDF_PATH}" "$@" 2>&1 || true)"

printf '%s\n' "${OUTPUT}"

if printf '%s' "${OUTPUT}" | grep -q 'isCompliant="false"'; then
    exit 0
fi

if printf '%s' "${OUTPUT}" | grep -q '<validationReports '; then
    echo "veraPDF did not report the file as non-compliant: ${PDF_PATH}" >&2
    exit 1
fi

echo "veraPDF output could not be interpreted for ${PDF_PATH}" >&2
exit 1
