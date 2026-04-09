<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Feature\Annotation\CheckboxAnnotation;
use Kalle\Pdf\Feature\Form\CheckboxAppearanceStream;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckboxAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_checkbox_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $annotation = new CheckboxAnnotation(
            7,
            $page,
            10,
            20,
            12,
            12,
            'accept_terms',
            true,
            new CheckboxAppearanceStream(8, 12, 12, false),
            new CheckboxAppearanceStream(9, 12, 12, true),
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Btn /Rect [10 20 22 32] /Border [0 0 0] /P 4 0 R /T (accept_terms) /V /Yes /AS /Yes /AP << /N << /Off 8 0 R /Yes 9 0 R >> >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_an_unchecked_checkbox_and_returns_related_appearance_streams(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $offAppearance = new CheckboxAppearanceStream(8, 12, 12, false);
        $onAppearance = new CheckboxAppearanceStream(9, 12, 12, true);

        $annotation = new CheckboxAnnotation(
            7,
            $page,
            10,
            20,
            12,
            12,
            'accept_terms',
            false,
            $offAppearance,
            $onAppearance,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Btn /Rect [10 20 22 32] /Border [0 0 0] /P 4 0 R /T (accept_terms) /V /Off /AS /Off /AP << /N << /Off 8 0 R /Yes 9 0 R >> >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([$offAppearance, $onAppearance], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_accessibility_metadata_for_checkboxes(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();

        $annotation = new CheckboxAnnotation(
            7,
            $page,
            10,
            20,
            12,
            12,
            'accept_terms',
            true,
            new CheckboxAppearanceStream(8, 12, 12, false),
            new CheckboxAppearanceStream(9, 12, 12, true),
            'Accept terms',
        );
        $annotation->withStructParent(1);

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertStringContainsString('/TU (Accept terms)', $annotation->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $annotation = new CheckboxAnnotation(
            7,
            $page,
            10,
            20,
            12,
            12,
            'accept_terms',
            true,
            new CheckboxAppearanceStream(8, 12, 12, false),
            new CheckboxAppearanceStream(9, 12, 12, true),
            'Accept terms',
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

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Widget", $rendered);
        self::assertStringNotContainsString('(accept_terms)', $rendered);
        self::assertStringNotContainsString('(Accept terms)', $rendered);
    }
}
