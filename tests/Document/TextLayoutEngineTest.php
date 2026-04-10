<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Layout\Text\TextLayoutEngine;
use Kalle\Pdf\Internal\Layout\Text\TextLayoutFontResolver;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Navigation\LinkTarget;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextLayoutEngineTest extends TestCase
{
    #[Test]
    public function it_rejects_invalid_layout_arguments(): void
    {
        $engine = $this->createEngine();

        try {
            $engine->layoutParagraphLines('Hello', 'Base', 10, 0.0);
            self::fail('Expected exception for non-positive paragraph width.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Paragraph width must be greater than zero.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max lines must be greater than zero.');

        $engine->layoutParagraphLines('Hello', 'Base', 10, 100.0, maxLines: 0);
    }

    #[Test]
    public function it_rejects_non_text_segments_in_paragraph_arrays(): void
    {
        $engine = $this->createEngine();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paragraph text arrays must contain only TextSegment instances.');

        /** @phpstan-ignore argument.type */
        $engine->layoutParagraphLines(['Hello'], 'Base', 10, 100.0);
    }

    #[Test]
    public function it_normalizes_strings_and_empty_runs_with_defaults(): void
    {
        $engine = $this->createEngine();
        $color = Color::gray(0.4);
        $opacity = Opacity::fill(0.5);

        $stringLines = $engine->layoutParagraphLines('Hello', 'Base', 1, 100.0, $color, $opacity);
        $emptyLines = $engine->layoutParagraphLines([], 'Base', 1, 100.0, $color, $opacity);

        self::assertCount(1, $stringLines);
        self::assertSame('Hello', $stringLines[0]['segments'][0]->text);
        self::assertSame('0.4 g', $stringLines[0]['segments'][0]->color?->renderNonStrokingOperator());
        self::assertSame('<< /ca 0.5 >>', $stringLines[0]['segments'][0]->opacity?->renderExtGStateDictionary());

        self::assertSame([['segments' => [], 'justify' => false]], $emptyLines);
    }

    #[Test]
    public function it_wraps_words_preserves_newlines_and_merges_compatible_segments(): void
    {
        $engine = $this->createEngine();
        $target = LinkTarget::externalUrl('https://example.com');
        $color = Color::rgb(255, 0, 0);

        $lines = $engine->layoutParagraphLines([
            new TextSegment('Hello', $color, link: $target, underline: true),
            new TextSegment(" world\nnext", $color, link: $target, underline: true),
        ], 'Base', 1, 100.0);

        self::assertCount(2, $lines);
        self::assertSame('Hello world', $lines[0]['segments'][0]->text);
        self::assertFalse($lines[0]['justify']);
        self::assertSame('next', $lines[1]['segments'][0]->text);
        self::assertFalse($lines[1]['justify']);
    }

    #[Test]
    public function it_breaks_long_words_and_can_count_wrapped_lines(): void
    {
        $engine = $this->createEngine();

        $lines = $engine->layoutParagraphLines('abcdef', 'Base', 1, 2.0);

        self::assertCount(3, $lines);
        self::assertSame('ab', $lines[0]['segments'][0]->text);
        self::assertTrue($lines[0]['justify']);
        self::assertSame('cd', $lines[1]['segments'][0]->text);
        self::assertTrue($lines[1]['justify']);
        self::assertSame('ef', $lines[2]['segments'][0]->text);
        self::assertFalse($lines[2]['justify']);
        self::assertSame(3, $engine->countParagraphLines('abcdef', 'Base', 1, 2.0));
    }

    #[Test]
    public function it_wraps_at_word_boundaries_when_the_next_word_alone_still_fits(): void
    {
        $engine = $this->createEngine();

        $lines = $engine->layoutParagraphLines('aa bbb', 'Base', 1, 3.0);

        self::assertCount(2, $lines);
        self::assertSame('aa', $lines[0]['segments'][0]->text);
        self::assertTrue($lines[0]['justify']);
        self::assertSame('bbb', $lines[1]['segments'][0]->text);
        self::assertFalse($lines[1]['justify']);
    }

    #[Test]
    public function it_trims_trailing_whitespace_from_lines(): void
    {
        $engine = $this->createEngine();

        $trimmedToEmpty = $engine->trimTrailingWhitespaceFromLine([
            new TextSegment('Hello'),
            new TextSegment('world'),
            new TextSegment('   '),
        ]);
        $trimmedPartially = $engine->trimTrailingWhitespaceFromLine([
            new TextSegment('Hello'),
            new TextSegment('world  '),
        ]);

        self::assertCount(2, $trimmedToEmpty);
        self::assertSame('Hello', $trimmedToEmpty[0]->text);
        self::assertSame('world', $trimmedToEmpty[1]->text);
        self::assertCount(2, $trimmedPartially);
        self::assertSame('world', $trimmedPartially[1]->text);
    }

    #[Test]
    public function it_clips_visible_lines_when_max_lines_is_reached(): void
    {
        $engine = $this->createEngine();

        $lines = $engine->layoutParagraphLines("one\ntwo\nthree", 'Base', 1, 100.0, maxLines: 2, overflow: TextOverflow::CLIP);

        self::assertCount(2, $lines);
        self::assertSame('one', $lines[0]['segments'][0]->text);
        self::assertSame('two', $lines[1]['segments'][0]->text);
        self::assertFalse($lines[0]['justify']);
        self::assertFalse($lines[1]['justify']);
    }

    #[Test]
    public function it_appends_an_ellipsis_and_falls_back_to_three_dots_when_needed(): void
    {
        $engine = $this->createEngine(supportsEllipsis: false);

        $lines = $engine->layoutParagraphLines('abcdef', 'Base', 1, 2.0, maxLines: 1, overflow: TextOverflow::ELLIPSIS);

        self::assertCount(1, $lines);
        self::assertSame('..', $lines[0]['segments'][0]->text);
        self::assertFalse($lines[0]['justify']);
    }

    #[Test]
    public function it_returns_the_trimmed_line_when_even_the_ellipsis_marker_does_not_fit(): void
    {
        $engine = $this->createEngine(supportsEllipsis: false);

        $lines = $engine->layoutParagraphLines('abcdef', 'Base', 1, 0.5, maxLines: 1, overflow: TextOverflow::ELLIPSIS);

        self::assertCount(1, $lines);
        self::assertSame([], $lines[0]['segments']);
        self::assertFalse($lines[0]['justify']);
    }

    #[Test]
    public function it_keeps_segments_separate_when_their_style_differs(): void
    {
        $engine = $this->createEngine();

        $lines = $engine->layoutParagraphLines([
            new TextSegment('Hello', bold: true),
            new TextSegment('World', italic: true),
        ], 'Base', 1, 100.0);

        self::assertCount(1, $lines);
        self::assertCount(2, $lines[0]['segments']);
        self::assertSame('Hello', $lines[0]['segments'][0]->text);
        self::assertSame('World', $lines[0]['segments'][1]->text);
    }

    #[Test]
    public function it_can_build_a_layout_engine_from_page_fonts(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $engine = TextLayoutEngine::forPageFonts(
            PageFonts::forPage($page),
        );

        $lines = $engine->layoutParagraphLines('Hello world', 'Helvetica', 12, 200.0);

        self::assertCount(1, $lines);
        self::assertSame('Hello world', $lines[0]['segments'][0]->text);
    }

    private function createEngine(bool $supportsEllipsis = true): TextLayoutEngine
    {
        return new TextLayoutEngine(
            TextLayoutFontResolver::fromCallables(
                static fn (string $fontName): FontDefinition => new class ($fontName, $supportsEllipsis) implements FontDefinition {
                    public function __construct(
                        private readonly string $fontName,
                        private readonly bool $supportsEllipsis,
                    ) {
                    }

                    public function getId(): int
                    {
                        return 1;
                    }

                    public function getBaseFont(): string
                    {
                        return $this->fontName;
                    }

                    public function supportsText(string $text): bool
                    {
                        return $text !== '…' || $this->supportsEllipsis;
                    }

                    public function encodeText(string $text): string
                    {
                        return $text;
                    }

                    public function measureTextWidth(string $text, float $size): float
                    {
                        return $this->characterCount($text) * $size;
                    }

                    public function render(): string
                    {
                        return '';
                    }

                    private function characterCount(string $text): int
                    {
                        return count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                    }
                },
                static function (string $baseFont, TextSegment $segment): string {
                    if ($segment->bold && $segment->italic) {
                        return $baseFont . '-BoldItalic';
                    }

                    if ($segment->bold) {
                        return $baseFont . '-Bold';
                    }

                    if ($segment->italic) {
                        return $baseFont . '-Italic';
                    }

                    return $baseFont;
                },
            ),
        );
    }
}
