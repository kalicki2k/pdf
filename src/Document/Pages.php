<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

final class Pages extends IndirectObject
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
        $page = new Page($pageId, $contentsId, $resourcesId, $structParentId, $width, $height, $this->document);
        $this->pages[] = $page;

        return $page;
    }

    /**
     * @param list<Page> $pages
     */
    public function prependPages(array $pages): void
    {
        $remainingPages = array_values(array_filter(
            $this->pages,
            static fn (Page $page): bool => !in_array($page, $pages, true),
        ));

        $this->pages = [...$pages, ...$remainingPages];
    }

    public function render(): string
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

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
