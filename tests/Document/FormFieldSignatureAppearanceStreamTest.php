<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Form\FormFieldSignatureAppearanceStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldSignatureAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_form_field_signature_appearance_stream(): void
    {
        $stream = new FormFieldSignatureAppearanceStream(7, 100, 30);

        $rendered = $stream->render();

        self::assertStringContainsString('7 0 obj', $rendered);
        self::assertStringContainsString('/Subtype /Form', $rendered);
        self::assertStringContainsString('/BBox [0 0 100 30]', $rendered);
        self::assertStringContainsString('0 0 100 30 re', $rendered);
        self::assertStringContainsString('4 8.4 m', $rendered);
        self::assertStringContainsString('96 8.4 l', $rendered);
    }
}
