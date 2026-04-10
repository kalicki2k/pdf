<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document;

use Random\RandomException;

function setDocumentRandomBytesShouldThrow(bool $shouldThrow): void
{
    $GLOBALS['kalle_pdf_tests_document_random_bytes_should_throw'] = $shouldThrow;
}

/**
 * @throws RandomException
 */
function random_bytes(int $length): string
{
    if (($GLOBALS['kalle_pdf_tests_document_random_bytes_should_throw'] ?? false) === true) {
        throw new RandomException('stubbed random_bytes failure');
    }

    return \random_bytes($length);
}
