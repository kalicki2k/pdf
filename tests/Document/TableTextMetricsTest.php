<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableTextMetricsTest extends TestCase
{
    #[Test]
    public function it_resolves_alignment_height(): void
    {
        $metrics = new TableTextMetrics();

        self::assertSame(0.0, $metrics->resolveAlignmentHeight(0, 12, 16.0));
        self::assertSame(16.0, $metrics->resolveAlignmentHeight(1, 12, 16.0));
        self::assertSame(28.0, $metrics->resolveAlignmentHeight(2, 12, 16.0));
    }

    #[Test]
    public function it_resolves_content_height(): void
    {
        $metrics = new TableTextMetrics();

        self::assertSame(0.0, $metrics->resolveContentHeight(-1, 12, 16.0));
        self::assertSame(12.0, $metrics->resolveContentHeight(1, 12, 16.0));
        self::assertSame(32.0, $metrics->resolveContentHeight(2, 12, 16.0));
    }

    #[Test]
    public function it_resolves_fitting_line_count(): void
    {
        $metrics = new TableTextMetrics();

        self::assertSame(0, $metrics->resolveFittingLineCount(0.0, 16.0, 12));
        self::assertSame(1, $metrics->resolveFittingLineCount(10.0, 16.0, 12));
        self::assertSame(1, $metrics->resolveFittingLineCount(14.0, 16.0, 12));
        self::assertSame(3, $metrics->resolveFittingLineCount(48.0, 16.0, 12));
    }
}
