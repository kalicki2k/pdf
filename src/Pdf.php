<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuilder;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Signature\DocumentSigner;
use Kalle\Pdf\Document\Signature\OpenSslPemSigningCredentials;
use Kalle\Pdf\Document\Signature\PdfSignatureOptions;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Text\TextMeasurer;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\Output;
use Kalle\Pdf\Writer\StreamOutput;
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
    public static function writeToFile(Document $document, string $path): void
    {
        $scope = $document->debugger->startPerformanceScope('file.write', [
            'path' => $path,
            'page_count' => count($document->pages),
        ]);
        $output = new FileOutput($path);

        try {
            self::render($document, $output);
            $output->close();
            $scope->stop([
                'path' => $path,
                'bytes' => $output->offset(),
            ]);
        } catch (Throwable $throwable) {
            unset($output);

            throw $throwable;
        }
    }

    /**
     * @param resource $stream
     */
    public static function writeToStream(Document $document, $stream): void
    {
        self::render($document, new StreamOutput($stream));
    }

    public static function contents(Document $document): string
    {
        $output = new StringOutput();
        self::render($document, $output);

        return $output->contents();
    }

    public static function renderSigned(
        Document $document,
        Output $output,
        OpenSslPemSigningCredentials $credentials,
        PdfSignatureOptions $options,
    ): void {
        new DocumentSigner()->write($document, $output, $credentials, $options);
    }

    public static function signedContents(
        Document $document,
        OpenSslPemSigningCredentials $credentials,
        PdfSignatureOptions $options,
    ): string {
        return (new DocumentSigner())->contents($document, $credentials, $options);
    }

    public static function measureTextWidth(string $text, float $fontSize, string | StandardFont $font = StandardFont::HELVETICA): float
    {
        return new TextMeasurer()->measureTextWidth($text, $fontSize, $font);
    }
}
