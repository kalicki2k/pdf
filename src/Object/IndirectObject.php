<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

abstract class IndirectObject
{
    public function __construct(
        public int $id {
            get {
                return $this->id;
            }
        },
    )
    {
    }

    abstract public function render(): string;
}
