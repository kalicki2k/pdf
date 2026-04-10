<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Support;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\Page\Serialization\PageObjectRenderer;
use Kalle\Pdf\Render\PdfRenderer;
use Kalle\Pdf\Render\PdfSerializationPlan;
use Kalle\Pdf\Render\StreamPdfOutput;
use PHPUnit\Framework\Assert;

function writeDocumentToString(Document $document): string
{
    return capturePdfOutput(static function ($stream) use ($document): void {
        $document->writeToStream($stream);
    });
}

function writePlanToString(PdfRenderer $renderer, PdfSerializationPlan $plan): string
{
    return capturePdfOutput(static function ($stream) use ($renderer, $plan): void {
        $renderer->write($plan, new StreamPdfOutput($stream));
    });
}

function writeContentInstructionToString(ContentInstruction $instruction): string
{
    return capturePdfOutput(static function ($stream) use ($instruction): void {
        $instruction->write(new StreamPdfOutput($stream));
    });
}

function writePageObjectToString(PageObjectRenderer $renderer, bool $hasMarkedContent): string
{
    return capturePdfOutput(static function ($stream) use ($renderer, $hasMarkedContent): void {
        $renderer->write(new StreamPdfOutput($stream), $hasMarkedContent);
    });
}

/**
 * @param callable(resource): void $writer
 */
function capturePdfOutput(callable $writer): string
{
    $stream = fopen('php://temp', 'w+b');

    Assert::assertNotFalse($stream);

    $writtenOutput = false;

    try {
        $writer($stream);

        rewind($stream);
        $writtenOutput = stream_get_contents($stream);
    } finally {
        fclose($stream);
    }

    Assert::assertNotFalse($writtenOutput);

    return $writtenOutput;
}
