<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use InvalidArgumentException;

final readonly class RadioButtonChoice
{
    public function __construct(
        public int $pageNumber,
        public float $x,
        public float $y,
        public float $size,
        public string $exportValue,
        public bool $checked = false,
        public ?string $alternativeName = null,
    ) {
        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Radio button page number must be greater than zero.');
        }

        if ($this->size <= 0.0) {
            throw new InvalidArgumentException('Radio button size must be greater than zero.');
        }

        if ($this->exportValue === '') {
            throw new InvalidArgumentException('Radio button export value must not be empty.');
        }

        if ($this->alternativeName === '') {
            throw new InvalidArgumentException('Radio button alternative name must not be empty.');
        }
    }
}
