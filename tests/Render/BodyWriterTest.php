<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Render\BodyWriter;
use Kalle\Pdf\Render\DocumentSerializationPlan;
use Kalle\Pdf\Render\FileStructure;
use Kalle\Pdf\Render\IndirectObject;
use Kalle\Pdf\Render\StringOutput;
use Kalle\Pdf\Render\Trailer;
use Kalle\Pdf\Version;
use PHPUnit\Framework\TestCase;

final class BodyWriterTest extends TestCase
{
    public function testItWritesIndirectObjectsAndReturnsTheirOffsets(): void
    {
        $writer = new BodyWriter();
        $output = new StringOutput();
        $plan = new DocumentSerializationPlan(
            objects: [
                new IndirectObject(1, '<< /Type /Catalog /Pages 2 0 R >>'),
                new IndirectObject(2, "<< /Type /Pages /Count 0 /Kids [] >>\n"),
            ],
            fileStructure: new FileStructure(
                version: Version::V1_4,
                trailer: new Trailer(size: 3, rootObjectId: 1),
            ),
        );

        $offsets = $writer->write($plan, $output);

        self::assertSame([1 => 0, 2 => 49], $offsets);
        self::assertSame(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n",
            $output->contents(),
        );
    }
}
