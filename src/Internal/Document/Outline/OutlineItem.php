<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Outline;

use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\PdfType\ArrayType;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\PdfType\ReferenceType;
use Kalle\Pdf\Internal\PdfType\StringType;
use Kalle\Pdf\Page;

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
