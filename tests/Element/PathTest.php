<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Element;

use Kalle\Pdf\Element\Path;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    #[Test]
    public function it_renders_a_stroked_path(): void
    {
        $path = new Path(['20 200 m', '60 240 l', '100 200 l', '60 160 l', 'h'], 1.5, '1 0 0 RG');

        self::assertSame(
            "q\n"
            . "1 0 0 RG\n"
            . "1.5 w\n"
            . "20 200 m\n"
            . "60 240 l\n"
            . "100 200 l\n"
            . "60 160 l\n"
            . "h\n"
            . "S\n"
            . "Q",
            $path->render(),
        );
    }

    #[Test]
    public function it_renders_a_filled_path(): void
    {
        $path = new Path(['20 200 m', '60 240 l', '100 200 l', '60 160 l', 'h'], null, null, '0.9 g', null, 'f');

        self::assertSame(
            "q\n"
            . "0.9 g\n"
            . "20 200 m\n"
            . "60 240 l\n"
            . "100 200 l\n"
            . "60 160 l\n"
            . "h\n"
            . "f\n"
            . "Q",
            $path->render(),
        );
    }
}
