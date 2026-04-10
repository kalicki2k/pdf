<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\OptionalContent;

use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\StringType;

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
