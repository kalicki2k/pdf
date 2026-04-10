<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Annotation\HighlightAnnotation;
use Kalle\Pdf\Feature\Annotation\PopupAnnotation;
use Kalle\Pdf\Feature\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HighlightAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_highlight_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(255, 255, 0), 'Markiert', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Highlight /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /C [1 1 0] /Contents (Markiert) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Highlight /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_link_a_popup_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12, contents: 'Markiert');
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 40, 60, 30, true);

        self::assertSame($annotation, $annotation->withPopup($popup));
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertSame([$popup], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_highlight_color(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12, Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }

    #[Test]
    public function it_renders_a_pdf_a_highlight_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(255, 255, 0), 'Markiert', 'QA');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 12));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Highlight /Rect [10 20 90 32] /P 4 0 R /QuadPoints [10 32 90 32 10 20 90 20] /F 4 /C [1 1 0] /Contents (Markiert) /T (QA) /AP << /N 8 0 R >> >>\n"
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
        $annotation = new HighlightAnnotation(7, $page, 10, 20, 80, 12, Color::rgb(255, 255, 0), 'Markiert', 'QA');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Highlight", $rendered);
        self::assertStringNotContainsString('(Markiert)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
    }
}
