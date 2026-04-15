<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use InvalidArgumentException;
use Kalle\Pdf\Font\EmbeddedFontSource;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontSourceTest extends TestCase
{
    public function testItCreatesASourceFromStringData(): void
    {
        $source = EmbeddedFontSource::fromString('font-bytes', 'MemoryFont.ttf');

        self::assertSame('font-bytes', $source->data);
        self::assertSame('MemoryFont.ttf', $source->path);
    }

    public function testItCreatesASourceFromAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-font-source-');

        if ($path === false) {
            self::fail('Unable to create a temporary font source path.');
        }

        file_put_contents($path, 'font-bytes');

        $source = EmbeddedFontSource::fromPath($path);

        self::assertSame('font-bytes', $source->data);
        self::assertSame($path, $source->path);

        unlink($path);
    }

    public function testItRejectsEmptyStringData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedded font source data must not be empty.');

        EmbeddedFontSource::fromString('');
    }

    public function testItRejectsMissingPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmbeddedFontSource::fromPath('/definitely/missing/font.ttf');
    }
}
