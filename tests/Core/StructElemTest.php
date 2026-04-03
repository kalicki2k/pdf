<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use InvalidArgumentException;
use Kalle\Pdf\Core\StructElem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructElemTest extends TestCase
{
    #[Test]
    public function it_renders_an_empty_structure_element(): void
    {
        $structElem = new StructElem(4, 'Document');

        self::assertSame(
            "4 0 obj\n<< /Type /StructElem /S /Document /K [] >>\nendobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_adds_kids_and_renders_their_references(): void
    {
        $structElem = new StructElem(10, 'P');

        $result = $structElem->addKid(0)->addKid(1);

        self::assertSame($structElem, $result);
        self::assertSame(
            "10 0 obj\n<< /Type /StructElem /S /P /K [0 0 R 1 0 R] >>\nendobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_rejects_unknown_structure_tags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tag 'Table' is not allowed.");

        new StructElem(12, 'Table');
    }
}
