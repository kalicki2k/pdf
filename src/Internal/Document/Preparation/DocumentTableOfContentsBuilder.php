<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Preparation;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsStyle;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Page\PageSize;
use Kalle\Pdf\Internal\Layout\Text\Input\ParagraphOptions;
use Kalle\Pdf\Internal\Layout\Text\Input\TextOptions;
use Kalle\Pdf\Internal\Page\Link\LinkTarget;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\TaggedPdf\StructureTag;

/**
 * @internal Builds and inserts the table of contents pages for a document.
 */
class DocumentTableOfContentsBuilder
{
    /**
     * @param array<int, true> $excludedPageIdsFromNumbering
     */
    public function __construct(
        private Document $document,
        private array $excludedPageIdsFromNumbering,
    ) {
    }

    public function addTableOfContents(?PageSize $size = null, ?TableOfContentsOptions $options = null): Page
    {
        $options ??= new TableOfContentsOptions();
        $firstTocPageIndex = count($this->document->pages->pages);
        $insertionIndex = $options->placement->insertionIndex($firstTocPageIndex);
        $page = $this->document->addPage($size ?? PageSize::A4());
        $contentWidth = $page->getWidth() - ($options->margin * 2);

        if ($contentWidth <= 0) {
            throw new InvalidArgumentException('Table of contents content width must be greater than zero.');
        }

        $frame = $page->createTextFrame(
            new Position($options->margin, $page->getHeight() - $options->margin),
            $contentWidth,
            $options->margin,
        );
        $frame->addHeading(
            $options->title,
            $options->baseFont,
            $options->titleSize,
            new ParagraphOptions(structureTag: StructureTag::Heading1),
        );

        $outlineRoot = $this->document->outlineRoot;

        if ($outlineRoot === null || $outlineRoot->getItems() === []) {
            throw new InvalidArgumentException('Table of contents requires at least one outline entry.');
        }

        $entryLineHeight = ($options->entrySize * 1.35) + $options->style->entrySpacing;
        $currentPage = $page;
        $currentY = $frame->getCursorY();
        $pageNumbersByObjectId = $this->buildPageNumbers(
            $firstTocPageIndex,
            $this->estimatePageCount(
                count($outlineRoot->getItems()),
                $currentY,
                $page->getHeight(),
                $options->margin,
                $entryLineHeight,
            ),
            $insertionIndex,
            $options->useLogicalPageNumbers,
        );

        foreach ($outlineRoot->getItems() as $outlineItem) {
            if ($currentY < $options->margin + $entryLineHeight) {
                $currentPage = $this->document->addPage($page->getWidth(), $page->getHeight());
                $currentY = $currentPage->getHeight() - $options->margin;
            }

            $targetPage = $outlineItem->getPage();
            $pageNumber = $pageNumbersByObjectId[$targetPage->id] ?? null;

            if ($pageNumber === null) {
                continue;
            }

            $destinationName = 'toc-page-' . $targetPage->id;
            $this->document->addDestination($destinationName, $targetPage);

            $pageNumberText = (string) $pageNumber;
            $pageNumberWidth = $currentPage->measureTextWidth($pageNumberText, $options->baseFont, $options->entrySize);
            $entryWidth = $contentWidth - $pageNumberWidth - $options->style->pageNumberGap;
            $entryTitle = $this->fitTextToWidth(
                $currentPage,
                $outlineItem->getTitle(),
                $options->baseFont,
                $options->entrySize,
                $entryWidth,
            );
            $entryTitleWidth = $currentPage->measureTextWidth($entryTitle, $options->baseFont, $options->entrySize);
            $leaderText = $this->buildLeaderText(
                $currentPage,
                $options->baseFont,
                $options->entrySize,
                max(0.0, $contentWidth - $entryTitleWidth - $pageNumberWidth - $options->style->pageNumberGap),
                $options->style,
            );

            $currentPage->addText(
                $entryTitle,
                new Position($options->margin, $currentY),
                $options->baseFont,
                $options->entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );
            if ($leaderText !== '') {
                $currentPage->addText(
                    $leaderText,
                    new Position($options->margin + $entryTitleWidth + ($options->style->pageNumberGap / 2), $currentY),
                    $options->baseFont,
                    $options->entrySize,
                );
            }
            $currentPage->addText(
                $pageNumberText,
                new Position($page->getWidth() - $options->margin - $pageNumberWidth, $currentY),
                $options->baseFont,
                $options->entrySize,
                new TextOptions(link: LinkTarget::namedDestination($destinationName)),
            );

            $currentY -= $entryLineHeight;
        }

        if ($insertionIndex !== $firstTocPageIndex) {
            $tocPages = array_values(array_slice($this->document->pages->pages, $firstTocPageIndex));
            $this->document->pages->insertPagesAt($tocPages, $insertionIndex);
        }

        return $page;
    }

