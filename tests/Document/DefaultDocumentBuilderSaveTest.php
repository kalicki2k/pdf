<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderSaveTest extends TestCase
{
    public function testItSavesADocumentToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-document-save-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary path for the save test.');
        }

        unlink($path);
        $path .= '.pdf';

        $savedPath = DefaultDocumentBuilder::make()
            ->title('Hello')
            ->author('Sebastian Kalicki')
            ->save($path);

        self::assertSame($path, $savedPath);
        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('%PDF-1.4', $contents);
        self::assertStringContainsString('%%EOF', $contents);

        unlink($path);
    }
}
