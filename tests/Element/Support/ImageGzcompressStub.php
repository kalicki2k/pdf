<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

function setImageGzcompressFailure(bool $shouldFail): void
{
    $GLOBALS['__pdf_image_gzcompress_failure'] = $shouldFail;
}

function gzcompress(string $data, int $level = -1, int $encoding = ZLIB_ENCODING_DEFLATE): string | false
{
    if (($GLOBALS['__pdf_image_gzcompress_failure'] ?? false) === true) {
        return false;
    }

    return \gzcompress($data, $level, $encoding);
}

function deflate_init(int $encoding, array $options = []): object | false
{
    if (($GLOBALS['__pdf_image_gzcompress_failure'] ?? false) === true) {
        return false;
    }

    return \deflate_init($encoding, $options);
}

function deflate_add(object $context, string $data, int $flushMode = ZLIB_SYNC_FLUSH): string | false
{
    if (($GLOBALS['__pdf_image_gzcompress_failure'] ?? false) === true) {
        return false;
    }

    return \deflate_add($context, $data, $flushMode);
}
