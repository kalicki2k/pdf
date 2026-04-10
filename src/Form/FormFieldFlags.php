<?php

declare(strict_types=1);

namespace Kalle\Pdf\Form;

final readonly class FormFieldFlags
{
    public function __construct(
        public bool $readOnly = false,
        public bool $required = false,
        public bool $password = false,
        public bool $editable = false,
        public bool $multiSelect = false,
    ) {
    }

    public function toPdfFlags(bool $multiline = false, bool $combo = false, bool $listBox = false): int
    {
        $flags = 0;

        if ($this->readOnly) {
            $flags |= 1;
        }

        if ($this->required) {
            $flags |= 1 << 1;
        }

        if ($multiline) {
            $flags |= 1 << 12;
        }

        if ($this->password) {
            $flags |= 1 << 13;
        }

        if ($combo) {
            $flags |= 1 << 17;
        }

        if ($combo && $this->editable) {
            $flags |= 1 << 18;
        }

        if ($listBox && $this->multiSelect) {
            $flags |= 1 << 21;
        }

        return $flags;
    }
}
