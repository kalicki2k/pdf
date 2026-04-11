<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderOutputTest extends TestCase
{
    public function testItWritesADocumentToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-document-write-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary path for the writeToFile test.');
        }

        unlink($path);
        $path .= '.pdf';

        DefaultDocumentBuilder::make()
            ->title('Hello')
            ->author('Sebastian Kalicki')
            ->writeToFile($path);

        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        unlink($path);
    }

    public function testItWritesADocumentToAStream(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for the writeToStream test.');
        }

        DefaultDocumentBuilder::make()
            ->title('Hello')
            ->author('Sebastian Kalicki')
            ->writeToStream($stream);

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        fclose($stream);
    }

    public function testItReturnsDocumentContentsAsAString(): void
    {
        $contents = DefaultDocumentBuilder::make()
            ->title('Hello')
            ->author('Sebastian Kalicki')
            ->contents();

        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }
}
