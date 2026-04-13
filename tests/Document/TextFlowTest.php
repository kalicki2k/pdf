<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TextFlow;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class TextFlowTest extends TestCase
{
    public function testItResolvesPlacementFromThePageContentArea(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A4(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $placement = $flow->placement(TextOptions::make(), StandardFontDefinition::from(StandardFont::HELVETICA));

        self::assertEqualsWithDelta(56.693, $placement['x'], 0.001);
        self::assertEqualsWithDelta(767.197, $placement['y'], 0.001);
    }

    public function testItResolvesPlacementToTheTopLeftPageCornerWithoutMargin(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A4(),
            ),
        );

        $placement = $flow->placement(TextOptions::make(), StandardFontDefinition::from(StandardFont::HELVETICA));

        self::assertSame(0.0, $placement['x']);
        self::assertEqualsWithDelta(PageSize::A4()->height() - 18.0, $placement['y'], 0.001);
    }

    public function testItWrapsTextWithinTheAvailableWidth(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A5(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $lines = $flow->wrapTextLines(
            'Hello world this wraps automatically across multiple lines.',
            TextOptions::make(fontName: StandardFont::HELVETICA->value),
            StandardFontDefinition::from(StandardFont::HELVETICA),
            Units::mm(20),
        );

        self::assertCount(2, $lines);
        self::assertSame('Hello world this wraps automatically', $lines[0]);
        self::assertSame('across multiple lines.', $lines[1]);
    }

    public function testItWrapsTextWithinAnExplicitBlockWidth(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A5(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $lines = $flow->wrapTextLines(
            'Hello world this wraps automatically across multiple lines.',
            TextOptions::make(
                fontName: StandardFont::HELVETICA->value,
                width: 120.0,
            ),
            StandardFontDefinition::from(StandardFont::HELVETICA),
            Units::mm(20),
        );

        self::assertCount(5, $lines);
        self::assertSame('Hello world', $lines[0]);
        self::assertSame('this wraps', $lines[1]);
        self::assertSame('automatically', $lines[2]);
        self::assertSame('across multiple', $lines[3]);
        self::assertSame('lines.', $lines[4]);
    }

    public function testItWrapsTextWithinAnExplicitMaxWidth(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A5(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $lines = $flow->wrapTextLines(
            'Hello world this wraps automatically across multiple lines.',
            TextOptions::make(
                fontName: StandardFont::HELVETICA->value,
                maxWidth: 120.0,
            ),
            StandardFontDefinition::from(StandardFont::HELVETICA),
            Units::mm(20),
        );

        self::assertCount(5, $lines);
        self::assertSame('Hello world', $lines[0]);
        self::assertSame('this wraps', $lines[1]);
        self::assertSame('automatically', $lines[2]);
        self::assertSame('across multiple', $lines[3]);
        self::assertSame('lines.', $lines[4]);
    }

    public function testItWrapsTheFirstParagraphLineWithinTheIndentedWidth(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A5(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $lines = $flow->wrapTextLines(
            'Hello world this wraps automatically across multiple lines.',
            TextOptions::make(
                fontName: StandardFont::HELVETICA->value,
                width: 160.0,
                firstLineIndent: 40.0,
            ),
            StandardFontDefinition::from(StandardFont::HELVETICA),
            Units::mm(20),
        );

        self::assertCount(5, $lines);
        self::assertSame('Hello world', $lines[0]);
        self::assertSame('this wraps', $lines[1]);
        self::assertSame('automatically', $lines[2]);
        self::assertSame('across multiple', $lines[3]);
        self::assertSame('lines.', $lines[4]);
    }

    public function testItWrapsFollowingParagraphLinesWithinTheHangingIndentedWidth(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A5(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $lines = $flow->wrapTextLines(
            'Hello world this wraps automatically across multiple lines.',
            TextOptions::make(
                fontName: StandardFont::HELVETICA->value,
                width: 160.0,
                hangingIndent: 40.0,
            ),
            StandardFontDefinition::from(StandardFont::HELVETICA),
            Units::mm(20),
        );

        self::assertCount(5, $lines);
        self::assertSame('Hello world this', $lines[0]);
        self::assertSame('wraps', $lines[1]);
        self::assertSame('automatically', $lines[2]);
        self::assertSame('across multiple', $lines[3]);
        self::assertSame('lines.', $lines[4]);
    }

    public function testItCalculatesTheNextCursorYFromLineHeightAndSpacingAfter(): void
    {
        $flow = new TextFlow(new Page(PageSize::A4()));

        $nextCursorY = $flow->nextCursorY(TextOptions::make(
            fontSize: 24,
            lineHeight: 28,
            spacingAfter: 12,
        ), 785.197);

        self::assertEqualsWithDelta(745.197, $nextCursorY, 0.001);
    }

    public function testItAppliesSpacingBeforeToImplicitPlacementOnly(): void
    {
        $flow = new TextFlow(
            new Page(
                size: PageSize::A4(),
                margin: Margin::all(Units::mm(20)),
            ),
        );

        $implicitPlacement = $flow->placement(TextOptions::make(
            spacingBefore: 12.0,
        ), StandardFontDefinition::from(StandardFont::HELVETICA));
        $explicitPlacement = $flow->placement(TextOptions::make(
            y: 700.0,
            spacingBefore: 12.0,
        ), StandardFontDefinition::from(StandardFont::HELVETICA));

        self::assertEqualsWithDelta(755.197, $implicitPlacement['y'], 0.001);
        self::assertSame(700.0, $explicitPlacement['y']);
    }
}
