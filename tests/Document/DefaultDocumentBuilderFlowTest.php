<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontDefinition;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderFlowTest extends TestCase
{
    public function testParagraphUsesTheFlowingTextPath(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->text('Hello world this wraps automatically across multiple lines.')
            ->text('After paragraph')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 520.583 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.983 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 477.383 Td\n[", $document->pages[0]->contents);
    }

    public function testItWritesTextColorOperatorsIntoPageContents(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Gray', TextOptions::make(
                color: Color::gray(0.5),
            ))
            ->text('CMYK', TextOptions::make(
                bottom: 680.0,
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
            ->text("Line 1\nLine 2", TextOptions::make(
                left: 72.0,
                bottom: 720.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->text('After', TextOptions::make(
                left: 72.0,
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
            ->text("Line 1\n\nLine 2", TextOptions::make(
                left: 72.0,
                bottom: 720.0,
                fontSize: 10.0,
                lineHeight: 12.0,
            ))
            ->text('After', TextOptions::make(
                left: 72.0,
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
            ->text('Headline', TextOptions::make(
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
            ->text('Body', TextOptions::make(
                fontSize: 18.0,
            ))
            ->build();

        self::assertStringContainsString("56.693 680 m\n200 680 l", $document->pages[0]->contents);
        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 662 Td\n[", $document->pages[0]->contents);
    }

    public function testItPlacesImplicitTextBelowThePreviousTable(): void
    {
        $table = Table::define(
            TableColumn::proportional(1.0),
        )->withRows(
            TableRow::fromTexts('Cell'),
        );

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(Units::mm(20)))
            ->table($table)
            ->text('Body')
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 498.183 Td\n[", $document->pages[0]->contents);
        self::assertStringNotContainsString("BT\n/F1 18 Tf\n56.693 516.183 Td\n[", $document->pages[0]->contents);
    }

    public function testItSplitsFlowTextAcrossOverflowPagesLineByLine(): void
    {
        $lines = [];

        for ($index = 1; $index <= 20; $index++) {
            $lines[] = 'Item ' . $index;
        }

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->text(implode("\n", $lines), TextOptions::make(
                fontSize: 18.0,
                lineHeight: 18.0,
            ))
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(10, substr_count($document->pages[0]->contents, "BT\n/F1 18 Tf\n"));
        self::assertSame(10, substr_count($document->pages[1]->contents, "BT\n/F1 18 Tf\n"));
        self::assertStringContainsString('[<49> <74> 14 <65> <6d> <20> <32> <30>] TJ', $document->pages[1]->contents);
    }

    public function testItCanDisableAutomaticFlowTextPageBreaks(): void
    {
        $lines = [];

        for ($index = 1; $index <= 20; $index++) {
            $lines[] = 'Item ' . $index;
        }

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A8())
                ->margin(Margin::all(10.0))
                ->disableAutoPageBreak()
                ->text(implode("\n", $lines), TextOptions::make(
                    fontSize: 18.0,
                    lineHeight: 18.0,
                ));
            self::fail('Expected coded text layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TEXT_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Automatic page breaks are disabled and the text block does not fit in the remaining page space.',
                $exception->getMessage(),
            );
        }
    }

    public function testItSplitsTaggedFlowTextAcrossOverflowPagesWithoutDuplicatingDocumentChildren(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->text(implode("\n", array_fill(0, 20, 'Tagged flow text Привет')), TextOptions::make(
                tag: TaggedStructureTag::P,
                fontSize: 18.0,
                lineHeight: 18.0,
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
            ))
            ->build();

        self::assertGreaterThan(1, count($document->pages));
        self::assertGreaterThan(1, count($document->taggedTextBlocks));
        self::assertSame(
            [$document->taggedTextBlocks[0]->key],
            array_values(array_unique(array_map(
                static fn ($block): ?string => $block->key,
                $document->taggedTextBlocks,
            ))),
        );
        self::assertSame([$document->taggedTextBlocks[0]->key], $document->taggedDocumentChildKeys);
    }

    public function testItSplitsFlowTextLinesAcrossOverflowPagesLineByLine(): void
    {
        $lines = [];

        for ($index = 1; $index <= 20; $index++) {
            $lines[] = 'Line ' . $index;
        }

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->textLines($lines, TextOptions::make(
                fontSize: 18.0,
                lineHeight: 18.0,
            ))
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(10, substr_count($document->pages[0]->contents, "BT\n/F1 18 Tf\n"));
        self::assertSame(10, substr_count($document->pages[1]->contents, "BT\n/F1 18 Tf\n"));
        self::assertStringContainsString('<32> <30>] TJ', $document->pages[1]->contents);
    }

    public function testItCanDisableAutomaticFlowTextLinesPageBreaks(): void
    {
        $lines = [];

        for ($index = 1; $index <= 20; $index++) {
            $lines[] = 'Line ' . $index;
        }

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A8())
                ->margin(Margin::all(10.0))
                ->disableAutoPageBreak()
                ->textLines($lines, TextOptions::make(
                    fontSize: 18.0,
                    lineHeight: 18.0,
                ));
            self::fail('Expected coded text layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TEXT_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Automatic page breaks are disabled and the text block does not fit in the remaining page space.',
                $exception->getMessage(),
            );
        }
    }

    public function testItSplitsFlowTextSegmentsAcrossOverflowPagesLineByLine(): void
    {
        $segments = [];

        for ($index = 1; $index <= 20; $index++) {
            $suffix = $index < 20 ? "\n" : '';
            $segments[] = TextSegment::link('Seg ' . $index . $suffix, TextLink::externalUrl('https://example.com/docs'));
        }

        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A8())
            ->margin(Margin::all(10.0))
            ->text($segments, TextOptions::make(
                fontSize: 18.0,
                lineHeight: 18.0,
            ))
            ->build();

        self::assertCount(2, $document->pages);
        self::assertCount(10, $document->pages[0]->annotations);
        self::assertCount(10, $document->pages[1]->annotations);
        self::assertSame('https://example.com/docs', $document->pages[1]->annotations[9]->target->externalUrlValue());
    }

    public function testItCanDisableAutomaticFlowTextSegmentPageBreaks(): void
    {
        $segments = [];

        for ($index = 1; $index <= 20; $index++) {
            $suffix = $index < 20 ? "\n" : '';
            $segments[] = TextSegment::plain('Seg ' . $index . $suffix);
        }

        try {
            DefaultDocumentBuilder::make()
                ->pageSize(PageSize::A8())
                ->margin(Margin::all(10.0))
                ->disableAutoPageBreak()
                ->text($segments, TextOptions::make(
                    fontSize: 18.0,
                    lineHeight: 18.0,
                ));
            self::fail('Expected coded text layout validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TEXT_LAYOUT_INVALID, $exception->error);
            self::assertSame(
                'Automatic page breaks are disabled and the text block does not fit in the remaining page space.',
                $exception->getMessage(),
            );
        }
    }

    public function testItAppliesSpacingBeforeToImplicitTextPlacement(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->margin(Margin::all(Units::mm(20)))
            ->text('Body', TextOptions::make(
                spacingBefore: 12.0,
            ))
            ->build();

        self::assertStringContainsString("BT\n/F1 18 Tf\n56.693 755.197 Td\n[", $document->pages[0]->contents);
    }

    public function testItDoesNotApplySpacingBeforeWhenYIsExplicit(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Body', TextOptions::make(
                bottom: 680.0,
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
            ->text('Centered', TextOptions::make(
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
            ->text('Centered', TextOptions::make(
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
            ->text('Centered', TextOptions::make(
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
            ->text('Right', TextOptions::make(
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
            ->text('Right', TextOptions::make(
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
            ->text('Right', TextOptions::make(
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
            ->text('one two three four five six seven eight', TextOptions::make(
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
            ->text('one two three four five six', TextOptions::make(
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
            ->text('one two three four five six', TextOptions::make(
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
            ->text('Hello world this wraps automatically across multiple lines.', TextOptions::make(
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
            ->text('Hello world this wraps automatically across multiple lines.', TextOptions::make(
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

    private function fontPath(): string
    {
        return dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';
    }
}
