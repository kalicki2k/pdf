<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use InvalidArgumentException;
use Kalle\Pdf\Element\Path;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Internal\Page\Page;

final class PathBuilder
{
    /** @var list<string> */
    private array $commands = [];

    public function __construct(
        private readonly Page $page,
        private readonly PageGraphics $pageGraphics,
    ) {
    }

    public function moveTo(float $x, float $y): self
    {
        $this->commands[] = Path::formatNumber($x) . ' ' . Path::formatNumber($y) . ' m';

        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        $this->commands[] = Path::formatNumber($x) . ' ' . Path::formatNumber($y) . ' l';

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
        $this->commands[] = Path::formatNumber($x1) . ' '
            . Path::formatNumber($y1) . ' '
            . Path::formatNumber($x2) . ' '
            . Path::formatNumber($y2) . ' '
            . Path::formatNumber($x3) . ' '
            . Path::formatNumber($y3) . ' c';

        return $this;
    }

    public function close(): self
    {
        $this->commands[] = 'h';

        return $this;
    }

    public function stroke(float $width = 1.0, ?Color $color = null, ?Opacity $opacity = null): Page
    {
        if ($width <= 0) {
            throw new InvalidArgumentException('Path stroke width must be greater than zero.');
        }

        return $this->finish(
            'S',
            $width,
            $color?->renderStrokingOperator(),
            null,
            $opacity,
        );
    }

    public function fill(?Color $fillColor = null, ?Opacity $opacity = null): Page
    {
        return $this->finish(
            'f',
            null,
            null,
            $fillColor?->renderNonStrokingOperator(),
            $opacity,
        );
    }

    public function fillAndStroke(
        float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): Page {
        if ($strokeWidth <= 0) {
            throw new InvalidArgumentException('Path stroke width must be greater than zero.');
        }

        return $this->finish(
            'B',
            $strokeWidth,
            $strokeColor?->renderStrokingOperator(),
            $fillColor?->renderNonStrokingOperator(),
            $opacity,
        );
    }

    private function finish(
        string $paintOperator,
        ?float $strokeWidth,
        ?string $strokeColorOperator,
        ?string $fillColorOperator,
        ?Opacity $opacity,
    ): Page {
        if ($this->commands === []) {
            throw new InvalidArgumentException('Path requires at least one drawing command.');
        }

        $graphicsStateName = $this->pageGraphics->resolveGraphicsStateName($opacity);

        $this->pageGraphics->addGraphicElement(new Path(
            $this->commands,
            $strokeWidth,
            $strokeColorOperator,
            $fillColorOperator,
            $graphicsStateName,
            $paintOperator,
        ));

        return $this->page;
    }
}
