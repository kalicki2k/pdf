<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\Contents;
use Kalle\Pdf\Core\Element;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentsTest extends TestCase
{
    #[Test]
    public function it_returns_itself_when_adding_an_element(): void
    {
        $contents = new Contents(8);

        $result = $contents->addElement($this->createElement('BT'));

        self::assertSame($contents, $result);
    }

    #[Test]
    public function it_renders_an_empty_stream_with_zero_length(): void
    {
        $contents = new Contents(8);

        self::assertSame(
            "8 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n",
            $contents->render(),
        );
    }

    #[Test]
    public function it_renders_all_elements_in_order_and_sets_the_stream_length(): void
    {
        $contents = new Contents(12);
        $contents->addElement($this->createElement('BT'));
        $contents->addElement($this->createElement('ET'));

        self::assertSame(
            "12 0 obj\n<< /Length 5 >>\nstream\nBT\nET\nendstream\nendobj\n",
            $contents->render(),
        );
    }

    private function createElement(string $renderedValue): Element
    {
        return new class ($renderedValue) extends Element {
            public function __construct(private readonly string $renderedValue)
            {
            }

            public function render(): string
            {
                return $this->renderedValue;
            }
        };
    }
}
