#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfua-regression"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_DIR}"/*.pdf

mapfile -t PDF_FILES < <(php "${SCRIPT_DIR}/generate-pdfua-regression-fixtures.php" "${OUTPUT_DIR}")

if [ "${#PDF_FILES[@]}" -eq 0 ]; then
    echo "No PDF/UA regression fixtures were generated." >&2
    exit 1
fi

for pdf_file in "${PDF_FILES[@]}"; do
    relative_path="${pdf_file#${PROJECT_ROOT}/}"
    echo "Validating ${relative_path}"
    bash "${SCRIPT_DIR}/validate-pdfua.sh" "${pdf_file}"
done
