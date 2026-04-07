<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Annotation\FreeTextAnnotation;
use Kalle\Pdf\Document\Annotation\PageAnnotationFactory;
use Kalle\Pdf\Document\Annotation\PopupAnnotation;
use Kalle\Pdf\Document\Annotation\TextAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\EmbeddedFileStream;
use Kalle\Pdf\Document\FileSpecification;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageAnnotationFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_free_text_annotation_and_registers_the_resolved_font(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            'QA',
        );

        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertSame([StandardFontName::HELVETICA], $registeredFonts);
        self::assertStringContainsString('/DA (/F1 12 Tf 0 g)', $annotation->render());
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_free_text_annotations(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            'QA',
        );

        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame([StandardFontName::HELVETICA], $resolvedFonts);
        self::assertSame([StandardFontName::HELVETICA], $registeredFonts);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_links_a_popup_to_a_parent_annotation_when_supported(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $parent = $factory->createHighlightAnnotation(new Rect(10, 20, 80, 12), null, 'Markiert', 'QA');

        $popup = $factory->createPopupAnnotation($parent, new Rect(20, 40, 60, 30), true);

        self::assertInstanceOf(PopupAnnotation::class, $popup);
        self::assertSame([$popup], $parent->getRelatedObjects());
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_width(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight annotation width must be greater than zero.');

        $factory->createHighlightAnnotation(new Rect(10, 20, 0, 12), null, null, null);
    }

    #[Test]
    public function it_rejects_rectangles_with_non_positive_height(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Highlight annotation height must be greater than zero.');

        $factory->createHighlightAnnotation(new Rect(10, 20, 80, 0), null, null, null);
    }

    #[Test]
    public function it_rejects_an_empty_file_attachment_icon(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);
        $file = new FileSpecification(8, 'demo.txt', new EmbeddedFileStream(7, 'hello'), 'Demo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File attachment icon must not be empty.');

        $factory->createFileAttachmentAnnotation(new Rect(10, 20, 12, 14), $file, '', null);
    }

    #[Test]
    public function it_rejects_empty_text_annotation_contents(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text annotation contents must not be empty.');

        $factory->createTextAnnotation(new Rect(10, 20, 16, 18), '', 'QA', 'Comment', true);
    }

    #[Test]
    public function it_rejects_empty_text_annotation_icons(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text annotation icon must not be empty.');

        $factory->createTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', '', true);
    }

    #[Test]
    public function it_adds_a_pdf_a_appearance_stream_to_text_annotations(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::pdfA2u());
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $annotation = $factory->createTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA', 'Comment', true);

        self::assertInstanceOf(TextAnnotation::class, $annotation);
        self::assertStringContainsString('/F 4', $annotation->render());
        self::assertStringContainsString('/AP << /N 101 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_rejects_empty_free_text_contents(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Free text annotation contents must not be empty.');

        $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            '',
            StandardFontName::HELVETICA,
            12,
            null,
            null,
            null,
            null,
        );
    }

    #[Test]
    public function it_rejects_non_positive_free_text_font_sizes(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Free text annotation font size must be greater than zero.');

        $factory->createFreeTextAnnotation(
            new Rect(10, 20, 80, 24),
            'Hinweistext',
            StandardFontName::HELVETICA,
            0,
            null,
            null,
            null,
            null,
        );
    }

    #[Test]
    public function it_rejects_empty_stamp_icons(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $resolvedFonts = [];
        $registeredFonts = [];
        $factory = $this->createFactory($page, $resolvedFonts, $registeredFonts);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stamp annotation icon must not be empty.');

        $factory->createStampAnnotation(new Rect(10, 20, 80, 24), '', null, null, null);
    }

    /**
     * @param list<string> $resolvedFonts
     * @param list<string> $registeredFonts
     */
    private function createFactory(Page $page, array &$resolvedFonts, array &$registeredFonts): PageAnnotationFactory
    {
        $nextObjectId = 100;

        return new PageAnnotationFactory(
            $page,
            static function () use (&$nextObjectId): int {
                return $nextObjectId++;
            },
            static function (string $baseFont) use (&$resolvedFonts): StandardFont {
                $resolvedFonts[] = $baseFont;

                return new StandardFont(
                    999,
                    $baseFont,
                    'Type1',
                    'WinAnsiEncoding',
                    1.4,
                );
            },
            static function (FontDefinition $font) use (&$registeredFonts): string {
                $registeredFonts[] = $font->getBaseFont();

                return 'F1';
            },
        );
    }
}