    private function buildLeaderText(
        Page $page,
        string $baseFont,
        int $entrySize,
        float $leaderWidth,
        TableOfContentsStyle $style,
    ): string {
        if ($style->leaderStyle === TableOfContentsLeaderStyle::NONE || $leaderWidth <= 0.0) {
            return '';
        }

        $leaderCharacter = match ($style->leaderStyle) {
            TableOfContentsLeaderStyle::DOTS => '.',
            TableOfContentsLeaderStyle::DASHES => '-',
        };

        $characterWidth = max(0.0001, $page->measureTextWidth($leaderCharacter, $baseFont, $entrySize));
        $characterCount = max(3, (int) floor($leaderWidth / $characterWidth));

        return str_repeat($leaderCharacter, $characterCount);
    }

    /**
     * @return array<int, int>
     */
    private function buildPageNumbers(
        int $firstTocPageIndex,
        int $tocPageCount,
        int $insertionIndex,
        bool $useLogicalPageNumbers,
    ): array {
        if ($useLogicalPageNumbers) {
            return $this->buildLogicalPageNumbers($firstTocPageIndex, $tocPageCount, $insertionIndex);
        }

        $pageNumbersByObjectId = [];

        foreach (array_slice($this->document->pages->pages, 0, $firstTocPageIndex) as $index => $documentPage) {
            $pageNumbersByObjectId[$documentPage->id] = $index + 1 + ($index >= $insertionIndex ? $tocPageCount : 0);
        }

        return $pageNumbersByObjectId;
    }

    /**
     * @return array<int, int>
     */
    private function buildLogicalPageNumbers(
        int $firstTocPageIndex,
        int $tocPageCount,
        int $insertionIndex,
    ): array {
        $pageNumbersByObjectId = [];
        $contentPages = array_values(array_slice($this->document->pages->pages, 0, $firstTocPageIndex));
        $logicalPageNumber = 0;
        $pageIndex = 0;
        $totalPageCount = $firstTocPageIndex + $tocPageCount;

        foreach ($contentPages as $documentPage) {
            while ($pageIndex >= $insertionIndex && $pageIndex < $insertionIndex + $tocPageCount) {
                $logicalPageNumber++;
                $pageIndex++;
            }

            $pageIndex++;

            if (isset($this->excludedPageIdsFromNumbering[$documentPage->id])) {
                continue;
            }

            $logicalPageNumber++;
            $pageNumbersByObjectId[$documentPage->id] = $logicalPageNumber;
        }

        while ($pageIndex < $totalPageCount) {
            $pageIndex++;
        }

        return $pageNumbersByObjectId;
    }

    private function estimatePageCount(
        int $entryCount,
        float $initialY,
        float $pageHeight,
        float $margin,
        float $entryLineHeight,
    ): int {
        $pageCount = 1;
        $currentY = $initialY;

        for ($index = 0; $index < $entryCount; $index++) {
            if ($currentY < $margin + $entryLineHeight) {
                $pageCount++;
                $currentY = $pageHeight - $margin;
            }

            $currentY -= $entryLineHeight;
        }

        return $pageCount;
    }

    private function fitTextToWidth(Page $page, string $text, string $baseFont, int $size, float $maxWidth): string
    {
        if ($page->measureTextWidth($text, $baseFont, $size) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';
        $ellipsisWidth = $page->measureTextWidth($ellipsis, $baseFont, $size);

        if ($ellipsisWidth > $maxWidth) {
            return $ellipsis;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $current = '';

        foreach ($characters as $character) {
            $candidate = $current . $character;

            if ($page->measureTextWidth($candidate . $ellipsis, $baseFont, $size) > $maxWidth) {
                break;
            }

            $current = $candidate;
        }

        return rtrim($current) . $ellipsis;
    }
}
