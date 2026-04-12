<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TextSegmentTest extends TestCase
{
    public function testItProvidesExplicitPlainAndLinkFactories(): void
    {
        $plain = TextSegment::plain('Hello');
        $link = TextSegment::link('Docs', TextLink::externalUrl('https://example.com/docs', 'Open Docs', 'Read docs'));

        self::assertSame('Hello', $plain->text);
        self::assertNull($plain->link);
        self::assertSame('Docs', $link->text);
        self::assertInstanceOf(TextLink::class, $link->link);
        self::assertSame('Open Docs', $link->link->contents);
        self::assertSame('Read docs', $link->link->accessibleLabel);
    }
}
