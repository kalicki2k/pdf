<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\PageDecorationContext;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableOptions;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Layout\PositionMode;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderPageDecorationTest extends TestCase
{
    public function testItRendersHeaderAndFooterOnExplicitNewPagesInStableOrder(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->top,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 12.0,
                ));
            })
            ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Footer ' . $pageNumber, TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->bottom + 12.0,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 12.0,
                ));
            })
            ->text('Body 1', TextOptions::make(
                left: 80.0,
                bottom: 420.0,
                positionMode: PositionMode::ABSOLUTE,
                fontSize: 12.0,
            ))
            ->newPage(PageOptions::make(pageSize: PageSize::A6()))
            ->text('Body 2', TextOptions::make(
                left: 80.0,
                bottom: 320.0,
                positionMode: PositionMode::ABSOLUTE,
                fontSize: 12.0,
            ))
            ->build();

        self::assertCount(2, $document->pages);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Footer 1'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 2'), $document->pages[1]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Footer 2'), $document->pages[1]->contents);

        $headerPosition = strpos($document->pages[0]->contents, '113.386 595.276 Td');
        $bodyPosition = strpos($document->pages[0]->contents, '80 420 Td');
        $footerPosition = strpos($document->pages[0]->contents, '113.386 125.386 Td');

        self::assertNotFalse($headerPosition);
        self::assertNotFalse($bodyPosition);
        self::assertNotFalse($footerPosition);
        self::assertLessThan($bodyPosition, $headerPosition);
        self::assertLessThan($footerPosition, $bodyPosition);
    }

    public function testItAppliesPageDecorationsToAutomaticOverflowPages(): void
    {
        $rows = [];

        for ($index = 1; $index <= 20; $index++) {
            $rows[] = TableRow::fromTexts('Row ' . $index, str_repeat('Value ', 4));
        }

        $table = Table::define(
            TableColumn::fixed(50.0),
            TableColumn::fixed(90.0),
        )
            ->withOptions(
                (TableOptions::make())
                    ->withPlacement(TablePlacement::absolute(left: 32.0, top: 59.528, width: 140.0))
                    ->withCellPadding(CellPadding::all(4.0))
                    ->withTextOptions(TextOptions::make(fontSize: 12.0, lineHeight: 14.4)),
            )
            ->withRows(...$rows);

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A6())
            ->margin(Margin::all(Units::mm(10)))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->top,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 10.0,
                ));
            })
            ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Footer ' . $pageNumber, TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->bottom + 10.0,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 10.0,
                ));
            })
            ->table($table)
            ->build();

        self::assertGreaterThan(1, count($document->pages));

        foreach ($document->pages as $index => $page) {
            self::assertMatchesRegularExpression('/BT\\n\\/F1 10 Tf\\n(?:28\\.346|56\\.693) [0-9.]+ Td\\n\\[<48> <65> 7 <61> <64> <65> <72> <20> </', $page->contents);
            self::assertMatchesRegularExpression('/BT\\n\\/F1 10 Tf\\n(?:28\\.346|56\\.693) [0-9.]+ Td\\n\\[<46> 21 <6f> <6f> 9 <74> 14 <65> <72> <20> </', $page->contents);
        }
    }

    public function testItExposesTheCurrentPageToDecorationCallbacks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->top,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 12.0,
                ));
            })
            ->build();

        self::assertStringContainsString("BT\n/F1 12 Tf\n48 595.276 Td\n", $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
    }

    public function testItExposesTotalPagesAndSupportsConditionalDecoration(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                if ($page->isFirstPage()) {
                    return;
                }

                $page->text(
                    'Header ' . $page->pageNumber() . '/' . $page->totalPages(),
                    TextOptions::make(
                        left: $page->page()->contentArea()->left,
                        bottom: $page->page()->contentArea()->top,
                        fontSize: 12.0,
                    ),
                );
            })
            ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
                if (!$page->isLastPage()) {
                    return;
                }

                $page->text(
                    'Footer ' . $page->pageNumber() . '/' . $page->totalPages(),
                    TextOptions::make(
                        left: $page->page()->contentArea()->left,
                        bottom: $page->page()->contentArea()->bottom + 12.0,
                        fontSize: 12.0,
                    ),
                );
            })
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->newPage()
            ->text('Page 3')
            ->build();

        self::assertCount(3, $document->pages);
        self::assertDoesNotMatchRegularExpression($this->textRegex('Header 1/3'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 2/3'), $document->pages[1]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 3/3'), $document->pages[2]->contents);
        self::assertDoesNotMatchRegularExpression($this->textRegex('Footer 1/3'), $document->pages[0]->contents);
        self::assertDoesNotMatchRegularExpression($this->textRegex('Footer 2/3'), $document->pages[1]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Footer 3/3'), $document->pages[2]->contents);
    }

    public function testItAddsConveniencePageNumbersToTheFooter(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->pageNumbers(TextOptions::make(
                left: 24.0,
                bottom: 20.0,
                positionMode: PositionMode::RELATIVE,
                fontSize: 10.0,
            ), 'Seite {{page}} von {{pages}}')
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->build();

        self::assertMatchesRegularExpression($this->textRegex('Seite 1 von 2'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Seite 2 von 2'), $document->pages[1]->contents);
    }

    public function testItSupportsPredicateBasedHeadersAndFooters(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->headerOn(
                static fn (PageDecorationContext $page): bool => !$page->isFirstPage(),
                static function (PageDecorationContext $page): void {
                    $page->text('Conditional header', TextOptions::make(
                        left: $page->page()->contentArea()->left,
                        bottom: $page->page()->contentArea()->top,
                        positionMode: PositionMode::RELATIVE,
                        fontSize: 12.0,
                    ));
                },
            )
            ->footerOn(
                static fn (PageDecorationContext $page): bool => $page->pageNumber() % 2 === 0,
                static function (PageDecorationContext $page): void {
                    $page->text('Even footer', TextOptions::make(
                        left: $page->page()->contentArea()->left,
                        bottom: $page->page()->contentArea()->bottom + 12.0,
                        positionMode: PositionMode::RELATIVE,
                        fontSize: 12.0,
                    ));
                },
            )
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->newPage()
            ->text('Page 3')
            ->build();

        self::assertDoesNotMatchRegularExpression($this->textRegex('Conditional header'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Conditional header'), $document->pages[1]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Conditional header'), $document->pages[2]->contents);
        self::assertDoesNotMatchRegularExpression($this->textRegex('Even footer'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Even footer'), $document->pages[1]->contents);
        self::assertDoesNotMatchRegularExpression($this->textRegex('Even footer'), $document->pages[2]->contents);
    }

    public function testItRendersTaggedPageDecorationsAsArtifacts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Decorated document')
            ->language('de-DE')
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, TextOptions::make(
                    left: 24.0,
                    bottom: 800.0,
                    fontSize: 12.0,
                ));
            })
            ->text('Body', TextOptions::make(
                left: 24.0,
                bottom: 760.0,
                fontSize: 12.0,
            ))
            ->build();

        self::assertCount(1, $document->taggedTextBlocks);
        self::assertStringContainsString('/Artifact BMC', $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
    }

    public function testItKeepsLinksAndImagesWorkingAlongsidePageDecorations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Decorated header', TextOptions::make(
                    left: $page->page()->contentArea()->left,
                    bottom: $page->page()->contentArea()->top,
                    positionMode: PositionMode::RELATIVE,
                    fontSize: 10.0,
                ));
            })
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::absolute(left: 40.0, bottom: 340.0, width: 120.0),
            )
            ->text([
                TextSegment::link('Open docs', TextLink::externalUrl('https://example.com/docs')),
            ], TextOptions::make(
                left: 40.0,
                bottom: 300.0,
                fontSize: 12.0,
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->images);
        self::assertCount(1, $document->pages[0]->annotations);
        self::assertMatchesRegularExpression($this->textRegex('Decorated header'), $document->pages[0]->contents);
        self::assertStringContainsString('/Im1 Do', $document->pages[0]->contents);
        self::assertSame('https://example.com/docs', $document->pages[0]->annotations[0]->target->externalUrlValue());
    }

    private function textRegex(string $text): string
    {
        $hex = array_map(
            static fn (string $char): string => '<' . strtolower(bin2hex($char)) . '>',
            str_split($text),
        );

        return '/' . implode('.*', array_map(preg_quote(...), $hex)) . '/s';
    }
}
