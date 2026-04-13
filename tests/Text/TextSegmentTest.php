<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TextSegmentTest extends TestCase
{
    public function testItProvidesExplicitPlainAndLinkFactories(): void
    {
        $plain = TextSegment::plain('Hello');
        $options = TextOptions::make(fontName: 'Helvetica-Bold');
        $link = TextSegment::link('Docs', TextLink::externalUrl('https://example.com/docs', 'Open Docs', 'Read docs'), $options);

        self::assertSame('Hello', $plain->text);
        self::assertNull($plain->link);
        self::assertNull($plain->options);
        self::assertSame('Docs', $link->text);
        self::assertInstanceOf(TextLink::class, $link->link);
        self::assertSame($options, $link->options);
        self::assertSame('Open Docs', $link->link->contents);
        self::assertSame('Read docs', $link->link->accessibleLabel);
    }
}
