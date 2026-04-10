<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Layout\Table\Rendering\CellRenderResult;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CellRenderResultTest extends TestCase
{
    #[Test]
    public function it_stores_cell_render_result_values(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
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
