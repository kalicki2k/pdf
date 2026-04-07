<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Table\Rendering\CellRenderResult;
use Kalle\Pdf\Document\Text\TextSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellRenderResultTest extends TestCase
{
    #[Test]
    public function it_stores_cell_render_result_values(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $remainingLines = [
            [
                'segments' => [new TextSegment('continued')],
                'justify' => false,
            ],
        ];

        $result = new CellRenderResult($page, $remainingLines);

        self::assertSame($page, $result->page);
        self::assertSame($remainingLines, $result->remainingLines);
    }
}
