<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class Pages extends DictionaryIndirectObject
{
    /** @var Page[] */
    public array $pages = [];

    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function addPage(
        int   $pageId,
        int   $contentsId,
        int   $resourcesId,
        int   $structParentId,
        float $width,
        float $height,
    ): Page {
        $page = new PdfPage(
            $pageId,
            $contentsId,
            $resourcesId,
            $structParentId,
            $width,
            $height,
            $this->document,
        );
        $this->pages[] = $page;

        return $page;
    }

    /**
     * @param list<Page> $pages
     */
    public function insertPagesAt(array $pages, int $index): void
    {
        if ($index < 0 || $index > count($this->pages)) {
            throw new InvalidArgumentException('Page insertion index is out of bounds.');
        }

        $remainingPages = array_values(array_filter(
            $this->pages,
            static fn (Page $page): bool => !in_array($page, $pages, true),
        ));

        array_splice($remainingPages, $index, 0, $pages);
        $this->pages = $remainingPages;
    }

    protected function dictionary(): DictionaryType
    {
        $kidReferences = [];

        foreach ($this->pages as $page) {
            $kidReferences[] = new ReferenceType($page);
        }

        $kids = new ArrayType($kidReferences);

        $dictionary = new DictionaryType([
            'Type' => new NameType('Pages'),
            'Kids' => $kids,
            'Count' => count($this->pages),
        ]);

        return $dictionary;
    }
}
