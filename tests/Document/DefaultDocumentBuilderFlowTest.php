<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
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

    public function testSingleNewlinesAdvanceOnlyOneLineInTextBlocks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text("Line 1\nLine 2", new TextOptions(
                x: 72.0,
                y: 720.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->text('After', new TextOptions(
                x: 72.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->build();

        self::assertStringContainsString('72 720 Td', $document->pages[0]->contents);
        self::assertStringContainsString('72 696 Td', $document->pages[0]->contents);
        self::assertStringNotContainsString('72 684 Td', $document->pages[0]->contents);
    }

    public function testDoubleNewlinesStillProduceABlankLineInTextBlocks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text("Line 1\n\nLine 2", new TextOptions(
                x: 72.0,
                y: 720.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->text('After', new TextOptions(
                x: 72.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->build();

        self::assertStringContainsString('72 720 Td', $document->pages[0]->contents);
        self::assertStringContainsString('72 696 Td', $document->pages[0]->contents);
        self::assertStringContainsString('72 684 Td', $document->pages[0]->contents);
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

    public function testItPlacesImplicitTextBelowThePreviousLineGraphic(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->line(56.693, 680.0, 200.0, 680.0)
            ->text('Body', new TextOptions(
                fontSize: 18.0,
            ))
            ->build();

        self::assertStringContainsString("56.693 680 m\n200 680 l", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 662 Td\n[", $document->pages[0]->contents);
    }

    public function testItAppliesSpacingBeforeToImplicitTextPlacement(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Body', new TextOptions(
                spacingBefore: 12.0,
            ))
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 755.197 Td\n[", $document->pages[0]->contents);
    }

    public function testItDoesNotApplySpacingBeforeWhenYIsExplicit(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Body', new TextOptions(
                y: 680.0,
                spacingBefore: 12.0,
            ))
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n0 680 Td\n[", $document->pages[0]->contents);
    }

    public function testItCentersImplicitTextWithinTheAvailableLineWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Centered', new TextOptions(
                align: TextAlign::CENTER,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Centered', 18.0);
        $availableWidth = PageSize::A5()->width() - (Units::mm(20) * 2);
        $x = Units::mm(20) + (($availableWidth - $lineWidth) / 2);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItCentersTextWithinAnExplicitBlockWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Centered', new TextOptions(
                width: 200.0,
                align: TextAlign::CENTER,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Centered', 18.0);
        $x = Units::mm(20) + ((200.0 - $lineWidth) / 2);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItCentersTextWithinAnExplicitMaxWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Centered', new TextOptions(
                maxWidth: 200.0,
                align: TextAlign::CENTER,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Centered', 18.0);
        $x = Units::mm(20) + ((200.0 - $lineWidth) / 2);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItRightAlignsImplicitTextWithinTheAvailableLineWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Right', new TextOptions(
                align: TextAlign::RIGHT,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Right', 18.0);
        $availableWidth = PageSize::A5()->width() - (Units::mm(20) * 2);
        $x = Units::mm(20) + ($availableWidth - $lineWidth);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItRightAlignsTextWithinAnExplicitBlockWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Right', new TextOptions(
                width: 150.0,
                align: TextAlign::RIGHT,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Right', 18.0);
        $x = Units::mm(20) + (150.0 - $lineWidth);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItRightAlignsTextWithinAnExplicitMaxWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Right', new TextOptions(
                maxWidth: 150.0,
                align: TextAlign::RIGHT,
            ))
            ->build();

        $font = StandardFontDefinition::from(StandardFont::HELVETICA);
        $lineWidth = $font->measureTextWidth('Right', 18.0);
        $x = Units::mm(20) + (150.0 - $lineWidth);

        self::assertStringContainsString(
            "BT\n/F1 18 Tf\n" . $this->formatNumber($x) . " 520.583 Td\n[",
            $document->pages[0]->contents,
        );
    }

    public function testItJustifiesWrappedLinesExceptTheParagraphEnd(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('one two three four five six seven eight', new TextOptions(
                fontName: StandardFont::COURIER->value,
                align: TextAlign::JUSTIFY,
            ))
            ->build();

        self::assertMatchesRegularExpression(
            '/56\.693 520\.583 Td\\n\\[.*-[1-9][0-9]*.*\\] TJ/s',
            $document->pages[0]->contents,
        );
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.983 Td\n(seven eight) Tj\nET", $document->pages[0]->contents);
    }

    public function testItJustifiesTextWithinAnExplicitBlockWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('one two three four five six', new TextOptions(
                fontName: StandardFont::COURIER->value,
                width: 170.0,
                align: TextAlign::JUSTIFY,
            ))
            ->build();

        self::assertMatchesRegularExpression(
            '/56\.693 520\.583 Td\\n\\[.*-[1-9][0-9]*.*\\] TJ/s',
            $document->pages[0]->contents,
        );
    }

    public function testItJustifiesTextWithinAnExplicitMaxWidth(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('one two three four five six', new TextOptions(
                fontName: StandardFont::COURIER->value,
                maxWidth: 170.0,
                align: TextAlign::JUSTIFY,
            ))
            ->build();

        self::assertMatchesRegularExpression(
            '/56\.693 520\.583 Td\\n\\[.*-[1-9][0-9]*.*\\] TJ/s',
            $document->pages[0]->contents,
        );
    }

    public function testItAppliesFirstLineIndentOnlyToTheParagraphStart(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.', new TextOptions(
                width: 160.0,
                firstLineIndent: 40.0,
            ))
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n96.693 520.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.983 Td\n[", $document->pages[0]->contents);
    }

    public function testItAppliesHangingIndentOnlyToFollowingParagraphLines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.', new TextOptions(
                width: 160.0,
                hangingIndent: 40.0,
            ))
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 520.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n96.693 498.983 Td\n[", $document->pages[0]->contents);
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
