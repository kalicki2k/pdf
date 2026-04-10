<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Content;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Geometry\Position;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\Internal\Page\Content\Instruction\LineInstruction;
use Kalle\Pdf\Internal\Page\Content\Instruction\RawInstruction;
use Kalle\Pdf\Internal\Page\Content\Instruction\RectangleInstruction;
use Kalle\Pdf\Page;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;

/**
 * @internal Coordinates graphic primitives for a page.
 */
final class PageGraphics
{
    public function __construct(private readonly Page $page)
    {
    }

    public static function forPage(Page $page): self
    {
        return new self($page);
    }

    public function addPath(): PathBuilder
    {
        return new PathBuilder($this->page, $this);
    }

    public function addLine(
        Position $from,
        Position $to,
        float $width = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
    ): void {
        if ($width <= 0) {
            throw new InvalidArgumentException('Line width must be greater than zero.');
        }

        $colorOperator = $color?->renderStrokingOperator();
        $graphicsStateName = $this->resolveGraphicsStateName($opacity);

        $this->addGraphicElement(new LineInstruction(
            $from->x,
            $from->y,
            $to->x,
            $to->y,
            $width,
            $colorOperator,
            $graphicsStateName,
        ));
    }

    public function addRectangle(
        Rect $box,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): void {
        if ($box->width <= 0) {
            throw new InvalidArgumentException('Rectangle width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Rectangle height must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Rectangle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Rectangle requires either a stroke or a fill.');
        }

        $graphicsStateName = $this->resolveGraphicsStateName($opacity);

        $this->addGraphicElement(new RectangleInstruction(
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $strokeWidth,
            $strokeColor?->renderStrokingOperator(),
            $fillColor?->renderNonStrokingOperator(),
            $graphicsStateName,
        ));
    }

