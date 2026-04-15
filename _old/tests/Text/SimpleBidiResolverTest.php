<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\SimpleBidiResolver;
use Kalle\Pdf\Text\TextDirection;
use PHPUnit\Framework\TestCase;

final class SimpleBidiResolverTest extends TestCase
{
    public function testItReturnsNoRunsForAnEmptyString(): void
    {
        $resolver = new SimpleBidiResolver();

        self::assertSame([], $resolver->resolve(''));
    }

    public function testItKeepsPureLtrTextInASingleRun(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('Hello world');

        self::assertCount(1, $runs);
        self::assertSame('Hello world', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
    }

    public function testItKeepsPureRtlTextInASingleRun(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('שלום');

        self::assertCount(1, $runs);
        self::assertSame('שלום', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
    }

    public function testItSplitsMixedLtrAndRtlTextIntoDirectionalRuns(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('Hello שלום world');

        self::assertCount(3, $runs);
        self::assertSame('Hello ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('שלום ', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame('world', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItUsesTheBaseDirectionForNeutralLeadingCharacters(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve(' (שלום)', TextDirection::RTL);

        self::assertCount(1, $runs);
        self::assertSame(' )שלום(', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
    }

    public function testItKeepsWeakEuropeanNumbersWithTheResolvedDirection(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('שלום 123');

        self::assertCount(1, $runs);
        self::assertSame('שלום 123', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
    }

    public function testItMovesBackToLtrAfterAnExplicitLtrRun(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('שלום ABC 123');

        self::assertCount(2, $runs);
        self::assertSame('שלום ', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame('ABC 123', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
    }

    public function testItSupportsRliAndPdiAsExplicitRtlIsolates(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}שלום\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('שלום', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItSupportsLriAndPdiAsExplicitLtrIsolates(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("שלום \u{2066}abc\u{2069} עולם", TextDirection::RTL);

        self::assertCount(3, $runs);
        self::assertSame('שלום ', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame('abc', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(' עולם', $runs[2]->text);
        self::assertSame(TextDirection::RTL, $runs[2]->direction);
    }

    public function testItSupportsFirstStrongIsolates(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2068}שלום 123\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('שלום 123', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItAssignsBracketNeutralsToTheInnerStrongDirection(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('abc (שלום) def');

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(')שלום( ', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame('def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItTracksHigherEmbeddingLevelsForExplicitEmbeddings(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{202B}123 שלום\u{202C} def");

        self::assertCount(4, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(0, $runs[0]->embeddingLevel);
        self::assertSame('שלום', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame('123 ', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
        self::assertSame(1, $runs[2]->embeddingLevel);
        self::assertSame(' def', $runs[3]->text);
        self::assertSame(TextDirection::LTR, $runs[3]->direction);
        self::assertSame(0, $runs[3]->embeddingLevel);
    }

    public function testItKeepsEuropeanNumberSeparatorsInsideAnLtrNumericRun(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('abc 12-34,56 def');

        self::assertCount(1, $runs);
        self::assertSame('abc 12-34,56 def', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
    }

    public function testItResolvesArabicIndicDigitsAsRtl(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve('שלום ١٢٣');

        self::assertCount(1, $runs);
        self::assertSame('שלום ١٢٣', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
    }

    public function testItAttachesNonSpacingMarksToThePreviousDirection(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc\u{0301} שלום");

        self::assertCount(2, $runs);
        self::assertSame("abc\u{0301} ", $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('שלום', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
    }

    public function testItResolvesBracketNeutralsInsideAnRtlIsolateFromTheIsolateLevel(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}(שלום)\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(')שלום(', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItDoesNotLeakOuterStrongDirectionsIntoAnLtrIsolate(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("שלום \u{2066}(abc)\u{2069} עולם", TextDirection::RTL);

        self::assertCount(3, $runs);
        self::assertSame('שלום ', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame('(abc)', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(2, $runs[1]->embeddingLevel);
        self::assertSame(' עולם', $runs[2]->text);
        self::assertSame(TextDirection::RTL, $runs[2]->direction);
    }

    public function testItDoesNotResolveInnerIsolateNeutralsFromOuterStrongText(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}(123)\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('(123)', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItKeepsNestedIsolateResolutionLocalToTheInnermostSequence(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}שלום \u{2066}(12-34)\u{2069}\u{2069} def");

        self::assertCount(4, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('(12-34)', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(2, $runs[1]->embeddingLevel);
        self::assertSame('שלום ', $runs[2]->text);
        self::assertSame(TextDirection::RTL, $runs[2]->direction);
        self::assertSame(1, $runs[2]->embeddingLevel);
        self::assertSame(' def', $runs[3]->text);
        self::assertSame(TextDirection::LTR, $runs[3]->direction);
    }

    public function testItResolvesWhitespaceAndNeutralsFromResolvedDirectionsInsideAnIsolate(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}123 - 456\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('123 - 456', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItKeepsArabicNumberSeparatorsInsideAnArabicIndicNumericRun(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}١٢٣\u{066C}٤٥٦\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame("١٢٣\u{066C}٤٥٦", $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItResolvesEuropeanNumbersNearArabicIndicNumbersAsRtlWithinTheSequence(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}١٢٣ 456\u{2069} def");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('١٢٣ 456', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItAppliesLroAsATrueDirectionalOverride(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("שלום \u{202D}123 אבג\u{202C} עולם", TextDirection::RTL);

        self::assertCount(3, $runs);
        self::assertSame('שלום ', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame('123 אבג', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame(2, $runs[1]->embeddingLevel);
        self::assertSame(' עולם', $runs[2]->text);
        self::assertSame(TextDirection::RTL, $runs[2]->direction);
    }

    public function testItAppliesRloAsATrueDirectionalOverride(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{202E}DEF 123\u{202C} ghi");

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame('DEF 123', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(1, $runs[1]->embeddingLevel);
        self::assertSame(' ghi', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItUsesLrmAsALocalStrongMarkWithoutChangingFollowingBaseContext(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("שלום \u{200E}(123) עולם", TextDirection::RTL);

        self::assertCount(5, $runs);
        self::assertSame('שלום )', $runs[0]->text);
        self::assertSame(TextDirection::RTL, $runs[0]->direction);
        self::assertSame('123', $runs[1]->text);
        self::assertSame(TextDirection::LTR, $runs[1]->direction);
        self::assertSame('(', $runs[2]->text);
        self::assertSame(TextDirection::RTL, $runs[2]->direction);
        self::assertSame(' ', $runs[3]->text);
        self::assertSame(TextDirection::LTR, $runs[3]->direction);
        self::assertSame('עולם', $runs[4]->text);
        self::assertSame(TextDirection::RTL, $runs[4]->direction);
    }

    public function testItUsesRlmAsALocalStrongMarkWithoutChangingFollowingBaseContext(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{200F}(שלום) def", TextDirection::LTR);

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(')שלום( ', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame('def', $runs[2]->text);
        self::assertSame(TextDirection::LTR, $runs[2]->direction);
    }

    public function testItKeepsAdjacentIsolatesAsSeparateRunsEvenWhenDirectionAndLevelMatch(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2066}DEF\u{2069}\u{2066}GHI\u{2069} jkl");

        self::assertCount(4, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame('DEF', $runs[1]->text);
        self::assertSame(1, $runs[1]->isolateSequence);
        self::assertSame('GHI', $runs[2]->text);
        self::assertSame(2, $runs[2]->isolateSequence);
        self::assertSame(' jkl', $runs[3]->text);
    }

    public function testItKeepsNestedIsolateSegmentsDistinctDuringVisualReordering(): void
    {
        $resolver = new SimpleBidiResolver();
        $runs = $resolver->resolve("abc \u{2067}שלום \u{2066}XYZ\u{2069}\u{2067}אבג\u{2069}\u{2069} def");

        self::assertCount(5, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame('XYZ', $runs[1]->text);
        self::assertSame(2, $runs[1]->isolateSequence);
        self::assertSame('אבג', $runs[2]->text);
        self::assertSame(3, $runs[2]->isolateSequence);
        self::assertSame('שלום ', $runs[3]->text);
        self::assertSame(1, $runs[3]->isolateSequence);
        self::assertSame(' def', $runs[4]->text);
    }
}
