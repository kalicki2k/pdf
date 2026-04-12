#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/pdfa-regression"

mkdir -p "${OUTPUT_DIR}"
rm -f "${OUTPUT_DIR}"/pdf-a-3a-*.pdf

PDF_FILES="$(env UID="$(id -u)" GID="$(id -g)" docker compose run --rm php php /app/bin/generate-pdfa3a-regression-fixtures.php /app/var/pdfa-regression)"

if [ -z "${PDF_FILES}" ]; then
    echo "No PDF/A-3a regression fixtures were generated." >&2
    exit 1
fi

OLD_IFS="${IFS}"
IFS='
'

for pdf_file in ${PDF_FILES}; do
    case "${pdf_file}" in
        /app/*)
            pdf_file="${PROJECT_ROOT}${pdf_file#/app}"
            ;;
    esac

    relative_path="${pdf_file#${PROJECT_ROOT}/}"
    echo "Validating ${relative_path}"
    sh "${SCRIPT_DIR}/validate-pdfa.sh" "${pdf_file}"
done

IFS="${OLD_IFS}"
