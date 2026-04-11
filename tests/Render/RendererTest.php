<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use Kalle\Pdf\Render\DocumentSerializationPlan;
use Kalle\Pdf\Render\FileStructure;
use Kalle\Pdf\Render\IndirectObject;
use Kalle\Pdf\Render\Renderer;
use Kalle\Pdf\Render\StringOutput;
use Kalle\Pdf\Render\Trailer;
use Kalle\Pdf\Version;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testItWritesAMinimalPdfDocument(): void
    {
        $renderer = new Renderer();
        $output = new StringOutput();
        $plan = new DocumentSerializationPlan(
            objects: [
                new IndirectObject(1, '<< /Type /Catalog /Pages 2 0 R >>'),
                new IndirectObject(2, '<< /Type /Pages /Count 0 /Kids [] >>'),
            ],
            fileStructure: new FileStructure(
                version: Version::V1_4,
                trailer: new Trailer(size: 3, rootObjectId: 1),
            ),
        );

        $renderer->write($plan, $output);

        $pdf = $output->contents();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString("1 0 obj\n", $pdf);
        self::assertStringContainsString("2 0 obj\n", $pdf);
        self::assertStringContainsString("xref\n", $pdf);
        self::assertStringContainsString("trailer\n", $pdf);
        self::assertStringContainsString('/Root 1 0 R', $pdf);
        self::assertStringContainsString("startxref\n", $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
    }
}
