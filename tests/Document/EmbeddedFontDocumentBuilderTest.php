<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontDocumentBuilderTest extends TestCase
{
    public function testItBuildsTextWithAnEmbeddedTrueTypeFont(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->fontResources);
        $font = current($document->pages[0]->fontResources);

        self::assertNotFalse($font);
        self::assertTrue($font->isEmbedded());
        self::assertSame('TestFont-Regular', $font->name);
        self::assertStringContainsString('/F1 18 Tf', $document->pages[0]->contents);
        self::assertStringContainsString('(A) Tj', $document->pages[0]->contents);
    }
}
