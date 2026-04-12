#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"
OUTPUT_PDF="${OUTPUT_DIR}/pdf-a-2u-minimal.pdf"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_PDF}"

php "${SCRIPT_DIR}/generate-pdfa2u-regression.php" "${OUTPUT_PDF}" >/dev/null

echo "Validating ${OUTPUT_PDF#${PROJECT_ROOT}/}"
sh "${SCRIPT_DIR}/validate-pdfa.sh" "${OUTPUT_PDF}"
