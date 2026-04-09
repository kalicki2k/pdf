<?php

declare(strict_types=1);

namespace Kalle\Pdf\Structure;

use InvalidArgumentException;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\RawType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class StructElem extends DictionaryIndirectObject
{
    /** @var StructElem[] */
    private array $kids = [];
    /** @var RawType[] */
    private array $objectReferences = [];
    /** @var list<int> */
    private array $markedContentIds = [];
    private ?IndirectObject $parent = null;
    private ?Page $page = null;
    private ?string $altText = null;
    private ?string $scope = null;
    private ?int $rowSpan = null;
    private ?int $colSpan = null;

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

    public function setParent(IndirectObject $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function setMarkedContent(int $markedContentId, Page $page): self
    {
        $this->markedContentIds[] = $markedContentId;
        $this->page = $page;

        return $this;
    }

    public function tag(): string
    {
        return $this->tag;
    }

    public function setPage(Page $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function setAltText(string $altText): self
    {
        $this->altText = $altText;

        return $this;
    }

    public function setScope(string $scope): self
    {
        if (!in_array($scope, ['Row', 'Column', 'Both'], true)) {
            throw new InvalidArgumentException("Scope '$scope' is not allowed.");
        }

        $this->scope = $scope;

        return $this;
    }

    public function setRowSpan(int $rowSpan): self
    {
        if ($rowSpan <= 1) {
            throw new InvalidArgumentException('RowSpan must be greater than one.');
        }

        $this->rowSpan = $rowSpan;

        return $this;
    }

    public function setColSpan(int $colSpan): self
    {
        if ($colSpan <= 1) {
            throw new InvalidArgumentException('ColSpan must be greater than one.');
        }

        $this->colSpan = $colSpan;

        return $this;
    }

    public function addObjectReference(IndirectObject $object, Page $page): self
    {
        $this->objectReferences[] = new RawType(sprintf(
            '<< /Type /OBJR /Obj %d 0 R /Pg %d 0 R >>',
            $object->id,
            $page->id,
        ));

        return $this;
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('StructElem'),
            'S' => new NameType($this->tag),
        ]);

        if ($this->parent !== null) {
            $dictionary->add('P', new ReferenceType($this->parent));
        }

        if ($this->page !== null) {
            $dictionary->add('Pg', new ReferenceType($this->page));
        }

        if ($this->altText !== null && $this->altText !== '') {
            $dictionary->add('Alt', new StringType($this->altText));
        }

        if ($this->scope !== null || $this->rowSpan !== null || $this->colSpan !== null) {
            $attributes = new DictionaryType([
                'O' => new NameType('Table'),
            ]);

            if ($this->scope !== null) {
                $attributes->add('Scope', new NameType($this->scope));
            }

            if ($this->rowSpan !== null) {
                $attributes->add('RowSpan', $this->rowSpan);
            }

            if ($this->colSpan !== null) {
                $attributes->add('ColSpan', $this->colSpan);
            }

            $dictionary->add('A', $attributes);
        }

        if (count($this->markedContentIds) === 1 && $this->kids === [] && $this->objectReferences === []) {
            $dictionary->add('K', $this->markedContentIds[0]);
        } else {
            $kidReferences = [];

            foreach ($this->markedContentIds as $markedContentId) {
                $kidReferences[] = $markedContentId;
            }

            foreach ($this->kids as $kid) {
                $kidReferences[] = new ReferenceType($kid);
            }

            foreach ($this->objectReferences as $objectReference) {
                $kidReferences[] = $objectReference;
            }

            $dictionary->add('K', new ArrayType($kidReferences));
        }

        return $dictionary;
    }

    private function validate(): void
    {
        $allowedTags = array_map(static fn (StructureTag $tag): string => $tag->value, StructureTag::cases());

        if (!in_array($this->tag, $allowedTags, true)) {
            throw new InvalidArgumentException("Tag '$this->tag' is not allowed.");
        }
    }
}
