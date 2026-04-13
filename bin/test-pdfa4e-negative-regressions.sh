#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_DIR}"/pdf-a-4e-invalid-*.pdf

PDF_FILES="$(env UID="$(id -u)" GID="$(id -g)" docker compose run --rm php php /app/bin/generate-pdfa4e-negative-regression-fixtures.php /app/var/pdfa-regression)"

if [ -z "${PDF_FILES}" ]; then
    echo "No negative PDF/A-4e regression fixtures were generated." >&2
    exit 1
fi

printf '%s\n' "${PDF_FILES}" | while IFS= read -r pdf_file; do
    if [ -z "${pdf_file}" ]; then
        continue
    fi

    host_pdf_file="${pdf_file}"

    case "${host_pdf_file}" in
        /app/*)
            host_pdf_file="${PROJECT_ROOT}${host_pdf_file#/app}"
            ;;
    esac

    echo "Expecting veraPDF rejection for ${host_pdf_file#${PROJECT_ROOT}/}"
    sh "${SCRIPT_DIR}/assert-verapdf-invalid.sh" "${host_pdf_file}" </dev/null
done
