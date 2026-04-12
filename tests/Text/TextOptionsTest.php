<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Text\TextAlign;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class TextOptionsTest extends TestCase
{
    public function testItDefaultsToLeftAlignment(): void
    {
        $options = new TextOptions();

        self::assertSame(TextAlign::LEFT, $options->align);
    }

    public function testItAcceptsAnExplicitAlignment(): void
    {
        $options = new TextOptions(align: TextAlign::CENTER);

        self::assertSame(TextAlign::CENTER, $options->align);
    }

    public function testItAcceptsAnExplicitWidth(): void
    {
        $options = new TextOptions(width: 200.0);

        self::assertSame(200.0, $options->width);
    }

    public function testItAcceptsAnExplicitMaxWidth(): void
    {
        $options = new TextOptions(maxWidth: 180.0);

        self::assertSame(180.0, $options->maxWidth);
    }

    public function testItAcceptsAnExplicitSpacingBefore(): void
    {
        $options = new TextOptions(spacingBefore: 18.0);

        self::assertSame(18.0, $options->spacingBefore);
    }

    public function testItAcceptsAnExplicitFirstLineIndent(): void
    {
        $options = new TextOptions(firstLineIndent: 24.0);

        self::assertSame(24.0, $options->firstLineIndent);
    }

    public function testItAcceptsAnExplicitHangingIndent(): void
    {
        $options = new TextOptions(hangingIndent: 24.0);

        self::assertSame(24.0, $options->hangingIndent);
    }

    public function testItAcceptsALinkTarget(): void
    {
        $link = LinkTarget::externalUrl('https://example.com');
        $options = new TextOptions(link: $link);

        self::assertSame($link, $options->link);
    }

    public function testItAcceptsATextLink(): void
    {
        $link = TextLink::externalUrl('https://example.com', 'Open Example', 'Open the example website');
        $options = new TextOptions(link: $link);

        self::assertSame($link, $options->link);
    }
}
