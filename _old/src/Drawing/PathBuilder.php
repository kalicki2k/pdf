<?php

declare(strict_types=1);

namespace Kalle\Pdf\Drawing;

final class PathBuilder
{
    /**
     * @var list<array{operator: string, values: list<float>}>
     */
    private array $commands = [];

    public function moveTo(float $x, float $y): self
    {
        $this->commands[] = ['operator' => 'm', 'values' => [$x, $y]];

        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        $this->commands[] = ['operator' => 'l', 'values' => [$x, $y]];

        return $this;
    }

    public function curveTo(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): self {
        $this->commands[] = ['operator' => 'c', 'values' => [$x1, $y1, $x2, $y2, $x3, $y3]];

        return $this;
    }

    public function close(): self
    {
        $this->commands[] = ['operator' => 'h', 'values' => []];

        return $this;
    }

    public function build(): Path
    {
        return new Path($this->commands);
    }
}
