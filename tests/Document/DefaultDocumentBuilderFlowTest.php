<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderFlowTest extends TestCase
{
    public function testParagraphUsesTheFlowingTextPath(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->paragraph('Hello world this wraps automatically across multiple lines.')
            ->paragraph('After paragraph')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 520.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.983 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 477.383 Td\n[", $document->pages[0]->contents);
    }

    public function testItWritesTextColorOperatorsIntoPageContents(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Gray', new TextOptions(
                color: Color::gray(0.5),
            ))
            ->text('CMYK', new TextOptions(
                y: 680.0,
                color: Color::cmyk(0.1, 0.2, 0.3, 0.4),
            ))
            ->build();

        self::assertStringContainsString("BT\n0.5 g\n/F1 18 Tf\n0 823.89 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n0.1 0.2 0.3 0.4 k\n/F1 18 Tf\n0 680 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString('] TJ', $document->pages[0]->contents);
    }

    public function testItUsesTheConfiguredFirstPageMarginForImplicitTextPlacement(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 767.197 Td\n[", $document->pages[0]->contents);
        self::assertSame(Units::mm(20), $document->pages[0]->margin?->top);
    }

    public function testItAdvancesImplicitTextPlacementBelowThePreviousLine(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Line 1')
            ->text('Line 2')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 767.197 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 745.597 Td\n[", $document->pages[0]->contents);
    }

    public function testItWrapsTextWithinTheCurrentContentArea(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 520.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.983 Td\n[", $document->pages[0]->contents);
    }

    public function testItContinuesBelowAllWrappedLinesForTheNextImplicitTextCall(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.')
            ->text('After wrap')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 477.383 Td\n[", $document->pages[0]->contents);
    }

    public function testItAppliesSpacingAfterToTheNextImplicitTextCall(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Headline', new TextOptions(
                fontSize: 24,
                lineHeight: 28,
                spacingAfter: 12,
            ))
            ->text('Body')
            ->build();

        self::assertStringContainsString("BT\n/F1 24 Tf\n56.693 761.197 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 721.197 Td\n[", $document->pages[0]->contents);
    }
}
