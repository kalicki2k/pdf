#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"
OUTPUT_LOG="${OUTPUT_DIR}/pdf-a-1a-radio-group.log"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_LOG}"

set +e
env UID="$(id -u)" GID="$(id -g)" docker compose run --rm php php /app/bin/generate-pdfa1a-radio-regression.php /app/var/pdfa-regression/pdf-a-1a-radio-group.pdf >"${OUTPUT_LOG}" 2>&1
STATUS=$?
set -e

if [ "${STATUS}" -eq 0 ]; then
    echo "Expected PDF/A-1a radio regression generator to fail, but it succeeded." >&2
    cat "${OUTPUT_LOG}" >&2
    exit 1
fi

EXPECTED='Profile PDF/A-1a currently only allows text and choice fields in the PDF/A-1a form implementation.'

if ! grep -F "${EXPECTED}" "${OUTPUT_LOG}" >/dev/null 2>&1; then
    echo "Expected radio regression failure message was not found." >&2
    cat "${OUTPUT_LOG}" >&2
    exit 1
fi

echo "PASS pdf-a-1a-radio-group rejection"
