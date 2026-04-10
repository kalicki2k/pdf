<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Style\Color;

final readonly class FormFieldLabel
{
    public function __construct(
        public string $text,
        public Position $position,
        public string $fontName,
        public int $size = 10,
        public ?Color $color = null,
    ) {
        if ($this->text === '') {
            throw new InvalidArgumentException('Form field label text must not be empty.');
        }

        if ($this->fontName === '') {
            throw new InvalidArgumentException('Form field label font name must not be empty.');
        }

        if ($this->size <= 0) {
            throw new InvalidArgumentException('Form field label font size must be greater than zero.');
        }
    }
}
