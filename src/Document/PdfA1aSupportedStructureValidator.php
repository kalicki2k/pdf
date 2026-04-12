<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\TaggedPdf\TaggedFigure;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
use Kalle\Pdf\Document\TaggedPdf\TaggedListContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableCell;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;

use function array_keys;
use function count;
use function implode;
use function in_array;
use function sprintf;

final class PdfA1aSupportedStructureValidator
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_TEXT_TAGS = [
        'BlockQuote',
        'Code',
        'H1',
        'H2',
        'H3',
        'H4',
        'H5',
        'H6',
        'P',
        'Quote',
        'Span',
    ];

    public function assertSupported(Document $document): void
    {
        if (!$document->profile->isPdfA1() || $document->profile->pdfaConformance() !== 'A') {
            return;
        }

        foreach ($document->taggedTextBlocks as $index => $textBlock) {
            $this->assertSupportedTextBlock($document, $textBlock, $index);
        }

        foreach ($document->taggedFigures as $index => $figure) {
            $this->assertSupportedFigure($document, $figure, $index);
        }

        foreach ($document->taggedLists as $index => $list) {
            $this->assertSupportedList($document, $list, $index);
        }

        foreach ($document->taggedTables as $index => $table) {
            $this->assertSupportedTable($document, $table, $index);
        }
    }

    private function assertSupportedTextBlock(Document $document, TaggedTextBlock $textBlock, int $index): void
    {
        if (!in_array($textBlock->tag, self::SUPPORTED_TEXT_TAGS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s supports only tagged text blocks with tags [%s]. Tagged text block %d uses unsupported tag "%s".',
                $document->profile->name(),
                implode(', ', self::SUPPORTED_TEXT_TAGS),
                $index + 1,
                $textBlock->tag,
            ));
        }

        $this->assertValidTaggedReference($document, $textBlock->pageIndex, $textBlock->markedContentId, sprintf(
            'tagged text block %d',
            $index + 1,
        ));
    }

    private function assertSupportedFigure(Document $document, TaggedFigure $figure, int $index): void
    {
        $this->assertValidTaggedReference($document, $figure->pageIndex, $figure->markedContentId, sprintf(
            'tagged figure %d',
            $index + 1,
        ));
    }

    private function assertSupportedList(Document $document, TaggedList $list, int $index): void
    {
        if ($list->items === []) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow empty tagged lists. Tagged list %d has no items.',
                $document->profile->name(),
                $index + 1,
            ));
        }

        foreach ($list->items as $itemIndex => $item) {
            $this->assertValidListReference($document, $item->labelReference, sprintf(
                'tagged list %d item %d label',
                $index + 1,
                $itemIndex + 1,
            ));
            $this->assertValidListReference($document, $item->bodyReference, sprintf(
                'tagged list %d item %d body',
                $index + 1,
                $itemIndex + 1,
            ));
        }
    }

    private function assertSupportedTable(Document $document, TaggedTable $table, int $index): void
    {
        if ($table->headerRows === [] && $table->bodyRows === [] && $table->footerRows === []) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow empty tagged tables. Tagged table %d has no rows.',
                $document->profile->name(),
                $index + 1,
            ));
        }

        foreach ($table->captionReferences as $captionIndex => $reference) {
            $this->assertValidTableReference($document, $reference, sprintf(
                'tagged table %d caption reference %d',
                $index + 1,
                $captionIndex + 1,
            ));
        }

        $this->assertSupportedTableRows($document, $table->headerRows, $index, 'header');
        $this->assertSupportedTableRows($document, $table->bodyRows, $index, 'body');
        $this->assertSupportedTableRows($document, $table->footerRows, $index, 'footer');
    }

    /**
     * @param list<TaggedTableRow> $rows
     */
    private function assertSupportedTableRows(Document $document, array $rows, int $tableIndex, string $section): void
    {
        $rowIndexes = [];

        foreach ($rows as $rowPosition => $row) {
            if (isset($rowIndexes[$row->rowIndex])) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow duplicate tagged table row index %d in table %d %s section.',
                    $document->profile->name(),
                    $row->rowIndex,
                    $tableIndex + 1,
                    $section,
                ));
            }

            $rowIndexes[$row->rowIndex] = true;

            if ($row->cells === []) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow empty tagged table rows. Table %d %s row %d has no cells.',
                    $document->profile->name(),
                    $tableIndex + 1,
                    $section,
                    $rowPosition + 1,
                ));
            }

            $this->assertSupportedTableCells($document, $row->cells, $tableIndex, $section, $rowPosition);
        }
    }

    /**
     * @param list<TaggedTableCell> $cells
     */
    private function assertSupportedTableCells(
        Document $document,
        array $cells,
        int $tableIndex,
        string $section,
        int $rowPosition,
    ): void {
        $columnIndexes = [];

        foreach ($cells as $cellPosition => $cell) {
            if (isset($columnIndexes[$cell->columnIndex])) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow duplicate tagged table column index %d in table %d %s row %d.',
                    $document->profile->name(),
                    $cell->columnIndex,
                    $tableIndex + 1,
                    $section,
                    $rowPosition + 1,
                ));
            }

            $columnIndexes[$cell->columnIndex] = true;

            if ($cell->contentReferences === []) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires tagged table cells to reference marked content. Table %d %s row %d cell %d is empty.',
                    $document->profile->name(),
                    $tableIndex + 1,
                    $section,
                    $rowPosition + 1,
                    $cellPosition + 1,
                ));
            }

            if (!$cell->header && $cell->headerScope !== null) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s only allows header scope on header cells. Table %d %s row %d cell %d is not a header cell.',
                    $document->profile->name(),
                    $tableIndex + 1,
                    $section,
                    $rowPosition + 1,
                    $cellPosition + 1,
                ));
            }

            foreach ($cell->contentReferences as $referenceIndex => $reference) {
                $this->assertValidTableReference($document, $reference, sprintf(
                    'tagged table %d %s row %d cell %d content reference %d',
                    $tableIndex + 1,
                    $section,
                    $rowPosition + 1,
                    $cellPosition + 1,
                    $referenceIndex + 1,
                ));
            }
        }
    }

    private function assertValidListReference(Document $document, TaggedListContentReference $reference, string $context): void
    {
        $this->assertValidTaggedReference($document, $reference->pageIndex, $reference->markedContentId, $context);
    }

    private function assertValidTableReference(Document $document, TaggedTableContentReference $reference, string $context): void
    {
        $this->assertValidTaggedReference($document, $reference->pageIndex, $reference->markedContentId, $context);
    }

    private function assertValidTaggedReference(
        Document $document,
        int $pageIndex,
        int $markedContentId,
        string $context,
    ): void {
        if ($pageIndex < 0 || !isset($document->pages[$pageIndex])) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires valid tagged content page references. %s points to missing page index %d.',
                $document->profile->name(),
                ucfirst($context),
                $pageIndex,
            ));
        }

        if ($markedContentId < 0) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires non-negative marked-content ids. %s uses MCID %d.',
                $document->profile->name(),
                ucfirst($context),
                $markedContentId,
            ));
        }
    }
}
