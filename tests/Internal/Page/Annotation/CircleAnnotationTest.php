<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Annotation\CircleAnnotation;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircleAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_circle_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 4 0 R /C [0 0 1] /IC [0.9] /Contents (Kreis) /T (QA) >>\n"
            . "endobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation),
        );
    }

    #[Test]
    public function it_renders_a_border_style_for_a_circle_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, borderStyle: AnnotationBorderStyle::dashed(1.5, [2.0, 1.0]));

        self::assertStringContainsString('/BS << /W 1.5 /S /D /D [2 1] >>', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation));
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 4 0 R >>\n"
            . "endobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_cmyk_border_and_fill_colors(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
            Color::cmyk(0.5, 0.6, 0.7, 0.8),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation));
        self::assertStringContainsString('/IC [0.5 0.6 0.7 0.8]', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation));
    }

    #[Test]
    public function it_renders_a_pdf_a_circle_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 24));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Circle /Rect [10 20 90 44] /P 4 0 R /F 4 /C [0 0 1] /IC [0.9] /Contents (Kreis) /T (QA) /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($annotation),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CircleAnnotation(7, $page, 10, 20, 80, 24, Color::rgb(0, 0, 255), Color::gray(0.9), 'Kreis', 'QA');

        $rendered = \Kalle\Pdf\Tests\Support\writeIndirectObjectToString(
            $annotation,
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Circle", $rendered);
        self::assertStringNotContainsString('(Kreis)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
    }
}
