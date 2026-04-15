<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_merge;
use function array_slice;
use function count;
use function floor;
use function max;
use function preg_split;
use function rtrim;
use function str_repeat;

use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonChoice;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsEntry;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TaggedPdf\TaggedFigure;
use Kalle\Pdf\Document\TaggedPdf\TaggedList;
use Kalle\Pdf\Document\TaggedPdf\TaggedListContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedListItem;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableCell;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\NamedDestination;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

/**
 * @internal Builds TOC pages and injects them into the prepared document.
 */
final readonly class DocumentTableOfContentsBuilder
{
    private const string TOC_DESTINATION_PREFIX = '__pdf2_toc_entry_';
    private const float ENTRY_INDENT = 14.0;

    /**
     * @param list<TableOfContentsEntry> $explicitEntries
     */
    public function build(
        Document $document,
        TableOfContentsOptions $options,
        array $explicitEntries = [],
    ): Document {
        $entries = $this->resolveEntries($document, $explicitEntries);

        if ($entries === []) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_ENTRIES_REQUIRED,
                'Table of contents requires at least one outline or explicit table of contents entry.',
            );
        }

        $pageCount = count($document->pages);
        $insertionIndex = $options->placement->insertionIndex($pageCount);
        $pageSize = $options->pageSize ?? $document->pages[0]->size;
        $layout = $this->layout($options, $pageSize);
        $tocPageCount = $this->countTocPages($entries, $pageSize->height(), $layout, $options);
        $resolvedEntries = [];

        foreach ($entries as $index => $entry) {
            $resolvedEntries[] = [
                'entry' => $entry,
                'destination' => self::TOC_DESTINATION_PREFIX . ($index + 1),
                'displayPageNumber' => $this->shiftedPageNumber($entry->pageNumber, $tocPageCount, $insertionIndex),
            ];
        }

        $shiftedPages = [];

        foreach ($document->pages as $pageIndex => $page) {
            $originalPageNumber = $pageIndex + 1;
            $extraNamedDestinations = [];

            foreach ($resolvedEntries as $resolvedEntry) {
                /** @var TableOfContentsEntry $entry */
                $entry = $resolvedEntry['entry'];

                if ($entry->pageNumber !== $originalPageNumber) {
                    continue;
                }

                $extraNamedDestinations[] = $entry->hasPosition()
                    ? NamedDestination::position($resolvedEntry['destination'], $entry->x ?? 0.0, $entry->y ?? 0.0)
                    : NamedDestination::fit($resolvedEntry['destination']);
            }

            $shiftedPages[] = $this->clonePage(
                $page,
                $this->shiftPageAnnotations($page->annotations, $tocPageCount, $insertionIndex),
                $extraNamedDestinations,
            );
        }

        $tocPages = $this->buildTocPages($resolvedEntries, $pageSize, $layout, $options);
        $finalPages = [
            ...array_slice($shiftedPages, 0, $insertionIndex),
            ...$tocPages,
            ...array_slice($shiftedPages, $insertionIndex),
        ];

        return new Document(
            profile: $document->profile,
            pages: $finalPages,
            title: $document->title,
            author: $document->author,
            subject: $document->subject,
            keywords: $document->keywords,
            language: $document->language,
            creator: $document->creator,
            creatorTool: $document->creatorTool,
            pdfaOutputIntent: $document->pdfaOutputIntent,
            encryption: $document->encryption,
            taggedFigures: $this->shiftTaggedFigures($document->taggedFigures, $tocPageCount, $insertionIndex),
            taggedTables: $this->shiftTaggedTables($document->taggedTables, $tocPageCount, $insertionIndex),
            taggedTextBlocks: $this->shiftTaggedTextBlocks($document->taggedTextBlocks, $tocPageCount, $insertionIndex),
            attachments: $document->attachments,
            outlines: $this->shiftOutlines($document->outlines, $tocPageCount, $insertionIndex),
            acroForm: $this->shiftAcroForm($document->acroForm, $tocPageCount, $insertionIndex),
            taggedLists: $this->shiftTaggedLists($document->taggedLists, $tocPageCount, $insertionIndex),
            taggedStructureElements: $document->taggedStructureElements,
            taggedDocumentChildKeys: $document->taggedDocumentChildKeys,
            debugger: $document->debugger,
        );
    }

    /**
     * @param list<TableOfContentsEntry> $explicitEntries
     * @return list<TableOfContentsEntry>
     */
    private function resolveEntries(Document $document, array $explicitEntries): array
    {
        if ($explicitEntries !== []) {
            return $explicitEntries;
        }

        $entries = [];

        foreach ($document->outlines as $outline) {
            $entries[] = $outline->destination->hasExplicitPosition()
                ? TableOfContentsEntry::position(
                    $outline->title,
                    $outline->pageNumber,
                    $outline->destination->x ?? 0.0,
                    $outline->destination->y ?? 0.0,
                    $outline->level,
                )
                : TableOfContentsEntry::page($outline->title, $outline->pageNumber, $outline->level);
        }

        return $entries;
    }

    /**
     * @return array{
     *   left: float,
     *   right: float,
     *   top: float,
     *   bottom: float,
     *   contentWidth: float,
     *   titleLineHeight: float,
     *   entryLineHeight: float,
     *   firstEntryY: float,
     *   newPageEntryY: float
     * }
     */
    private function layout(TableOfContentsOptions $options, PageSize $pageSize): array
    {
        $left = $options->margin->left;
        $right = $pageSize->width() - $options->margin->right;
        $top = $pageSize->height() - $options->margin->top;
        $bottom = $options->margin->bottom;
        $contentWidth = $right - $left;
        $titleLineHeight = max($options->titleSize * 1.2, $options->titleSize);
        $entryLineHeight = ($options->entrySize * 1.35) + $options->style->entrySpacing;
        $firstEntryY = $top - $titleLineHeight - $options->style->titleSpacingAfter;
        $newPageEntryY = $top;

        if ($contentWidth <= 0.0) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_LAYOUT_INVALID,
                'Table of contents content width must be greater than zero.',
            );
        }

        if (($top - $bottom) <= max($titleLineHeight + $options->style->titleSpacingAfter, $entryLineHeight)) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_LAYOUT_INVALID,
                'Table of contents content height must be greater than zero.',
            );
        }

        return [
            'left' => $left,
            'right' => $right,
            'top' => $top,
            'bottom' => $bottom,
            'contentWidth' => $contentWidth,
            'titleLineHeight' => $titleLineHeight,
            'entryLineHeight' => $entryLineHeight,
            'firstEntryY' => $firstEntryY,
            'newPageEntryY' => $newPageEntryY,
        ];
    }

    /**
     * @param list<TableOfContentsEntry> $entries
     * @param array{
     *   left: float,
     *   right: float,
     *   top: float,
     *   bottom: float,
     *   contentWidth: float,
     *   titleLineHeight: float,
     *   entryLineHeight: float,
     *   firstEntryY: float,
     *   newPageEntryY: float
     * } $layout
     */
    private function countTocPages(array $entries, float $pageHeight, array $layout, TableOfContentsOptions $options): int
    {
        $pageCount = 1;
        $currentY = $layout['firstEntryY'];

        foreach ($entries as $_entry) {
            if ($currentY < $layout['bottom'] + $layout['entryLineHeight']) {
                $pageCount++;
                $currentY = $pageHeight - $options->margin->top;
            }

            $currentY -= $layout['entryLineHeight'];
        }

        return $pageCount;
    }

    /**
     * @param list<array{
     *   entry: TableOfContentsEntry,
     *   destination: string,
     *   displayPageNumber: int
     * }> $resolvedEntries
     * @param array{
     *   left: float,
     *   right: float,
     *   top: float,
     *   bottom: float,
     *   contentWidth: float,
     *   titleLineHeight: float,
     *   entryLineHeight: float,
     *   firstEntryY: float,
     *   newPageEntryY: float
     * } $layout
     * @return list<Page>
     */
    private function buildTocPages(
        array $resolvedEntries,
        PageSize $pageSize,
        array $layout,
        TableOfContentsOptions $options,
    ): array {
        $builder = DefaultDocumentBuilder::make()->pageSize($pageSize);
        $builder = $builder->text($options->title, TextOptions::make(
            left: $layout['left'],
            bottom: $layout['top'],
            fontSize: $options->titleSize,
            fontName: $options->fontName,
            embeddedFont: $options->embeddedFont,
        ));
        $currentY = $layout['firstEntryY'];
        $pageNumber = 1;

        foreach ($resolvedEntries as $resolvedEntry) {
            if ($currentY < $layout['bottom'] + $layout['entryLineHeight']) {
                $builder = $builder->newPage(PageOptions::make(pageSize: $pageSize));
                $currentY = $layout['newPageEntryY'];
                $pageNumber++;
            }

            /** @var TableOfContentsEntry $entry */
            $entry = $resolvedEntry['entry'];
            $indent = self::ENTRY_INDENT * ($entry->level - 1);
            $pageNumberText = (string) $resolvedEntry['displayPageNumber'];
            $pageNumberWidth = $this->measureTextWidth($pageNumberText, $options);
            $entryWidth = max(0.0, $layout['contentWidth'] - $indent - $pageNumberWidth - $options->style->pageNumberGap);
            $entryTitle = $this->fitTextToWidth($entry->title, $entryWidth, $options);
            $entryTitleWidth = $this->measureTextWidth($entryTitle, $options);
            $leaderWidth = max(
                0.0,
                $layout['contentWidth'] - $indent - $entryTitleWidth - $pageNumberWidth - $options->style->pageNumberGap,
            );
            $leaderText = $this->buildLeaderText($leaderWidth, $options);

            $builder = $builder->text($entryTitle, TextOptions::make(
                left: $layout['left'] + $indent,
                bottom: $currentY,
                fontSize: $options->entrySize,
                fontName: $options->fontName,
                embeddedFont: $options->embeddedFont,
                link: LinkTarget::namedDestination($resolvedEntry['destination']),
            ));

            if ($leaderText !== '') {
                $builder = $builder->text($leaderText, TextOptions::make(
                    left: $layout['left'] + $indent + $entryTitleWidth + ($options->style->pageNumberGap / 2),
                    bottom: $currentY,
                    fontSize: $options->entrySize,
                    fontName: $options->fontName,
                    embeddedFont: $options->embeddedFont,
                ));
            }

            $builder = $builder->text($pageNumberText, TextOptions::make(
                left: $layout['right'] - $pageNumberWidth,
                bottom: $currentY,
                fontSize: $options->entrySize,
                fontName: $options->fontName,
                embeddedFont: $options->embeddedFont,
                link: LinkTarget::namedDestination($resolvedEntry['destination']),
            ));

            $currentY -= $layout['entryLineHeight'];
        }

        $pages = $builder->build()->pages;

        if (count($pages) !== $pageNumber) {
            throw new DocumentValidationException(
                DocumentBuildError::TABLE_OF_CONTENTS_PAGE_COUNT_UNRESOLVED,
                'Table of contents page count could not be resolved deterministically.',
            );
        }

        return $pages;
    }

    /**
     * @param list<PageAnnotation> $annotations
     * @return list<PageAnnotation>
     */
    private function shiftPageAnnotations(array $annotations, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($annotations as $annotation) {
            if (!$annotation instanceof LinkAnnotation) {
                $shifted[] = $annotation;
                continue;
            }

            $shifted[] = new LinkAnnotation(
                target: $this->shiftLinkTarget($annotation->target, $tocPageCount, $insertionIndex),
                x: $annotation->x,
                y: $annotation->y,
                width: $annotation->width,
                height: $annotation->height,
                contents: $annotation->contents,
                accessibleLabel: $annotation->accessibleLabel,
                markedContentId: $annotation->markedContentId,
                structParentId: $annotation->structParentId,
                taggedGroupKey: $annotation->taggedGroupKey,
            );
        }

        return $shifted;
    }

    private function shiftLinkTarget(LinkTarget $target, int $tocPageCount, int $insertionIndex): LinkTarget
    {
        if ($target->isPage()) {
            return LinkTarget::page(
                $this->shiftedPageNumber($target->pageNumberValue(), $tocPageCount, $insertionIndex),
            );
        }

        if ($target->isPosition()) {
            return LinkTarget::position(
                $this->shiftedPageNumber($target->pageNumberValue(), $tocPageCount, $insertionIndex),
                $target->xValue(),
                $target->yValue(),
            );
        }

        return $target;
    }

    /**
     * @param list<Outline> $outlines
     * @return list<Outline>
     */
    private function shiftOutlines(array $outlines, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($outlines as $outline) {
            $pageNumber = $this->shiftedPageNumber($outline->pageNumber, $tocPageCount, $insertionIndex);
            $shifted[] = $outline
                ->withDestination($this->shiftOutlineDestination($outline->destination, $pageNumber))
                ->withLevel($outline->level);
        }

        return $shifted;
    }

    private function shiftOutlineDestination(OutlineDestination $destination, int $pageNumber): OutlineDestination
    {
        if ($destination->isFit()) {
            $shifted = OutlineDestination::fit($pageNumber);
        } elseif ($destination->isFitHorizontal()) {
            $shifted = OutlineDestination::fitHorizontal($pageNumber, $destination->top ?? 0.0);
        } elseif ($destination->isFitRectangle()) {
            $shifted = OutlineDestination::fitRectangle(
                $pageNumber,
                $destination->left ?? 0.0,
                $destination->bottom ?? 0.0,
                $destination->right ?? 0.0,
                $destination->top ?? 0.0,
            );
        } elseif ($destination->hasExplicitPosition()) {
            $shifted = OutlineDestination::xyz($pageNumber, $destination->x ?? 0.0, $destination->y ?? 0.0);
        } else {
            $shifted = OutlineDestination::xyzPage($pageNumber);
        }

        return $destination->useGoToAction ? $shifted->asGoToAction() : $shifted;
    }

    private function shiftAcroForm(?AcroForm $acroForm, int $tocPageCount, int $insertionIndex): ?AcroForm
    {
        if ($acroForm === null) {
            return null;
        }

        $fields = [];

        foreach ($acroForm->fields as $field) {
            $fields[] = $this->shiftFormField($field, $tocPageCount, $insertionIndex);
        }

        return new AcroForm(fields: $fields, needAppearances: $acroForm->needAppearances);
    }

    private function shiftFormField(FormField $field, int $tocPageCount, int $insertionIndex): FormField
    {
        if ($field instanceof TextField) {
            return new TextField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                width: $field->width,
                height: $field->height,
                value: $field->value,
                alternativeName: $field->alternativeName,
                defaultValue: $field->defaultValue,
                fontSize: $field->fontSize,
                multiline: $field->multiline,
            );
        }

        if ($field instanceof CheckboxField) {
            return new CheckboxField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                size: $field->width,
                checked: $field->checked,
                alternativeName: $field->alternativeName,
            );
        }

        if ($field instanceof ComboBoxField) {
            return new ComboBoxField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                width: $field->width,
                height: $field->height,
                options: $field->options,
                value: $field->value,
                alternativeName: $field->alternativeName,
                defaultValue: $field->defaultValue,
                fontSize: $field->fontSize,
            );
        }

        if ($field instanceof ListBoxField) {
            return new ListBoxField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                width: $field->width,
                height: $field->height,
                options: $field->options,
                value: $field->value,
                alternativeName: $field->alternativeName,
                defaultValue: $field->defaultValue,
                fontSize: $field->fontSize,
            );
        }

        if ($field instanceof PushButtonField) {
            return new PushButtonField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                width: $field->width,
                height: $field->height,
                label: $field->label,
                alternativeName: $field->alternativeName,
                url: $field->url,
                fontSize: $field->fontSize,
            );
        }

        if ($field instanceof SignatureField) {
            return new SignatureField(
                name: $field->name,
                pageNumber: $this->shiftedPageNumber($field->pageNumber, $tocPageCount, $insertionIndex),
                x: $field->x,
                y: $field->y,
                width: $field->width,
                height: $field->height,
                alternativeName: $field->alternativeName,
            );
        }

        if ($field instanceof RadioButtonGroup) {
            $choices = [];

            foreach ($field->choices as $choice) {
                $choices[] = new RadioButtonChoice(
                    pageNumber: $this->shiftedPageNumber($choice->pageNumber, $tocPageCount, $insertionIndex),
                    x: $choice->x,
                    y: $choice->y,
                    size: $choice->size,
                    exportValue: $choice->exportValue,
                    checked: $choice->checked,
                    alternativeName: $choice->alternativeName,
                );
            }

            return new RadioButtonGroup(
                name: $field->name,
                choices: $choices,
                alternativeName: $field->alternativeName,
            );
        }

        return $field;
    }

    /**
     * @param list<TaggedFigure> $taggedFigures
     * @return list<TaggedFigure>
     */
    private function shiftTaggedFigures(array $taggedFigures, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($taggedFigures as $taggedFigure) {
            $shifted[] = new TaggedFigure(
                pageIndex: $this->shiftedPageIndex($taggedFigure->pageIndex, $tocPageCount, $insertionIndex),
                markedContentId: $taggedFigure->markedContentId,
                altText: $taggedFigure->altText,
                key: $taggedFigure->key,
            );
        }

        return $shifted;
    }

    /**
     * @param list<TaggedTextBlock> $taggedTextBlocks
     * @return list<TaggedTextBlock>
     */
    private function shiftTaggedTextBlocks(array $taggedTextBlocks, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($taggedTextBlocks as $taggedTextBlock) {
            $shifted[] = new TaggedTextBlock(
                tag: $taggedTextBlock->tag,
                pageIndex: $this->shiftedPageIndex($taggedTextBlock->pageIndex, $tocPageCount, $insertionIndex),
                markedContentId: $taggedTextBlock->markedContentId,
                key: $taggedTextBlock->key,
            );
        }

        return $shifted;
    }

    /**
     * @param list<TaggedTable> $taggedTables
     * @return list<TaggedTable>
     */
    private function shiftTaggedTables(array $taggedTables, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($taggedTables as $taggedTable) {
            $captionReferences = [];

            foreach ($taggedTable->captionReferences as $reference) {
                $captionReferences[] = $this->shiftTaggedTableContentReference($reference, $tocPageCount, $insertionIndex);
            }

            $shifted[] = new TaggedTable(
                tableId: $taggedTable->tableId,
                captionReferences: $captionReferences,
                headerRows: $this->shiftTaggedRows($taggedTable->headerRows, $tocPageCount, $insertionIndex),
                bodyRows: $this->shiftTaggedRows($taggedTable->bodyRows, $tocPageCount, $insertionIndex),
                footerRows: $this->shiftTaggedRows($taggedTable->footerRows, $tocPageCount, $insertionIndex),
                key: $taggedTable->key,
            );
        }

        return $shifted;
    }

    /**
     * @param list<TaggedList> $taggedLists
     * @return list<TaggedList>
     */
    private function shiftTaggedLists(array $taggedLists, int $tocPageCount, int $insertionIndex): array
    {
        $shifted = [];

        foreach ($taggedLists as $taggedList) {
            $items = [];

            foreach ($taggedList->items as $item) {
                $items[] = new TaggedListItem(
                    labelReference: $this->shiftTaggedListContentReference($item->labelReference, $tocPageCount, $insertionIndex),
                    bodyReference: $this->shiftTaggedListContentReference($item->bodyReference, $tocPageCount, $insertionIndex),
                );
            }

            $shifted[] = new TaggedList(
                listId: $taggedList->listId,
                items: $items,
                key: $taggedList->key,
            );
        }

        return $shifted;
    }

    /**
     * @param list<TaggedTableRow> $rows
     * @return list<TaggedTableRow>
     */
    private function shiftTaggedRows(array $rows, int $tocPageCount, int $insertionIndex): array
    {
        $shiftedRows = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row->cells as $cell) {
                $references = [];

                foreach ($cell->contentReferences as $reference) {
                    $references[] = $this->shiftTaggedTableContentReference($reference, $tocPageCount, $insertionIndex);
                }

                $cells[] = new TaggedTableCell(
                    columnIndex: $cell->columnIndex,
                    header: $cell->header,
                    headerScope: $cell->headerScope,
                    rowspan: $cell->rowspan,
                    colspan: $cell->colspan,
                    contentReferences: $references,
                );
            }

            $shiftedRows[] = new TaggedTableRow(
                rowIndex: $row->rowIndex,
                cells: $cells,
            );
        }

        return $shiftedRows;
    }

    private function shiftTaggedTableContentReference(
        TaggedTableContentReference $reference,
        int $tocPageCount,
        int $insertionIndex,
    ): TaggedTableContentReference {
        return new TaggedTableContentReference(
            pageIndex: $this->shiftedPageIndex($reference->pageIndex, $tocPageCount, $insertionIndex),
            markedContentId: $reference->markedContentId,
        );
    }

    private function shiftTaggedListContentReference(
        TaggedListContentReference $reference,
        int $tocPageCount,
        int $insertionIndex,
    ): TaggedListContentReference {
        return new TaggedListContentReference(
            pageIndex: $this->shiftedPageIndex($reference->pageIndex, $tocPageCount, $insertionIndex),
            markedContentId: $reference->markedContentId,
        );
    }

    /**
     * @param list<PageAnnotation> $annotations
     * @param list<NamedDestination> $extraNamedDestinations
     */
    private function clonePage(Page $page, array $annotations, array $extraNamedDestinations): Page
    {
        return new Page(
            size: $page->size,
            contents: $page->contents,
            fontResources: $page->fontResources,
            imageResources: $page->imageResources,
            images: $page->images,
            annotations: $annotations,
            namedDestinations: array_merge($page->namedDestinations, $extraNamedDestinations),
            margin: $page->margin,
            backgroundColor: $page->backgroundColor,
            label: $page->label,
            name: $page->name,
        );
    }

    private function shiftedPageNumber(int $pageNumber, int $tocPageCount, int $insertionIndex): int
    {
        return $pageNumber > $insertionIndex
            ? $pageNumber + $tocPageCount
            : $pageNumber;
    }

    private function shiftedPageIndex(int $pageIndex, int $tocPageCount, int $insertionIndex): int
    {
        return $pageIndex >= $insertionIndex
            ? $pageIndex + $tocPageCount
            : $pageIndex;
    }

    private function measureTextWidth(string $text, TableOfContentsOptions $options, ?float $fontSize = null): float
    {
        $fontSize ??= $options->entrySize;

        if ($options->embeddedFont !== null) {
            return EmbeddedFontDefinition::fromSource($options->embeddedFont)->measureTextWidth($text, $fontSize);
        }

        return StandardFontDefinition::from($options->fontName)->measureTextWidth($text, $fontSize);
    }

    private function fitTextToWidth(string $text, float $maxWidth, TableOfContentsOptions $options): string
    {
        if ($this->measureTextWidth($text, $options) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';

        if ($this->measureTextWidth($ellipsis, $options) > $maxWidth) {
            return $ellipsis;
        }

        $current = '';
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $character) {
            $candidate = $current . $character;

            if ($this->measureTextWidth($candidate . $ellipsis, $options) > $maxWidth) {
                break;
            }

            $current = $candidate;
        }

        return rtrim($current) . $ellipsis;
    }

    private function buildLeaderText(float $leaderWidth, TableOfContentsOptions $options): string
    {
        if ($options->style->leaderStyle === TableOfContentsLeaderStyle::NONE || $leaderWidth <= 0.0) {
            return '';
        }

        $leaderCharacter = match ($options->style->leaderStyle) {
            TableOfContentsLeaderStyle::DOTS => '.',
            TableOfContentsLeaderStyle::DASHES => '-',
        };

        $characterWidth = max(0.0001, $this->measureTextWidth($leaderCharacter, $options));
        $characterCount = max(3, (int) floor($leaderWidth / $characterWidth));

        return str_repeat($leaderCharacter, $characterCount);
    }
}
