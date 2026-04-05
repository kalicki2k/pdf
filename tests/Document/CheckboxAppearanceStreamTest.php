<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Form\CheckboxAppearanceStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckboxAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_checked_checkbox_appearance_stream(): void
    {
        $stream = new CheckboxAppearanceStream(7, 12, 12, true);

        self::assertStringContainsString('/Subtype /Form', $stream->render());
        self::assertStringContainsString('0 0 12 12 re', $stream->render());
        self::assertStringContainsString("S\nendstream", $stream->render());
    }
}
