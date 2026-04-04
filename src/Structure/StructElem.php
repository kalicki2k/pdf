<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\RawValue;
use Kalle\Pdf\Types\Reference;

final class StructElem extends IndirectObject
{
    /** @var StructElem[] */
    private array $kids = [];
    private ?int $markedContentId = null;
    private ?StructElem $parent = null;
    private ?Page $page = null;

    /** @var string[]  */
    private array $allowedTags = [
        // Text-Tags
        'Document',
        'H1', 'H2', 'H3',
        'P',
        'L', 'LI', 'LBody',
        'Span', 'Quote', 'Note',

        // Struktur-Tags
        'Part', 'Sect', 'Art', 'Div',

        // Tabellen-Tags
        // ...
    ];

    public function __construct(
        int                     $id,
        private readonly string $tag,
    ) {
        parent::__construct($id);
        $this->validate();
    }

    public function addKid(StructElem $structElem): self
    {
        $this->kids[] = $structElem;
        $structElem->parent = $this;

        return $this;
    }

    public function setMarkedContent(int $markedContentId, Page $page): self
    {
        $this->markedContentId = $markedContentId;
        $this->page = $page;

        return $this;
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('StructElem'),
            'S' => new Name($this->tag),
        ]);

        if ($this->parent !== null) {
            $dictionary->add('P', new Reference($this->parent));
        }

        if ($this->page !== null) {
            $dictionary->add('Pg', new Reference($this->page));
        }

        if ($this->markedContentId !== null) {
            $dictionary->add('K', $this->markedContentId);
        } else {
            $kidReferences = [];

            foreach ($this->kids as $kid) {
                $kidReferences[] = new RawValue($kid->id . ' 0 R');
            }

            $dictionary->add('K', new ArrayValue($kidReferences));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function validate(): void
    {
        if (!in_array($this->tag, $this->allowedTags)) {
            throw new InvalidArgumentException("Tag '$this->tag' is not allowed.");
        }
    }
}
