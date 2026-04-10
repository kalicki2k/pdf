<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Annotation\LinkAnnotation;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_uri_link_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::externalUrl('https://example.com'));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /A << /S /URI /URI (https://example.com) >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_pdf_a_uri_link_annotation_with_the_print_flag(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::externalUrl('https://example.com'));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /F 4 /A << /S /URI /URI (https://example.com) >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_struct_parent_for_tagged_link_annotations(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::externalUrl('https://example.com'));
        $annotation->withStructParent(1)->withContents('Example');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /StructParent 1 /Contents (Example) /A << /S /URI /URI (https://example.com) >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_an_internal_link_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::namedDestination('table-demo'));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest /table-demo >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_page_link_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $targetPage = $document->addPage();
        $annotation = new LinkAnnotation(10, $page, 10, 20, 80, 12, LinkTarget::page($targetPage));

        self::assertSame(
            "10 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest [7 0 R /Fit] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_a_position_link_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $targetPage = $document->addPage();
        $annotation = new LinkAnnotation(10, $page, 10, 20, 80, 12, LinkTarget::position($targetPage, 15, 25));

        self::assertSame(
            "10 0 obj\n"
            . "<< /Type /Annot /Subtype /Link /Rect [10 20 90 32] /Border [0 0 0] /P 4 0 R /Dest [7 0 R /XYZ 15 25 null] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_render_contents_and_uri_targets_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new LinkAnnotation(7, $page, 10, 20, 80, 12, LinkTarget::externalUrl('https://example.com'));
        $annotation->withContents('Example');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Link", $rendered);
        self::assertStringNotContainsString('(Example)', $rendered);
        self::assertStringNotContainsString('(https://example.com)', $rendered);
    }
}
