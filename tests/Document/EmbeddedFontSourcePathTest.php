<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontSourcePathTest extends TestCase
{
    public function testItBuildsTextWithAnEmbeddedTrueTypeFontLoadedFromPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-embedded-font-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary font path.');
        }

        file_put_contents($path, TrueTypeFontFixture::minimalTrueTypeFontBytes());

        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath($path),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertSame('TestFont-Regular', $font->name);

        unlink($path);
    }
}
