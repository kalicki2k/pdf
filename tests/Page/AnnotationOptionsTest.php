<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Page\AnnotationBorderStyle;
use Kalle\Pdf\Page\AnnotationBorderStyleType;
use Kalle\Pdf\Page\AnnotationMetadata;
use Kalle\Pdf\Page\CaretAnnotationOptions;
use Kalle\Pdf\Page\FileAttachmentAnnotationOptions;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LineAnnotationOptions;
use Kalle\Pdf\Page\LineEndingStyle;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\PolygonAnnotationOptions;
use Kalle\Pdf\Page\PolyLineAnnotationOptions;
use Kalle\Pdf\Page\ShapeAnnotationOptions;
use Kalle\Pdf\Page\StampAnnotationOptions;
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

    public function testAnnotationMetadataKeepsOptionalSubject(): void
    {
        $metadata = new AnnotationMetadata(subject: 'Area note');

        self::assertSame('Area note', $metadata->subject);
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

    public function testAdditionalAnnotationOptionsExposeMetadataAndStyles(): void
    {
        $shapeOptions = new ShapeAnnotationOptions(
            borderColor: Color::rgb(1, 0, 0),
            fillColor: Color::rgb(1, 1, 0.8),
            borderStyle: AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]),
            subject: 'Shape',
        );
        $lineOptions = new LineAnnotationOptions(
            color: Color::rgb(0, 0, 1),
            startStyle: LineEndingStyle::CIRCLE,
            endStyle: LineEndingStyle::CLOSED_ARROW,
            borderStyle: new AnnotationBorderStyle(2.0, AnnotationBorderStyleType::SOLID),
            subject: 'Line',
        );
        $polyLineOptions = new PolyLineAnnotationOptions(subject: 'Polyline');
        $polygonOptions = new PolygonAnnotationOptions(subject: 'Polygon');
        $stampOptions = new StampAnnotationOptions(icon: 'Approved', contents: 'Stamped');
        $caretOptions = new CaretAnnotationOptions(symbol: 'P', contents: 'Insert here');

        self::assertSame('Shape', $shapeOptions->metadata()->subject);
        self::assertSame('Line', $lineOptions->metadata()->subject);
        self::assertSame(LineEndingStyle::CIRCLE, $lineOptions->startStyle);
        self::assertSame(LineEndingStyle::CLOSED_ARROW, $lineOptions->endStyle);
        self::assertSame('Polyline', $polyLineOptions->metadata()->subject);
        self::assertSame('Polygon', $polygonOptions->metadata()->subject);
        self::assertSame('Approved', $stampOptions->icon);
        self::assertSame('Stamped', $stampOptions->metadata()->contents);
        self::assertSame('P', $caretOptions->symbol);
        self::assertSame('Insert here', $caretOptions->metadata()->contents);
    }

    public function testFileAttachmentAnnotationOptionsExposeDescriptionAndMetadata(): void
    {
        $options = new FileAttachmentAnnotationOptions(
            description: 'Demo attachment',
            icon: 'Graph',
            contents: 'Anhang',
        );

        self::assertSame('Demo attachment', $options->description);
        self::assertSame('Graph', $options->icon);
        self::assertSame('Anhang', $options->metadata()->contents);
    }
}
