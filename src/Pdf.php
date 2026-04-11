<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Text\TextMeasurer;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\Output;
use Kalle\Pdf\Writer\StringOutput;
use Throwable;

final readonly class Pdf
{
    private function __construct()
    {
    }

    public static function document(): DocumentBuilder
    {
        return DefaultDocumentBuilder::make();
    }

    public static function render(Document $document, Output $output): void
    {
        new DocumentRenderer()->write($document, $output);
    }

    /**
     * @throws Throwable
     */
    public static function save(Document $document, string $path): string
    {
        $output = new FileOutput($path);

        try {
            self::render($document, $output);
            $output->close();
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }

        return $path;
    }

    public static function contents(Document $document): string
    {
        $output = new StringOutput();
        self::render($document, $output);

        return $output->contents();
    }

    public static function measureTextWidth(string $text, float $fontSize, string|StandardFont $font = StandardFont::HELVETICA): float
    {
        return (new TextMeasurer())->measureTextWidth($text, $fontSize, $font);
    }
}