    public function addRoundedRectangle(
        Rect $box,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): void {
        if ($box->width <= 0) {
            throw new InvalidArgumentException('Rounded rectangle width must be greater than zero.');
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException('Rounded rectangle height must be greater than zero.');
        }

        if ($radius <= 0) {
            throw new InvalidArgumentException('Rounded rectangle radius must be greater than zero.');
        }

        if ($radius > ($box->width / 2) || $radius > ($box->height / 2)) {
            throw new InvalidArgumentException('Rounded rectangle radius must not exceed half the width or height.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Rounded rectangle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Rounded rectangle requires either a stroke or a fill.');
        }

        $controlOffset = $radius * 0.5522847498307936;
        $left = $box->x;
        $right = $box->x + $box->width;
        $bottom = $box->y;
        $top = $box->y + $box->height;

        $path = $this->addPath()
            ->moveTo($left + $radius, $top)
            ->lineTo($right - $radius, $top)
            ->curveTo(
                $right - $radius + $controlOffset,
                $top,
                $right,
                $top - $radius + $controlOffset,
                $right,
                $top - $radius,
            )
            ->lineTo($right, $bottom + $radius)
            ->curveTo(
                $right,
                $bottom + $radius - $controlOffset,
                $right - $radius + $controlOffset,
                $bottom,
                $right - $radius,
                $bottom,
            )
            ->lineTo($left + $radius, $bottom)
            ->curveTo(
                $left + $radius - $controlOffset,
                $bottom,
                $left,
                $bottom + $radius - $controlOffset,
                $left,
                $bottom + $radius,
            )
            ->lineTo($left, $top - $radius)
            ->curveTo(
                $left,
                $top - $radius + $controlOffset,
                $left + $radius - $controlOffset,
                $top,
                $left + $radius,
                $top,
            )
            ->close();

        $this->finishClosedPath($path, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addCircle(
        float $centerX,
        float $centerY,
        float $radius,
        ?float $strokeWidth = 1.0,
        ?Color $strokeColor = null,
        ?Color $fillColor = null,
        ?Opacity $opacity = null,
    ): void {
        if ($radius <= 0) {
            throw new InvalidArgumentException('Circle radius must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Circle stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Circle requires either a stroke or a fill.');
        }

        $this->finishClosedPath(
            $this->buildEllipsePath($centerX, $centerY, $radius, $radius),
            $strokeWidth,
            $strokeColor,
            $fillColor,
            $opacity,
        );
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
    ): void {
        if ($radiusX <= 0) {
            throw new InvalidArgumentException('Ellipse radiusX must be greater than zero.');
        }

        if ($radiusY <= 0) {
            throw new InvalidArgumentException('Ellipse radiusY must be greater than zero.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Ellipse stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Ellipse requires either a stroke or a fill.');
        }

        $this->finishClosedPath(
            $this->buildEllipsePath($centerX, $centerY, $radiusX, $radiusY),
            $strokeWidth,
            $strokeColor,
            $fillColor,
            $opacity,
        );
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
    ): void {
        if (count($points) < 3) {
            throw new InvalidArgumentException('Polygon requires at least three points.');
        }

        if ($strokeWidth !== null && $strokeWidth <= 0) {
            throw new InvalidArgumentException('Polygon stroke width must be greater than zero.');
        }

        if ($strokeWidth === null && $fillColor === null) {
            throw new InvalidArgumentException('Polygon requires either a stroke or a fill.');
        }

        $path = $this->addPath()->moveTo((float) $points[0][0], (float) $points[0][1]);

        foreach (array_slice($points, 1) as $point) {
            $path->lineTo((float) $point[0], (float) $point[1]);
        }

        $path->close();

        $this->finishClosedPath($path, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function addArrow(
        Position $from,
        Position $to,
        float $strokeWidth = 1.0,
        ?Color $color = null,
        ?Opacity $opacity = null,
        float $headLength = 10.0,
        float $headWidth = 8.0,
    ): void {
        if ($strokeWidth <= 0) {
            throw new InvalidArgumentException('Arrow stroke width must be greater than zero.');
        }

        if ($headLength <= 0) {
            throw new InvalidArgumentException('Arrow head length must be greater than zero.');
        }

        if ($headWidth <= 0) {
            throw new InvalidArgumentException('Arrow head width must be greater than zero.');
        }

        $dx = $to->x - $from->x;
        $dy = $to->y - $from->y;
        $length = hypot($dx, $dy);

        if ($length <= 0.0) {
            throw new InvalidArgumentException('Arrow requires distinct start and end points.');
        }

        $usableHeadLength = min($headLength, $length);
        $ux = $dx / $length;
        $uy = $dy / $length;
        $baseX = $to->x - ($ux * $usableHeadLength);
        $baseY = $to->y - ($uy * $usableHeadLength);
        $perpX = -$uy;
        $perpY = $ux;
        $halfHeadWidth = $headWidth / 2;
        $leftX = $baseX + ($perpX * $halfHeadWidth);
        $leftY = $baseY + ($perpY * $halfHeadWidth);
        $rightX = $baseX - ($perpX * $halfHeadWidth);
        $rightY = $baseY - ($perpY * $halfHeadWidth);

        $this->addLine($from, new Position($baseX, $baseY), $strokeWidth, $color, $opacity);
        $this->addPolygon(
            [
                [$to->x, $to->y],
                [$leftX, $leftY],
                [$rightX, $rightY],
            ],
            null,
            null,
            $color,
            $opacity,
        );
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
    ): void {
        if ($points < 3) {
            throw new InvalidArgumentException('Star requires at least three points.');
        }

        if ($outerRadius <= 0) {
            throw new InvalidArgumentException('Star outer radius must be greater than zero.');
        }

        if ($innerRadius <= 0) {
            throw new InvalidArgumentException('Star inner radius must be greater than zero.');
        }

        if ($innerRadius >= $outerRadius) {
            throw new InvalidArgumentException('Star inner radius must be smaller than the outer radius.');
        }

        $starPoints = [];
        $step = M_PI / $points;
        $startAngle = -M_PI / 2;

        for ($index = 0; $index < $points * 2; $index++) {
            $radius = $index % 2 === 0 ? $outerRadius : $innerRadius;
            $angle = $startAngle + ($index * $step);
            $starPoints[] = [
                $centerX + (cos($angle) * $radius),
                $centerY + (sin($angle) * $radius),
            ];
        }

        $this->addPolygon($starPoints, $strokeWidth, $strokeColor, $fillColor, $opacity);
    }

    public function resolveGraphicsStateName(?Opacity $opacity): ?string
    {
        if ($opacity === null) {
            return null;
        }

        $this->page->getDocument()->assertAllowsTransparency();

        return $this->page->addOpacityResource($opacity);
    }

    public function addGraphicElement(ContentInstruction $element): void
    {
        if ($this->page->getDocument()->isRenderingArtifactContext()) {
            $this->page->addContentElement(new RawInstruction('/Artifact BMC'));
            $this->page->addContentElement($element);
            $this->page->addContentElement(new RawInstruction('EMC'));

            return;
        }

        $this->assertAllowsGraphicElements();
        $this->page->addContentElement($element);
    }

    /**
     * @param callable(): void $renderer
     */
    public function renderDecorativeContent(callable $renderer): void
    {
        if ($this->page->getDocument()->getProfile()->requiresTaggedPdf()) {
            $this->page->getDocument()->renderInArtifactContext($renderer);

            return;
        }

        $renderer();
    }

    private function buildEllipsePath(float $centerX, float $centerY, float $radiusX, float $radiusY): PathBuilder
    {
        $controlOffsetX = $radiusX * 0.5522847498307936;
        $controlOffsetY = $radiusY * 0.5522847498307936;

        return $this->addPath()
            ->moveTo($centerX, $centerY + $radiusY)
            ->curveTo(
                $centerX + $controlOffsetX,
                $centerY + $radiusY,
                $centerX + $radiusX,
                $centerY + $controlOffsetY,
                $centerX + $radiusX,
                $centerY,
            )
            ->curveTo(
                $centerX + $radiusX,
                $centerY - $controlOffsetY,
                $centerX + $controlOffsetX,
                $centerY - $radiusY,
                $centerX,
                $centerY - $radiusY,
            )
            ->curveTo(
                $centerX - $controlOffsetX,
                $centerY - $radiusY,
                $centerX - $radiusX,
                $centerY - $controlOffsetY,
                $centerX - $radiusX,
                $centerY,
            )
            ->curveTo(
                $centerX - $radiusX,
                $centerY + $controlOffsetY,
                $centerX - $controlOffsetX,
                $centerY + $radiusY,
                $centerX,
                $centerY + $radiusY,
            )
            ->close();
    }

    public function finishClosedPath(
        PathBuilder $path,
        ?float $strokeWidth,
        ?Color $strokeColor,
        ?Color $fillColor,
        ?Opacity $opacity,
    ): void {
        if ($strokeWidth !== null && $fillColor !== null) {
            $path->fillAndStroke($strokeWidth, $strokeColor, $fillColor, $opacity);

            return;
        }

        if ($fillColor !== null) {
            $path->fill($fillColor, $opacity);

            return;
        }

        if ($strokeWidth === null) {
            throw new InvalidArgumentException('Closed path requires either a stroke or a fill.');
        }

        $path->stroke($strokeWidth, $strokeColor, $opacity);
    }

    private function assertAllowsGraphicElements(): void
    {
        $profile = $this->page->getDocument()->getProfile();

        if (!$profile->requiresArtifactGraphicElements()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires lines, shapes and paths to be rendered as artifacts in the current implementation.',
            $profile->name(),
        ));
    }
}
