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
        $options = TextOptions::make();

        self::assertSame(TextAlign::LEFT, $options->align);
    }

    public function testItAcceptsAnExplicitAlignment(): void
    {
        $options = TextOptions::make(align: TextAlign::CENTER);

        self::assertSame(TextAlign::CENTER, $options->align);
    }

    public function testItAcceptsAnExplicitWidth(): void
    {
        $options = TextOptions::make(width: 200.0);

        self::assertSame(200.0, $options->width);
    }

    public function testItAcceptsAnExplicitMaxWidth(): void
    {
        $options = TextOptions::make(maxWidth: 180.0);

        self::assertSame(180.0, $options->maxWidth);
    }

    public function testItAcceptsAnExplicitSpacingBefore(): void
    {
        $options = TextOptions::make(spacingBefore: 18.0);

        self::assertSame(18.0, $options->spacingBefore);
    }

    public function testItAcceptsAnExplicitFirstLineIndent(): void
    {
        $options = TextOptions::make(firstLineIndent: 24.0);

        self::assertSame(24.0, $options->firstLineIndent);
    }

    public function testItAcceptsAnExplicitHangingIndent(): void
    {
        $options = TextOptions::make(hangingIndent: 24.0);

        self::assertSame(24.0, $options->hangingIndent);
    }

    public function testItAcceptsALinkTarget(): void
    {
        $link = LinkTarget::externalUrl('https://example.com');
        $options = TextOptions::make(link: $link);

        self::assertSame($link, $options->link);
    }

    public function testItAcceptsATextLink(): void
    {
        $link = TextLink::externalUrl('https://example.com', 'Open Example', 'Open the example website');
        $options = TextOptions::make(link: $link);

        self::assertSame($link, $options->link);
    }

    public function testMakeFactoryBuildsTextOptions(): void
    {
        $options = TextOptions::make(
            width: 200.0,
            fontSize: 11.0,
            lineHeight: 14.0,
            align: TextAlign::CENTER,
        );

        self::assertSame(200.0, $options->width);
        self::assertSame(11.0, $options->fontSize);
        self::assertSame(14.0, $options->lineHeight);
        self::assertSame(TextAlign::CENTER, $options->align);
    }

    public function testBodyFactoryUsesBodyDefaults(): void
    {
        $options = TextOptions::body();

        self::assertSame(TextOptions::BODY_FONT_SIZE, $options->fontSize);
        self::assertSame(TextOptions::BODY_LINE_HEIGHT, $options->lineHeight);
    }

    public function testSmallFactoryUsesSmallDefaults(): void
    {
        $options = TextOptions::small();

        self::assertSame(TextOptions::SMALL_FONT_SIZE, $options->fontSize);
        self::assertSame(TextOptions::SMALL_LINE_HEIGHT, $options->lineHeight);
    }

    public function testCaptionFactoryUsesCaptionDefaults(): void
    {
        $options = TextOptions::caption();

        self::assertSame(TextOptions::CAPTION_FONT_SIZE, $options->fontSize);
        self::assertSame(TextOptions::CAPTION_LINE_HEIGHT, $options->lineHeight);
    }

    public function testHeadingFactoryUsesHeadingDefaults(): void
    {
        $options = TextOptions::heading();

        self::assertSame(TextOptions::HEADING_FONT_SIZE, $options->fontSize);
        self::assertSame(TextOptions::HEADING_LINE_HEIGHT, $options->lineHeight);
    }
}
