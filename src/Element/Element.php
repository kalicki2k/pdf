<?php

declare(strict_types=1);

namespace Kalle\Pdf\Element;

abstract class Element
{
    public float $x;
    public float $y;

    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;

        return $this;
    }

    abstract public function render(): string;
}
