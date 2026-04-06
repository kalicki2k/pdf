<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Table\Rendering\CellRenderOptions;
use Kalle\Pdf\Document\Text\TextSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellRenderOptionsTest extends TestCase
{
    #[Test]
    public function it_stores_cell_render_options_values(): void
    {
        $remainingLines = [
            [
                'segments' => [new TextSegment('continued')],
                'justify' => false,
            ],
        ];

        $options = new CellRenderOptions(
            visibleRowspan: 2,
            renderText: false,
            renderTopBorder: false,
            renderBottomBorder: false,
            remainingLines: $remainingLines,
        );

        self::assertSame(2, $options->visibleRowspan);
        self::assertFalse($options->renderText);
        self::assertFalse($options->renderTopBorder);
        self::assertFalse($options->renderBottomBorder);
        self::assertSame($remainingLines, $options->remainingLines);
    }
}
