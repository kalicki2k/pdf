<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use InvalidArgumentException;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\RawValue;

final class StructElem extends IndirectObject
{
    /** @var string[] */
    private array $kids = [];

    /** @var string[]  */
    private array $allowedTags = [
        // Text-Tags
        'Document',
        'H1', 'H2', 'H3',
        'P',
        'L', 'LI', 'LBody',
        'Span', 'Quote', 'Note',

        // Struktur-Tags
        'Part', 'Sect', 'Art', 'Div'

        // Tabellen-Tags
        // ...
    ];

    public function __construct(
        int                     $id,
        private readonly string $tag,
    )
    {
        parent::__construct($id);
        $this->validate();
    }

    public function addKid(int $id): self
    {
        $this->kids[] = (string)$id;
        return $this;
    }

    public function render(): string
    {
        $kidReferences = [];

        foreach ($this->kids as $id) {
            $kidReferences[] = new RawValue($id . ' 0 R');
        }

        $dictionary = new Dictionary([
            'Type' => new Name('StructElem'),
            'S' => new Name($this->tag),
            'K' => new ArrayValue($kidReferences),
        ]);

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
