<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderTest extends TestCase
{
    public function testItBuildsADocumentFromConfiguredMetadata(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->subject('Example Subject')
            ->language('de-DE')
            ->creator('Kalle PDF')
            ->creatorTool('pdf2 test suite')
            ->pageSize(PageSize::A5())
            ->text('Hello (PDF) \\ Test', new TextOptions(
                x: Units::mm(20),
                y: Units::mm(250),
                fontSize: 14,
                fontName: 'Times-Roman',
            ))
            ->build();

        self::assertSame(Version::V1_4, $document->version());
        self::assertSame('Example Title', $document->title);
        self::assertSame('Sebastian Kalicki', $document->author);
        self::assertSame('Example Subject', $document->subject);
        self::assertSame('de-DE', $document->language);
        self::assertSame('Kalle PDF', $document->creator);
        self::assertSame('pdf2 test suite', $document->creatorTool);
        self::assertCount(1, $document->pages);
        self::assertSame(PageSize::A5()->width(), $document->pages[0]->size->width());
        self::assertSame(PageSize::A5()->height(), $document->pages[0]->size->height());
        self::assertStringContainsString("BT\n/F1 14 Tf\n56.693 708.661 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString('] TJ' . "\nET", $document->pages[0]->contents);
        self::assertEquals(
            ['F1' => new PageFont('Times-Roman', StandardFontEncoding::WIN_ANSI)],
            $document->pages[0]->fontResources,
        );
    }

    public function testItBuildsMultiplePagesWhenNewPageIsUsed(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage(new PageOptions(
                pageSize: PageSize::A5(),
                orientation: PageOrientation::LANDSCAPE,
                margin: Margin::all(24.0),
                backgroundColor: Color::hex('#f5f5f5'),
                label: 'appendix',
                name: 'appendix-a',
            ))
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertStringContainsString('[<50>', $document->pages[0]->contents);
        self::assertStringContainsString('[<50>', $document->pages[1]->contents);
        self::assertStringContainsString('] TJ', $document->pages[0]->contents);
        self::assertStringContainsString('] TJ', $document->pages[1]->contents);
        self::assertSame(PageSize::A5()->landscape()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->landscape()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
        self::assertSame(ColorSpace::RGB, $document->pages[1]->backgroundColor?->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $document->pages[1]->backgroundColor?->components());
        self::assertSame('appendix', $document->pages[1]->label);
        self::assertSame('appendix-a', $document->pages[1]->name);
    }

    public function testNewPageWithoutOptionsKeepsDocumentPageDefaults(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(PageSize::A5()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
    }

    public function testNewPageOptionsOverrideOnlyExplicitFieldsOnTopOfDefaults(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->text('Page 1')
            ->newPage(new PageOptions(
                orientation: PageOrientation::LANDSCAPE,
            ))
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(PageSize::A5()->landscape()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->landscape()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
    }

    public function testItBuildsADocumentWithAnExplicitProfile(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::standard(Version::V1_7))
            ->build();

        self::assertSame(Version::V1_7, $document->version());
        self::assertSame(Version::V1_7, $document->profile->version());
    }
}
