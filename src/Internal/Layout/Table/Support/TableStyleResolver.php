<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Layout\Table\Support;

use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Style\Opacity;
use Kalle\Pdf\Table\Style\CellStyle;
use Kalle\Pdf\Table\Style\FooterStyle;
use Kalle\Pdf\Table\Style\HeaderStyle;
use Kalle\Pdf\Table\Style\RowStyle;
use Kalle\Pdf\Table\Style\TableBorder;
use Kalle\Pdf\Table\Style\TablePadding;
use Kalle\Pdf\Table\Style\TableStyle;
use Kalle\Pdf\Table\TableCell;

final class TableStyleResolver
{
    public function mergeTableStyle(TableStyle $base, TableStyle $override): TableStyle
    {
        return new TableStyle(
            padding: $override->padding ?? $base->padding,
            border: $override->border ?? $base->border,
            verticalAlign: $override->verticalAlign ?? $base->verticalAlign,
            fillColor: $override->fillColor ?? $base->fillColor,
            textColor: $override->textColor ?? $base->textColor,
        );
    }

    public function mergeRowStyle(?RowStyle $base, RowStyle $override): RowStyle
    {
        return $this->buildMergedRowStyle(RowStyle::class, $base, $override);
    }

    public function mergeHeaderStyle(?HeaderStyle $base, HeaderStyle $override): HeaderStyle
    {
        /** @var HeaderStyle $merged */
        $merged = $this->buildMergedRowStyle(HeaderStyle::class, $base, $override);

        return $merged;
    }

    public function mergeFooterStyle(?FooterStyle $base, FooterStyle $override): FooterStyle
    {
        /** @var FooterStyle $merged */
        $merged = $this->buildMergedRowStyle(FooterStyle::class, $base, $override);

        return $merged;
    }

    public function resolveCellStyle(
        TableStyle $tableStyle,
        ?RowStyle $rowStyle,
        ?HeaderStyle $headerStyle,
        TableCell $cell,
        bool $header,
        ?FooterStyle $footerStyle = null,
        bool $footer = false,
    ): ResolvedTableCellStyle {
        $resolvedRowStyle = $header
            ? $headerStyle
            : ($footer ? $footerStyle : $rowStyle);
        $cellStyle = $cell->style ?? new CellStyle();
        $rowPadding = $resolvedRowStyle?->padding;
        $rowFillColor = $resolvedRowStyle?->fillColor;
        $rowTextColor = $resolvedRowStyle?->textColor;
        $rowVerticalAlign = $resolvedRowStyle?->verticalAlign;
        $rowHorizontalAlign = $resolvedRowStyle?->horizontalAlign;
        $rowOpacity = $resolvedRowStyle?->opacity;
        $rowBorder = $resolvedRowStyle?->border;

        return new ResolvedTableCellStyle(
            $cellStyle->padding ?? $rowPadding ?? $tableStyle->padding ?? TablePadding::all(0.0),
            $cellStyle->fillColor ?? $rowFillColor ?? $tableStyle->fillColor,
            $cellStyle->textColor ?? $rowTextColor ?? $tableStyle->textColor,
            $cellStyle->verticalAlign ?? $rowVerticalAlign ?? $tableStyle->verticalAlign ?? VerticalAlign::TOP,
            $cellStyle->horizontalAlign ?? $rowHorizontalAlign ?? HorizontalAlign::LEFT,
            $cellStyle->opacity ?? $rowOpacity,
            $rowBorder,
            $cellStyle->border,
        );
    }

    public function resolveBorderSide(
        string $side,
        ?TableBorder $defaultBorder,
        ?TableBorder $rowBorder,
        ?TableBorder $cellBorder,
    ): ?ResolvedBorderSide {
        $applicableBorders = [];

        foreach ([$cellBorder, $rowBorder, $defaultBorder] as $border) {
            if ($border !== null && $border->isDefinedFor($side)) {
                $applicableBorders[] = $border;
            }
        }

        if ($applicableBorders === []) {
            return null;
        }

        $resolvedBorder = $applicableBorders[0];
        assert($resolvedBorder->width !== null);

        return new ResolvedBorderSide(
            $resolvedBorder->width,
            $this->firstDefinedBorderColor($applicableBorders),
            $this->firstDefinedBorderOpacity($applicableBorders),
        );
    }

    public function bordersAreEquivalent(
        ResolvedBorderSide $top,
        ResolvedBorderSide $right,
        ResolvedBorderSide $bottom,
        ResolvedBorderSide $left,
    ): bool {
        return $top == $right && $right == $bottom && $bottom == $left;
    }

    /**
     * @template T of RowStyle
     * @param class-string<T> $styleClass
     * @param ?T $base
     * @param T $override
     * @return T
     */
    private function buildMergedRowStyle(string $styleClass, ?RowStyle $base, RowStyle $override): RowStyle
    {
        return new $styleClass(
            horizontalAlign: $override->horizontalAlign ?? $base?->horizontalAlign,
            verticalAlign: $override->verticalAlign ?? $base?->verticalAlign,
            padding: $override->padding ?? $base?->padding,
            fillColor: $override->fillColor ?? $base?->fillColor,
            textColor: $override->textColor ?? $base?->textColor,
            opacity: $override->opacity ?? $base?->opacity,
            border: $override->border ?? $base?->border,
        );
    }

    /**
     * @param list<TableBorder> $borders
     */
    private function firstDefinedBorderColor(array $borders): ?Color
    {
        foreach ($borders as $border) {
            if ($border->color !== null) {
                return $border->color;
            }
        }

        return null;
    }

    /**
     * @param list<TableBorder> $borders
     */
    private function firstDefinedBorderOpacity(array $borders): ?Opacity
    {
        foreach ($borders as $border) {
            if ($border->opacity !== null) {
                return $border->opacity;
            }
        }

        return null;
    }
}
