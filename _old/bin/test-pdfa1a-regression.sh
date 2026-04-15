#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"
OUTPUT_PDF="${OUTPUT_DIR}/pdf-a-1a-text-structure.pdf"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_PDF}"

env UID="$(id -u)" GID="$(id -g)" docker compose run --rm php php /app/bin/generate-pdfa1a-regression.php /app/var/pdfa-regression/pdf-a-1a-text-structure.pdf >/dev/null

echo "Validating ${OUTPUT_PDF#${PROJECT_ROOT}/}"
sh "${SCRIPT_DIR}/validate-pdfa.sh" "${OUTPUT_PDF}"
