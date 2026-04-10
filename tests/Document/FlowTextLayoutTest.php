<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Layout\Text\FlowTextLayout;
use Kalle\Pdf\Internal\Layout\Text\Input\FlowTextOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FlowTextLayoutTest extends TestCase
{
    #[Test]
    public function it_resolves_default_flow_text_layout_values(): void
    {
        $layout = FlowTextLayout::fromOptions(100.0, 10, new FlowTextOptions(), 1.2, 20.0);

        self::assertSame(12.0, $layout->lineHeight);
        self::assertSame(20.0, $layout->bottomMargin);
        self::assertNull($layout->maxLines);
    }

    #[Test]
    public function it_rejects_non_positive_paragraph_widths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph width must be greater than zero.');

        FlowTextLayout::fromOptions(0.0, 10, new FlowTextOptions(), 1.2, 20.0);
    }
}
