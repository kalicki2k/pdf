<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\Element;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
    #[Test]
    public function it_sets_the_position_and_returns_itself(): void
    {
        $element = new class () extends Element {
            public function render(): string
            {
                return 'dummy';
            }
        };

        $result = $element->setPosition(12.5, 34.75);

        self::assertSame($element, $result);
        self::assertSame(12.5, $element->x);
        self::assertSame(34.75, $element->y);
    }
}
