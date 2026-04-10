<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Content;

use Kalle\Pdf\Object\StreamLengthObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamLengthObjectTest extends TestCase
{
    #[Test]
    public function it_renders_the_current_stream_length_as_a_plain_indirect_object(): void
    {
        $object = new StreamLengthObject(9);
        $object->setLength(42);

        self::assertSame("9 0 obj\n42\nendobj\n", $object->render());
    }
}
