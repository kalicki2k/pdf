<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Page\Content\PathBuilder;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

trait HandlesPageGraphics
{
    public function addPath(): PathBuilder
    {
        return $this->pageGraphics()->addPath();
    }

    public function addLine(
        Position $from,
        Position $to,
        float $width = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addLine($from, $to, $width, $color, $opacity);

        return $this;
    }

    public function addRectangle(
        Rect $box,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addRectangle($box, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addRoundedRectangle(
        Rect $box,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addRoundedRectangle($box, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addCircle(
        float $centerX,
        float $centerY,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addCircle($centerX, $centerY, $radius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addEllipse(
        float $centerX,
        float $centerY,
        float $radiusX,
        float $radiusY,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addEllipse($centerX, $centerY, $radiusX, $radiusY, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    /**
     * @param list<array{0: float|int, 1: float|int}> $points
     */
    public function addPolygon(
        array $points,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addPolygon($points, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }

    public function addArrow(
        Position $from,
        Position $to,
        float $strokeWidth = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
        float $headLength = 10.0,
        float $headWidth = 8.0,
    ): self {
        $this->pageGraphics()->addArrow($from, $to, $strokeWidth, $color, $opacity, $headLength, $headWidth);

        return $this;
    }

    public function addStar(
        float $centerX,
        float $centerY,
        int $points,
        float $outerRadius,
        float $innerRadius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): self {
        $this->pageGraphics()->addStar($centerX, $centerY, $points, $outerRadius, $innerRadius, $strokeWidth, $strokeColor, $fillColor, $opacity);

        return $this;
    }
}
