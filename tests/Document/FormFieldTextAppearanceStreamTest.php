<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldTextAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_form_field_text_appearance_stream(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldTextAppearanceStream(
            7,
            80,
            40,
            $font,
            'F1',
            12,
            ['Ada Lovelace', 'Grace Hopper'],
            Color::rgb(255, 0, 0),
        );

        $rendered = $stream->render();

        self::assertStringContainsString('7 0 obj', $rendered);
        self::assertStringContainsString('/Subtype /Form', $rendered);
        self::assertStringContainsString('/Font << /F1 9 0 R >>', $rendered);
        self::assertStringContainsString('/F1 12 Tf', $rendered);
        self::assertStringContainsString('1 0 0 rg', $rendered);
        self::assertStringContainsString('(Ada Lovelace) Tj', $rendered);
        self::assertStringContainsString('T*', $rendered);
    }
}
