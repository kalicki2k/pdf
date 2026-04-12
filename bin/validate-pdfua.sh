#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ "$#" -lt 1 ]; then
    echo "Usage: bin/validate-pdfua.sh <pdf-file> [additional veraPDF args]" >&2
    exit 1
fi

PDF_PATH="$1"
shift

bash "${SCRIPT_DIR}/validate-verapdf.sh" "${PDF_PATH}" --defaultflavour ua1 --flavour ua1 "$@"
