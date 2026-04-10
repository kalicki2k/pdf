<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\OptionalContent;

use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;

final class OptionalContentGroup extends DictionaryIndirectObject
{
    public function __construct(
        int $id,
        private readonly string $name,
        private readonly bool $visibleByDefault = true,
    ) {
        parent::__construct($id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->visibleByDefault;
    }

    protected function dictionary(): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('OCG'),
            'Name' => new StringType($this->name),
        ]);
    }
}
