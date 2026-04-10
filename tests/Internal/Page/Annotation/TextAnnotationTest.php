<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Page\Annotation\PopupAnnotation;
use Kalle\Pdf\Internal\Page\Annotation\TextAnnotation;
use Kalle\Pdf\Internal\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_text_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Text /Rect [10 20 26 38] /P 4 0 R /Contents (Kommentar) /Name /Comment /Open true /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_text_annotation_with_popup_reference(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, false);
        $annotation->withPopup($popup);

        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_pdf_a_text_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 16, 18));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Text /Rect [10 20 26 38] /P 4 0 R /Contents (Kommentar) /Name /Comment /Open true /F 4 /T (QA) /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_text_annotation_with_a_struct_parent_reference(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);
        $annotation->withStructParent(3);

        self::assertStringContainsString('/StructParent 3', $annotation->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new TextAnnotation(7, $page, 10, 20, 16, 18, 'Kommentar', 'QA', 'Comment', true);

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Text", $rendered);
        self::assertStringNotContainsString('(Kommentar)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
    }
}
