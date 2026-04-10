<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Feature\Annotation\SignatureFieldAnnotation;
use Kalle\Pdf\Feature\Form\FormFieldSignatureAppearanceStream;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SignatureFieldAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_signature_field_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $annotation = new SignatureFieldAnnotation(7, $page, 10, 20, 100, 30, 'approval_signature');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Sig /Rect [10 20 110 50] /Border [0 0 1] /P 4 0 R /T (approval_signature) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_accessibility_entries_for_pdf_ua_signature_fields(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $annotation = new SignatureFieldAnnotation(7, $page, 10, 20, 100, 30, 'approval_signature', 'Approval signature');
        $annotation->withStructParent(3);

        self::assertStringContainsString('/StructParent 3', $annotation->render());
        self::assertStringContainsString('/TU (Approval signature)', $annotation->render());
    }

    #[Test]
    public function it_renders_an_appearance_stream_for_signature_fields(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $annotation = new SignatureFieldAnnotation(
            7,
            $page,
            10,
            20,
            100,
            30,
            'approval_signature',
            appearance: new FormFieldSignatureAppearanceStream(8, 100, 30),
        );

        self::assertStringContainsString('/AP << /N 8 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $annotation = new SignatureFieldAnnotation(7, $page, 10, 20, 100, 30, 'approval_signature', 'Approval signature');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Widget", $rendered);
        self::assertStringNotContainsString('(approval_signature)', $rendered);
        self::assertStringNotContainsString('(Approval signature)', $rendered);
    }
}
