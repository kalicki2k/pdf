<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Element\Raw;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawTest extends TestCase
{
    #[Test]
    public function it_renders_raw_content_unchanged(): void
    {
        $raw = new Raw("q\nBT\n(Hello) Tj\nET\nQ");

        self::assertSame("q\nBT\n(Hello) Tj\nET\nQ", $raw->render());
    }

    #[Test]
    public function it_inherits_position_handling_from_element(): void
    {
        $raw = new Raw('content');

        $result = $raw->setPosition(12.5, 34.75);

        self::assertSame($raw, $result);
        self::assertSame(12.5, $raw->x);
        self::assertSame(34.75, $raw->y);
    }
}
