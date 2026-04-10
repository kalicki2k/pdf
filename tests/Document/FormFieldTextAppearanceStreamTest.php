<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Layout\HorizontalAlign;
use Kalle\Pdf\Layout\VerticalAlign;
use Kalle\Pdf\Render\StringPdfOutput;
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
            new UnicodeFontWidthUpdater(),
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
        self::assertStringContainsString('1 0 0 1 2.5 25.5 Tm', $rendered);
        self::assertStringContainsString('1 0 0 1 2.5 11.1 Tm', $rendered);
        self::assertStringContainsString('(Ada Lovelace) Tj', $rendered);
        self::assertStringContainsString('(Grace Hopper) Tj', $rendered);
    }

    #[Test]
    public function it_can_center_single_line_widget_text(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldTextAppearanceStream(
            7,
            80,
            20,
            $font,
            new UnicodeFontWidthUpdater(),
            'F1',
            12,
            ['Apply'],
            null,
            HorizontalAlign::CENTER,
            VerticalAlign::MIDDLE,
        );

        $rendered = $stream->render();
        $expectedX = 2.5 + max(0.0, ((80.0 - 5.0) - $font->measureTextWidth('Apply', 12)) / 2);

        self::assertStringContainsString(
            sprintf('1 0 0 1 %s 4 Tm', $this->format($expectedX)),
            $rendered,
        );
        self::assertStringContainsString('(Apply) Tj', $rendered);
    }

    #[Test]
    public function it_can_render_a_dropdown_indicator_for_choice_widgets(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldTextAppearanceStream(
            7,
            80,
            20,
            $font,
            new UnicodeFontWidthUpdater(),
            'F1',
            12,
            ['Germany'],
            null,
            HorizontalAlign::LEFT,
            VerticalAlign::MIDDLE,
            true,
        );

        $rendered = $stream->render();

        self::assertStringContainsString('66 2.5 m', $rendered);
        self::assertStringContainsString('66 17.5 l', $rendered);
        self::assertStringContainsString('70.2 11.6 m', $rendered);
        self::assertStringContainsString('73 8.4 l', $rendered);
        self::assertStringContainsString('75.8 11.6 l', $rendered);
        self::assertStringContainsString('(Germany) Tj', $rendered);
    }

    #[Test]
    public function it_writes_an_encrypted_text_field_appearance_stream_consistently(): void
    {
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);
        $stream = new FormFieldTextAppearanceStream(
            7,
            80,
            40,
            $font,
            new UnicodeFontWidthUpdater(),
            'F1',
            12,
            ['Ada Lovelace', 'Grace Hopper'],
            Color::rgb(255, 0, 0),
        );
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $stream->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($stream->render(), 7),
            $output->contents(),
        );
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
