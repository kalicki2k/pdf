<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Feature\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Feature\Annotation\PolygonAnnotation;
use Kalle\Pdf\Feature\Annotation\PopupAnnotation;
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

final class PolygonAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_polygon_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Polygon /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /C [1 0 0] /IC [0.9] /Contents (Polygon) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_subject_and_popup_reference_for_a_polygon_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], subject: 'Flaechenhinweis');
        $popup = new PopupAnnotation(8, $page, $annotation, 20, 30, 60, 40, true);

        self::assertSame($annotation, $annotation->withPopup($popup));

        self::assertStringContainsString('/Subj (Flaechenhinweis)', $annotation->render());
        self::assertStringContainsString('/Popup 8 0 R', $annotation->render());
        self::assertSame([$popup], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_border_style_for_a_polygon_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], borderStyle: AnnotationBorderStyle::dashed());

        self::assertStringContainsString('/BS << /W 1 /S /D /D [3 2] >>', $annotation->render());
    }

    #[Test]
    public function it_omits_optional_fields_when_they_are_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[90.0, 32.0], [10.0, 20.0], [40.0, 50.0]]);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Polygon /Rect [10 20 90 50] /P 4 0 R /Vertices [90 32 10 20 40 50] >>\n"
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
        $annotation = new PolygonAnnotation(
            7,
            $page,
            [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]],
            Color::cmyk(0.1, 0.2, 0.3, 0.4),
            Color::cmyk(0.5, 0.6, 0.7, 0.8),
        );

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
        self::assertStringContainsString('/IC [0.5 0.6 0.7 0.8]', $annotation->render());
    }

    #[Test]
    public function it_rejects_polygons_with_fewer_than_three_vertices(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Polygon annotation requires at least three vertices.');

        new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0]]);
    }

    #[Test]
    public function it_renders_a_pdf_a_polygon_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 80, 30));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Polygon /Rect [10 20 90 50] /P 4 0 R /Vertices [10 20 40 50 90 32] /F 4 /C [1 0 0] /IC [0.9] /Contents (Polygon) /T (QA) /AP << /N 8 0 R >> >>\n"
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
        $annotation = new PolygonAnnotation(7, $page, [[10.0, 20.0], [40.0, 50.0], [90.0, 32.0]], Color::rgb(255, 0, 0), Color::gray(0.9), 'Polygon', 'QA', subject: 'Flaechenhinweis');

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Polygon", $rendered);
        self::assertStringNotContainsString('(Polygon)', $rendered);
        self::assertStringNotContainsString('(QA)', $rendered);
        self::assertStringNotContainsString('(Flaechenhinweis)', $rendered);
    }
}
