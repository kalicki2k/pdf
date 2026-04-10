<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\TableOfContents;

use Kalle\Pdf\Internal\Document\TableOfContents\TableOfContentsLeaderStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableOfContentsLeaderStyleTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_leader_styles(): void
    {
        self::assertSame(
            [
                TableOfContentsLeaderStyle::DOTS,
                TableOfContentsLeaderStyle::DASHES,
                TableOfContentsLeaderStyle::NONE,
            ],
            TableOfContentsLeaderStyle::cases(),
        );
    }
}
