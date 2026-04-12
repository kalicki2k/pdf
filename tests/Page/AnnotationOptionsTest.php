<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Page\AnnotationMetadata;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\TextAnnotationOptions;
use PHPUnit\Framework\TestCase;

final class AnnotationOptionsTest extends TestCase
{
    public function testLinkAnnotationOptionsExposeMetadata(): void
    {
        $options = new LinkAnnotationOptions(
            contents: 'Open docs',
            accessibleLabel: 'Read the documentation',
            groupKey: 'docs-link',
        );

        $metadata = $options->metadata();

        self::assertSame('Open docs', $metadata->contents);
        self::assertSame('Read the documentation', $metadata->accessibleLabel);
        self::assertSame('docs-link', $metadata->groupKey);
    }

    public function testTextAnnotationOptionsExposeMetadata(): void
    {
        $options = new TextAnnotationOptions(title: 'QA');

        self::assertSame('QA', $options->metadata()->title);
    }

    public function testHighlightAnnotationOptionsExposeMetadata(): void
    {
        $options = new HighlightAnnotationOptions(
            color: Color::rgb(1, 1, 0),
            contents: 'Marked',
            title: 'QA',
        );

        $metadata = $options->metadata();

        self::assertSame('Marked', $metadata->contents);
        self::assertSame('QA', $metadata->title);
    }

    public function testExplicitMetadataOverridesConvenienceFields(): void
    {
        $metadata = new AnnotationMetadata(
            contents: 'Shared contents',
            title: 'Shared title',
            accessibleLabel: 'Shared label',
            groupKey: 'shared-group',
        );

        $linkOptions = new LinkAnnotationOptions(contents: 'Ignored', metadata: $metadata);
        $textOptions = new TextAnnotationOptions(title: 'Ignored', metadata: $metadata);
        $highlightOptions = new HighlightAnnotationOptions(contents: 'Ignored', title: 'Ignored', metadata: $metadata);

        self::assertSame($metadata, $linkOptions->metadata());
        self::assertSame($metadata, $textOptions->metadata());
        self::assertSame($metadata, $highlightOptions->metadata());
    }

    public function testFreeTextAnnotationOptionsExposeMetadata(): void
    {
        $options = new FreeTextAnnotationOptions(
            textColor: Color::rgb(0, 0, 0.4),
            borderColor: Color::rgb(0.2, 0.2, 0.2),
            fillColor: Color::rgb(1, 1, 0.8),
            metadata: new AnnotationMetadata(title: 'QA'),
        );

        self::assertSame('QA', $options->metadata()->title);
        self::assertSame([0.0, 0.0, 0.4], $options->textColor?->components());
        self::assertSame([0.2, 0.2, 0.2], $options->borderColor?->components());
        self::assertSame([1.0, 1.0, 0.8], $options->fillColor?->components());
    }
}
