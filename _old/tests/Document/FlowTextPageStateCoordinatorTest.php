<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\FlowTextPageStateCoordinator;
use Kalle\Pdf\Document\TextFlow;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class FlowTextPageStateCoordinatorTest extends TestCase
{
    public function testItAppliesFlowTextResultToPageState(): void
    {
        $coordinator = new FlowTextPageStateCoordinator();
        $textFlow = new TextFlow(new Page(PageSize::A4()));
        $options = TextOptions::make(fontSize: 12, lineHeight: 14);

        $result = $coordinator->applyFlowTextResult(
            'BT',
            [],
            $textFlow,
            $options,
            700,
            2,
            [
                'contents' => 'ET',
                'annotations' => [],
                'textMarkedContentIds' => [],
            ],
            static fn (string $left, string $right): string => $left . "\n" . $right,
        );

        self::assertSame("BT\nET", $result['contents']);
        self::assertSame([], $result['annotations']);
        self::assertSame($textFlow->nextCursorY($options, 700, 2), $result['cursorY']);
        self::assertFalse($result['cursorYIsTopBoundary']);
    }
}
