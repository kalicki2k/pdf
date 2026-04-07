<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Structure;

use InvalidArgumentException;
use Kalle\Pdf\Structure\StructElem;
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
        $firstChild = new StructElem(11, 'Span');
        $secondChild = new StructElem(12, 'Span');

        $result = $structElem->addKid($firstChild)->addKid($secondChild);

        self::assertSame($structElem, $result);
        self::assertSame(
            "10 0 obj\n<< /Type /StructElem /S /P /K [11 0 R 12 0 R] >>\nendobj\n",
            $structElem->render(),
        );
    }

    #[Test]
    public function it_rejects_unknown_structure_tags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tag 'UnknownTag' is not allowed.");

        new StructElem(12, 'UnknownTag');
    }

    #[Test]
    public function it_accepts_table_structure_tags(): void
    {
        self::assertStringContainsString('/S /Table', (new StructElem(12, 'Table'))->render());
        self::assertStringContainsString('/S /TR', (new StructElem(13, 'TR'))->render());
        self::assertStringContainsString('/S /TH', (new StructElem(14, 'TH'))->render());
        self::assertStringContainsString('/S /TD', (new StructElem(15, 'TD'))->render());
        self::assertStringContainsString('/S /Figure', (new StructElem(16, 'Figure'))->render());
    }

    #[Test]
    public function it_renders_alt_text_for_structure_elements(): void
    {
        $structElem = new StructElem(12, 'Figure');
        $structElem->setAltText('Illustration');

        self::assertStringContainsString('/Alt (Illustration)', $structElem->render());
    }
}
