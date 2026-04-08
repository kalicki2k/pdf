<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Geometry\Position;

/**
 * @internal Registers deferred page decorators and resolves logical page numbering.
 */
final class DocumentPageDecoratorManager
{
    /** @var list<callable(Page, int, int): void> */
    private array $headerRenderers;

    /** @var list<callable(Page, int, int): void> */
    private array $footerRenderers;

    /** @var array<int, true> */
    private array $excludedPageIdsFromNumbering;

    /**
     * @param list<callable(Page, int, int): void> $headerRenderers
     * @param list<callable(Page, int, int): void> $footerRenderers
     * @param array<int, true> $excludedPageIdsFromNumbering
     */
    public function __construct(
        private readonly Document $document,
        array &$headerRenderers,
        array &$footerRenderers,
        array &$excludedPageIdsFromNumbering,
    ) {
        $this->headerRenderers = & $headerRenderers;
        $this->footerRenderers = & $footerRenderers;
        $this->excludedPageIdsFromNumbering = & $excludedPageIdsFromNumbering;
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addHeader(callable $renderer): void
    {
        $this->headerRenderers = [
            ...$this->headerRenderers,
            static function (Page $page, int $pageNumber) use ($renderer): void {
                $renderer($page, $pageNumber);
            },
        ];
    }

    /**
     * @param callable(Page, int): void $renderer
     */
    public function addFooter(callable $renderer): void
    {
        $this->footerRenderers = [
            ...$this->footerRenderers,
            static function (Page $page, int $pageNumber) use ($renderer): void {
                $renderer($page, $pageNumber);
            },
        ];
    }

    public function addPageNumbers(
        Position $position,
        string $baseFont,
        int $size,
        string $template,
        bool $footer,
        bool $useLogicalPageNumbers,
    ): void {
        if ($template === '') {
            throw new InvalidArgumentException('Page number template must not be empty.');
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('Page number size must be greater than zero.');
        }

        $renderer = function (Page $page, int $pageNumber, int $totalPages) use (
            $position,
            $baseFont,
            $size,
            $template,
            $useLogicalPageNumbers,
        ): void {
            if ($useLogicalPageNumbers) {
                $pageNumber = $this->resolveLogicalPageNumber($page);

                if ($pageNumber === null) {
                    return;
                }

                $totalPages = $this->countLogicalPages();
            }

            $page->addText(
                str_replace(
                    ['{{page}}', '{{pages}}'],
                    [(string) $pageNumber, (string) $totalPages],
                    $template,
                ),
                $position,
                $baseFont,
                $size,
            );
        };

        if ($footer) {
            $this->footerRenderers = [...$this->footerRenderers, $renderer];

            return;
        }

        $this->headerRenderers = [...$this->headerRenderers, $renderer];
    }

    public function excludePageFromNumbering(Page $page): void
    {
        $this->excludedPageIdsFromNumbering[$page->id] = true;
    }

    private function resolveLogicalPageNumber(Page $page): ?int
    {
        $logicalPageNumber = 0;

        foreach ($this->document->pages->pages as $documentPage) {
            if (isset($this->excludedPageIdsFromNumbering[$documentPage->id])) {
                if ($documentPage === $page) {
                    return null;
                }

                continue;
            }

            $logicalPageNumber++;

            if ($documentPage === $page) {
                return $logicalPageNumber;
            }
        }

        return null;
    }

    private function countLogicalPages(): int
    {
        $logicalPageCount = 0;

        foreach ($this->document->pages->pages as $page) {
            if (isset($this->excludedPageIdsFromNumbering[$page->id])) {
                continue;
            }

            $logicalPageCount++;
        }

        return $logicalPageCount;
    }
}
