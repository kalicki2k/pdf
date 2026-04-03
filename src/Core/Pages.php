<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

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

    public function render(): string
    {
        $kidReferences = [];

        foreach ($this->pages as $page) {
            $kidReferences[] = new Reference($page);
        }

        $kids = new ArrayValue($kidReferences);

        $dictionary = new Dictionary([
            'Type' => new Name('Pages'),
            'Kids' => $kids,
            'Count' => count($this->pages),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
