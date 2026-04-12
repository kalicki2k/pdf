<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TaggedPdf\TaggedFigure;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
use Kalle\Pdf\Document\TaggedPdf\TaggedListContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedListItem;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableCell;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

use function dirname;

final class PdfA1aSupportedStructureValidatorTest extends TestCase
{
    public function testItRejectsUnsupportedPdfA1aTaggedTextTags(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Span candidate Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('Div', 0, 0),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('supports only tagged text blocks with tags [BlockQuote, Code, H1, H2, H3, H4, H5, H6, P, Quote, Span]');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsEmptyPdfA1aTaggedLists(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, 0),
            ],
            taggedLists: [
                new TaggedList(0, []),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not allow empty tagged lists');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsEmptyPdfA1aTaggedTables(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, 0),
            ],
            taggedTables: [
                new TaggedTable(0),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not allow empty tagged tables');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA1aTaggedTableCellsWithoutContentReferences(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, 0),
            ],
            taggedTables: [
                new TaggedTable(
                    0,
                    bodyRows: [
                        new TaggedTableRow(0, [
                            new TaggedTableCell(0, false, contentReferences: []),
                        ]),
                    ],
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires tagged table cells to reference marked content');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA1aTaggedTableScopesOnNonHeaderCells(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, 0),
            ],
            taggedTables: [
                new TaggedTable(
                    0,
                    bodyRows: [
                        new TaggedTableRow(0, [
                            new TaggedTableCell(
                                0,
                                false,
                                TableHeaderScope::COLUMN,
                                contentReferences: [new TaggedTableContentReference(0, 1)],
                            ),
                        ]),
                    ],
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only allows header scope on header cells');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA1aTaggedReferencesToMissingPages(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 1, 0),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('points to missing page index 1');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA1aNegativeMarkedContentIds(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, -1),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires non-negative marked-content ids');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA1aTaggedFigureReferencesToMissingPages(): void
    {
        $document = new Document(
            profile: Profile::pdfA1a(),
            title: 'Archive Copy',
            language: 'de-DE',
            pages: [$this->textPage('Paragraph Привет')],
            taggedFigures: [
                new TaggedFigure(1, 0, 'Divider'),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tagged figure 1 points to missing page index 1');

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    private function textPage(string $text): Page
    {
        $document = \Kalle\Pdf\Document\DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->paragraph($text, new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        return $document->pages[0];
    }
}
