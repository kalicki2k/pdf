<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Outline;

use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class OutlineItem extends DictionaryIndirectObject
{
    private ?self $prev = null;
    private ?self $next = null;

    public function __construct(
        int $id,
        private readonly OutlineRoot $parent,
        private readonly string $title,
        private readonly Page $page,
    ) {
        parent::__construct($id);
    }

    public function setPrev(self $prev): void
    {
        $this->prev = $prev;
    }

    public function setNext(self $next): void
    {
        $this->next = $next;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Title' => new StringType($this->title),
            'Parent' => new ReferenceType($this->parent),
            'Dest' => new ArrayType([
                new ReferenceType($this->page),
                new NameType('Fit'),
            ]),
        ]);

        if ($this->prev instanceof self) {
            $dictionary->add('Prev', new ReferenceType($this->prev));
        }

        if ($this->next instanceof self) {
            $dictionary->add('Next', new ReferenceType($this->next));
        }

        return $dictionary;
    }
}
