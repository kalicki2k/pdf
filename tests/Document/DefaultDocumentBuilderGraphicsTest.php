<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\PageDecorationContext;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Drawing\GraphicsAccessibility;
use Kalle\Pdf\Drawing\Path;
use Kalle\Pdf\Drawing\StrokeStyle;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderGraphicsTest extends TestCase
{
    public function testItRendersALine(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->line(10, 20, 100, 20)
            ->build();

        self::assertStringContainsString(implode("\n", [
            'q',
            '1 w',
            '10 20 m',
            '100 20 l',
            'S',
            'Q',
        ]), $document->pages[0]->contents);
    }

    public function testItRendersAFilledRectangleWithoutStroke(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->rectangle(20, 30, 40, 50, fillColor: Color::rgb(1, 0, 0))
            ->build();

        self::assertStringContainsString(implode("\n", [
            'q',
            '1 0 0 rg',
            '20 30 40 50 re',
            'f',
            'Q',
        ]), $document->pages[0]->contents);
    }

    public function testItRendersAStrokedAndFilledPath(): void
    {
        $path = Path::builder()
            ->moveTo(20, 200)
            ->lineTo(60, 240)
            ->lineTo(100, 200)
            ->close()
            ->build();

        $document = DefaultDocumentBuilder::make()
            ->path($path, new StrokeStyle(1.5, Color::rgb(1, 0, 0)), Color::gray(0.9))
            ->build();

        self::assertStringContainsString(implode("\n", [
            'q',
            '1 0 0 RG',
            '1.5 w',
            '0.9 g',
            '20 200 m',
            '60 240 l',
            '100 200 l',
            'h',
            'B',
            'Q',
        ]), $document->pages[0]->contents);
    }

    public function testItRendersARoundedRectangle(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->roundedRectangle(10, 20, 80, 40, 10, new StrokeStyle(2.0, Color::gray(0.2)))
            ->build();

        self::assertStringContainsString('2 w', $document->pages[0]->contents);
        self::assertStringContainsString('20 60 m', $document->pages[0]->contents);
        self::assertStringContainsString('85.523 60 90 55.523 90 50 c', $document->pages[0]->contents);
        self::assertStringContainsString('h', $document->pages[0]->contents);
    }

    public function testItWrapsGraphicsAsArtifactsForTaggedProfiles(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Tagged graphics')
            ->language('de-DE')
            ->line(10, 20, 100, 20, new StrokeStyle(2.0, Color::rgb(0, 0, 1)))
            ->build();

        self::assertStringContainsString('/Artifact BMC', $document->pages[0]->contents);
        self::assertStringContainsString('0 0 1 RG', $document->pages[0]->contents);
    }

    public function testItCanRenderSemanticGraphicsAsTaggedFigures(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Tagged graphics')
            ->language('de-DE')
            ->line(
                10,
                20,
                100,
                20,
                new StrokeStyle(2.0, Color::rgb(0, 0, 1)),
                GraphicsAccessibility::alternativeText('Blue separator line'),
            )
            ->build();

        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', $document->pages[0]->contents);
        self::assertCount(1, $document->taggedFigures);
        self::assertSame('Blue separator line', $document->taggedFigures[0]->altText);
    }

    public function testItExposesGraphicsMethodsInsidePageDecorations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->header(static function (PageDecorationContext $page): void {
                $page->line(24, 800, 120, 800, new StrokeStyle(0.5, Color::gray(0.4)));
            })
            ->build();

        self::assertStringContainsString(implode("\n", [
            'q',
            '0.4 G',
            '0.5 w',
            '24 800 m',
            '120 800 l',
            'S',
            'Q',
        ]), $document->pages[0]->contents);
    }
}
