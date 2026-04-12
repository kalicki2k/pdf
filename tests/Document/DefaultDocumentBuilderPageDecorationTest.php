<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\PageDecorationContext;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Layout\Table\CellPadding;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderPageDecorationTest extends TestCase
{
    public function testItRendersHeaderAndFooterOnExplicitNewPagesInStableOrder(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, new TextOptions(
                    x: $page->page()->contentArea()->left,
                    y: $page->page()->contentArea()->top,
                    fontSize: 12.0,
                ));
            })
            ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Footer ' . $pageNumber, new TextOptions(
                    x: $page->page()->contentArea()->left,
                    y: $page->page()->contentArea()->bottom + 12.0,
                    fontSize: 12.0,
                ));
            })
            ->text('Body 1', new TextOptions(x: 80.0, y: 420.0, fontSize: 12.0))
            ->newPage(new PageOptions(pageSize: PageSize::A6()))
            ->text('Body 2', new TextOptions(x: 80.0, y: 320.0, fontSize: 12.0))
            ->build();

        self::assertCount(2, $document->pages);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Footer 1'), $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 2'), $document->pages[1]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Footer 2'), $document->pages[1]->contents);

        $headerPosition = strpos($document->pages[0]->contents, '56.693 538.583 Td');
        $bodyPosition = strpos($document->pages[0]->contents, '80 420 Td');
        $footerPosition = strpos($document->pages[0]->contents, '56.693 68.693 Td');

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
            ->withPlacement(TablePlacement::at(32.0, 360.0, 140.0))
            ->withCellPadding(CellPadding::all(4.0))
            ->withTextOptions(new TextOptions(fontSize: 12.0, lineHeight: 14.4))
            ->withRows(...$rows);

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A6())
            ->margin(Margin::all(Units::mm(10)))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, new TextOptions(
                    x: $page->page()->contentArea()->left,
                    y: $page->page()->contentArea()->top,
                    fontSize: 10.0,
                ));
            })
            ->footer(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Footer ' . $pageNumber, new TextOptions(
                    x: $page->page()->contentArea()->left,
                    y: $page->page()->contentArea()->bottom + 10.0,
                    fontSize: 10.0,
                ));
            })
            ->table($table)
            ->build();

        self::assertGreaterThan(1, count($document->pages));

        foreach ($document->pages as $index => $page) {
            self::assertStringContainsString("BT\n/F1 10 Tf\n28.346 391.181 Td\n", $page->contents);
            self::assertStringContainsString("BT\n/F1 10 Tf\n28.346 38.346 Td\n", $page->contents);
        }
    }

    public function testItExposesTheCurrentPageToDecorationCallbacks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, new TextOptions(
                    x: $page->page()->contentArea()->left,
                    y: $page->page()->contentArea()->top,
                    fontSize: 12.0,
                ));
            })
            ->build();

        self::assertStringContainsString("BT\n/F1 12 Tf\n24 571.276 Td\n", $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
    }

    public function testItRendersTaggedPageDecorationsAsArtifacts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Decorated document')
            ->language('de-DE')
            ->header(static function (PageDecorationContext $page, int $pageNumber): void {
                $page->text('Header ' . $pageNumber, new TextOptions(
                    x: 24.0,
                    y: 800.0,
                    fontSize: 12.0,
                ));
            })
            ->text('Body', new TextOptions(
                x: 24.0,
                y: 760.0,
                fontSize: 12.0,
            ))
            ->build();

        self::assertCount(1, $document->taggedTextBlocks);
        self::assertStringContainsString('/Artifact BMC', $document->pages[0]->contents);
        self::assertMatchesRegularExpression($this->textRegex('Header 1'), $document->pages[0]->contents);
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
