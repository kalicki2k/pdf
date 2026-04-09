<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Annotation;

use Kalle\Pdf\Types\DictionaryType;

trait HasStructParent
{
    private ?int $structParentId = null;

    public function withStructParent(int $structParentId): self
    {
        $this->structParentId = $structParentId;

        return $this;
    }

    private function addStructParentEntry(DictionaryType $dictionary): void
    {
        if ($this->structParentId !== null) {
            $dictionary->add('StructParent', $this->structParentId);
        }
    }
}
