<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Form\RadioButtonAppearanceStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_checked_radio_button_appearance_stream(): void
    {
        $stream = new RadioButtonAppearanceStream(7, 12, true);

        self::assertStringContainsString('/Subtype /Form', $stream->render());
        self::assertStringContainsString('6 11.5 m', $stream->render());
        self::assertStringContainsString("\nf\nendstream", $stream->render());
    }
}
