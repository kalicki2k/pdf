<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Page\Annotation\InkAnnotation;
use Kalle\Pdf\Internal\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Internal\Style\Color;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InkAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_an_ink_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Ink /Rect [10 20 90 44] /P 4 0 R /InkList [[10 20 20 30 30 20]] /C [0 0 0] /Contents (Ink) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0]],
            ],
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Ink /Rect [10 20 90 44] /P 4 0 R /InkList [[10 20 20 30]] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_ink_color(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0]],
            ],
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }

    #[Test]
    public function it_rejects_empty_paths(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ink annotation requires at least one path.');

        new InkAnnotation(7, $page, 10, 20, 80, 24, []);
    }

    #[Test]
    public function it_renders_a_pdf_a_ink_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
        );
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 24));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Ink /Rect [10 20 90 44] /P 4 0 R /InkList [[10 20 20 30 30 20]] /F 4 /C [0 0 0] /Contents (Ink) /T (QA) /AP << /N 8 0 R >> >>\n"
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
        $annotation = new InkAnnotation(
            7,
            $page,
            10,
            20,
            80,
            24,
            [
                [[10.0, 20.0], [20.0, 30.0], [30.0, 20.0]],
            ],
            Color::rgb(0, 0, 0),
            'Ink',
            'QA',
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

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Ink", $rendered);
        self::assertStringNotContainsString('(Ink)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
    }
}
