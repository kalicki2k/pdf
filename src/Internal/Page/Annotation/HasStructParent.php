<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Internal\PdfType\DictionaryType;

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
