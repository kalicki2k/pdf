<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\SimpleScriptResolver;
use Kalle\Pdf\Text\TextDirection;
use Kalle\Pdf\Text\TextScript;
use PHPUnit\Framework\TestCase;

final class SimpleScriptResolverTest extends TestCase
{
    public function testItKeepsPureLatinTextInASingleScriptRun(): void
    {
        $resolver = new SimpleScriptResolver();
        $runs = $resolver->resolve('Hello world');

        self::assertCount(1, $runs);
        self::assertSame('Hello world', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(TextScript::LATIN, $runs[0]->script);
    }

    public function testItDetectsArabicAndHebrewRuns(): void
    {
        $resolver = new SimpleScriptResolver();
        $runs = $resolver->resolve('שלום عربي');

        self::assertCount(2, $runs);
        self::assertSame('שלום ', $runs[0]->text);
        self::assertSame(TextScript::HEBREW, $runs[0]->script);
        self::assertSame('عربي', $runs[1]->text);
        self::assertSame(TextScript::ARABIC, $runs[1]->script);
    }

    public function testItSplitsMixedLatinAndHebrewByScriptAndDirection(): void
    {
        $resolver = new SimpleScriptResolver();
        $runs = $resolver->resolve('Hello שלום');

        self::assertCount(2, $runs);
        self::assertSame('Hello ', $runs[0]->text);
        self::assertSame(TextDirection::LTR, $runs[0]->direction);
        self::assertSame(TextScript::LATIN, $runs[0]->script);
        self::assertSame('שלום', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(TextScript::HEBREW, $runs[1]->script);
    }

    public function testItKeepsMirroredCommonCharactersAttachedToRtlScriptRuns(): void
    {
        $resolver = new SimpleScriptResolver();
        $runs = $resolver->resolve('abc (שלום) def');

        self::assertCount(3, $runs);
        self::assertSame('abc ', $runs[0]->text);
        self::assertSame(')שלום( ', $runs[1]->text);
        self::assertSame(TextDirection::RTL, $runs[1]->direction);
        self::assertSame(TextScript::HEBREW, $runs[1]->script);
        self::assertSame('def', $runs[2]->text);
    }
}
