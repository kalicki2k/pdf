<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Document\DocumentBuilder;

/**
 * Entry-point facade for starting PDF document assembly.
 */
class Pdf
{
    /**
     * Creates a new document builder instance.
     */
    public static function document(): DocumentBuilder
    {
        return DocumentBuilder::make();
    }
}
