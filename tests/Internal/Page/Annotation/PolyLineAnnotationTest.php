<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Annotation\PolyLineAnnotation;
use Kalle\Pdf\Page\Annotation\PopupAnnotation;
use Kalle\Pdf\Page\Annotation\Style\AnnotationBorderStyle;
use Kalle\Pdf\Page\Annotation\Style\LineEndingStyle;
use Kalle\Pdf\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Style\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolyLineAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_polyline_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(0, 0, 255), 'PolyLine', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /PolyLine /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /C [0 0 1] /Contents (PolyLine) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_polyline_ending_styles(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(
            7,
            $page,
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            startStyle: LineEndingStyle::CIRCLE,
            endStyle: LineEndingStyle::SLASH,
        );

        self::assertStringContainsString('/LE [/Circle /Slash]', $annotation->render());
    }

    #[Test]
    public function it_uses_none_for_the_missing_polyline_ending_style(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(
            7,
            $page,
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            startStyle: LineEndingStyle::CIRCLE,
        );

        self::assertStringContainsString('/LE [/Circle /None]', $annotation->render());
    }

    #[Test]
    public function it_renders_subject_and_popup_reference_for_a_polyline_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], subject: 'Korrekturpfad');
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, true);

        self::assertSame($annotation, $annotation->withPopup($popup));

        self::assertStringContainsString('/Subj (Korrekturpfad)', $annotation->render());
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertSame([$popup], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_border_style_for_a_polyline_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], borderStyle: AnnotationBorderStyle::solid(2.5));

        self::assertStringContainsString('/BS << /W 2.5 /S /S >>', $annotation->render());
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[90.0, 32.0], [10.0, 20.0], [40.0, 50.0]]);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /PolyLine /Rect [10 20 90 50] /P 4 0 R /Vertices [90 32 10 20 40 50] >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_polyline_color(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(
            7,
            $page,
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }

    #[Test]
    public function it_rejects_polylines_with_fewer_than_two_vertices(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PolyLine annotation requires at least two vertices.');

        new PolyLineAnnotation(7, $page, [[10.0, 20.0]]);
    }

    #[Test]
    public function it_renders_a_pdf_a_polyline_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(0, 0, 255), 'PolyLine', 'QA');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 30));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /PolyLine /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /F 4 /C [0 0 1] /Contents (PolyLine) /T (QA) /AP << /N 8 0 R >> >>\n"
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
        $annotation = new PolyLineAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(0, 0, 255), 'PolyLine', 'QA', subject: 'Korrekturpfad');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /PolyLine", $rendered);
        self::assertStringNotContainsString('(PolyLine)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
        self::assertStringNotContainsString('(Korrekturpfad)', $rendered);
    }
}
