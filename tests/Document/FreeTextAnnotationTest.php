<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Feature\Annotation\FreeTextAnnotation;
use Kalle\Pdf\Feature\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FreeTextAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_free_text_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            'Hinweistext',
            'F1',
            12,
            Color::rgb(255, 0, 0),
            Color::gray(0.5),
            Color::gray(0.9),
            'QA',
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /FreeText /Rect [10 20 90 44] /P 4 0 R /Contents (Hinweistext) /DA (/F1 12 Tf 1 0 0 rg) /T (QA) /C [0.5] /IC [0.9] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(7, $page, 10, 20, 80, 24, 'Hinweistext', 'F1', 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /FreeText /Rect [10 20 90 44] /P 4 0 R /Contents (Hinweistext) /DA (/F1 12 Tf 0 g) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_cmyk_border_and_fill_colors(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            'Hinweistext',
            'F1',
            12,
            null,
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
            Color::cmyk(0.5, 0.6, 0.7, 0.8),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
        self::assertStringContainsString('/IC [0.5 0.6 0.7 0.8]', $annotation->render());
    }

    #[Test]
    public function it_renders_a_pdf_a_free_text_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(7, $page, 10, 20, 80, 24, 'Hinweistext', 'F1', 12);
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 24));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /FreeText /Rect [10 20 90 44] /P 4 0 R /Contents (Hinweistext) /DA (/F1 12 Tf 0 g) /F 4 /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new FreeTextAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            'Hinweistext',
            'F1',
            12,
            title: 'QA',
        );

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /FreeText", $rendered);
        self::assertStringNotContainsString('(Hinweistext)', $rendered);
        self::assertStringNotContainsString('(/F1 12 Tf 0 g)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
    }
}
