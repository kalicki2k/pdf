<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Annotation\FileAttachmentAnnotation;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Model\Document\EmbeddedFileStream;
use Kalle\Pdf\Model\Document\FileSpecification;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileAttachmentAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_file_attachment_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification, 'Graph', 'Anhang');

        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /Annot /Subtype /FileAttachment /Rect [10 20 22 34] /P 4 0 R /FS 8 0 R /Name /Graph /Contents (Anhang) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_uses_the_default_icon_and_omits_empty_contents(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification);

        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /Annot /Subtype /FileAttachment /Rect [10 20 22 34] /P 4 0 R /FS 8 0 R /Name /PushPin >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_file_attachment_annotation_with_a_struct_parent_reference(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification, 'Graph', 'Anhang');
        $annotation->withStructParent(4);

        self::assertStringContainsString('/StructParent 4', $annotation->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');
        $annotation = new FileAttachmentAnnotation(9, $page, 10, 20, 12, 14, $fileSpecification, 'Graph', 'Anhang');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                9,
            ),
        );

        self::assertStringStartsWith("9 0 obj\n<< /Type /Annot /Subtype /FileAttachment", $rendered);
        self::assertStringNotContainsString('(Anhang)', $rendered);
    }
}
