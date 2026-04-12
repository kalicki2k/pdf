#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_ROOT}/var/encryption-regression"

run_compose() {
    env UID="$(id -u)" GID="$(id -g)" docker compose "$@"
}

assert_contains() {
    local haystack="$1"
    local needle="$2"

    if [[ "${haystack}" != *"${needle}"* ]]; then
        echo "Expected qpdf output to contain: ${needle}" >&2
        exit 1
    fi
}

show_encryption() {
    local pdf_path="$1"

    run_compose run --rm -T qpdf qpdf --show-encryption --password=user "/app/${pdf_path#${PROJECT_ROOT}/}"
}

rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}"

run_compose run --rm -T php php /app/bin/generate-encryption-permissions-regression.php /app/var/encryption-regression >/dev/null

readonly_output="$(show_encryption "${OUTPUT_DIR}/aes128-readonly.pdf")"
selected_aes128_output="$(show_encryption "${OUTPUT_DIR}/aes128-selected.pdf")"
selected_aes256_output="$(show_encryption "${OUTPUT_DIR}/aes256-selected.pdf")"

assert_contains "${readonly_output}" "P = -64"
assert_contains "${readonly_output}" "print high resolution: not allowed"
assert_contains "${readonly_output}" "extract for any purpose: not allowed"
assert_contains "${readonly_output}" "modify anything: not allowed"
assert_contains "${readonly_output}" "file encryption method: AESv2"

assert_contains "${selected_aes128_output}" "P = -24"
assert_contains "${selected_aes128_output}" "modify annotations: allowed"
assert_contains "${selected_aes128_output}" "modify anything: allowed"
assert_contains "${selected_aes128_output}" "extract for any purpose: not allowed"
assert_contains "${selected_aes128_output}" "file encryption method: AESv2"

assert_contains "${selected_aes256_output}" "P = -24"
assert_contains "${selected_aes256_output}" "modify annotations: allowed"
assert_contains "${selected_aes256_output}" "modify anything: allowed"
assert_contains "${selected_aes256_output}" "extract for any purpose: not allowed"
assert_contains "${selected_aes256_output}" "file encryption method: AESv3"

echo "Validated encryption permissions with qpdf:"
echo "  var/encryption-regression/aes128-readonly.pdf"
echo "  var/encryption-regression/aes128-selected.pdf"
echo "  var/encryption-regression/aes256-selected.pdf"
