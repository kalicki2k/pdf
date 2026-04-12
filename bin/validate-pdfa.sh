#!/usr/bin/env sh

set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

sh "${SCRIPT_DIR}/validate-verapdf.sh" "$@"
