#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"
OUTPUT_PDF="${OUTPUT_DIR}/pdf-a-1b-minimal.pdf"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_PDF}"

php "${SCRIPT_DIR}/generate-pdfa1b-regression.php" "${OUTPUT_PDF}" >/dev/null

echo "Validating ${OUTPUT_PDF#${PROJECT_ROOT}/}"
bash "${SCRIPT_DIR}/validate-pdfa.sh" "${OUTPUT_PDF}"
