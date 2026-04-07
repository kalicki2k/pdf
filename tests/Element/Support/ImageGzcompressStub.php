<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

function setImageGzcompressFailure(bool $shouldFail): void
{
    $GLOBALS['__pdf_image_gzcompress_failure'] = $shouldFail;
}

function gzcompress(string $data, int $level = -1, int $encoding = ZLIB_ENCODING_DEFLATE): string|false
{
    if (($GLOBALS['__pdf_image_gzcompress_failure'] ?? false) === true) {
        return false;
    }

    return \gzcompress($data, $level, $encoding);
}
