<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Layout\PageSize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    private const FLOAT_DELTA = 0.0001;

    #[Test]
    public function it_initializes_base_objects_for_pdf_1_0(): void
    {
        $document = new Document();

        self::assertSame(1, $document->catalog->id);
        self::assertSame(2, $document->pages->id);
        self::assertSame(3, $document->info->id);
        self::assertSame([1, 2, 3], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_initializes_structure_objects_for_pdf_1_4_and_above(): void
    {
        $document = new Document(version: 1.4, language: 'de-DE');

        self::assertSame([1, 2, 3], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringNotContainsString('/StructTreeRoot', $document->catalog->render());
    }

    #[Test]
    public function it_assigns_object_ids_to_added_fonts_and_pages(): void
    {
        $document = new Document(version: 1.4);

        $returnedDocument = $document->addFont('Helvetica');
        $page = $document->addPage(100.0, 200.0);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->fonts);
        self::assertSame(4, $document->fonts[0]->id);
        self::assertSame(5, $page->id);
        self::assertSame(6, $page->contents->id);
        self::assertSame(7, $page->resources->id);
        self::assertSame([1, 2, 3, 4, 5, 7, 6], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_adds_a_page_from_a_named_page_size(): void
    {
        $document = new Document(version: 1.4);

        $page = $document->addPage(PageSize::A5());

        self::assertEqualsWithDelta(419.5275590551, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(595.2755905512, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_page_from_the_a00_special_case(): void
    {
        $document = new Document(version: 1.4);

        $page = $document->addPage(PageSize::A00());

        self::assertEqualsWithDelta(3370.3937007874, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(4767.874015748, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_page_from_the_b_series(): void
    {
        $document = new Document(version: 1.4);

        $page = $document->addPage(PageSize::B4());

        self::assertEqualsWithDelta(708.6614173228, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(1000.6299212598, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_page_from_the_c_series(): void
    {
        $document = new Document(version: 1.4);

        $page = $document->addPage(PageSize::C5());

        self::assertEqualsWithDelta(459.2125984252, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(649.1338582677, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_adds_a_landscape_page_from_a_named_page_size(): void
    {
        $document = new Document(version: 1.4);

        $page = $document->addPage(PageSize::A4()->landscape());

        self::assertEqualsWithDelta(841.8897637795, $page->getWidth(), self::FLOAT_DELTA);
        self::assertEqualsWithDelta(595.2755905512, $page->getHeight(), self::FLOAT_DELTA);
    }

    #[Test]
    public function it_applies_header_and_footer_callbacks_to_new_pages(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", 10, 90, 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", 10, 10, 'Helvetica', 10);
            });

        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        self::assertStringContainsString('(Header 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Footer 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Header 2) Tj', $secondPage->contents->render());
        self::assertStringContainsString('(Footer 2) Tj', $secondPage->contents->render());
    }

    #[Test]
    public function it_applies_header_and_footer_callbacks_to_overflow_pages(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", 10, 50, 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", 10, 5, 'Helvetica', 10);
            });

        $firstPage = $document->addPage(100, 60);
        $frame = $firstPage->textFrame(10, 40, 80, 10);
        $frame->paragraph(str_repeat('Wort ', 80), 'Helvetica', 12, 'P');
        $lastPage = $document->pages->pages[array_key_last($document->pages->pages)];
        $lastPageNumber = count($document->pages->pages);

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertStringContainsString('(Header 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Footer 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString("(Header $lastPageNumber) Tj", $lastPage->contents->render());
        self::assertStringContainsString("(Footer $lastPageNumber) Tj", $lastPage->contents->render());
    }

    #[Test]
    public function it_rejects_combining_page_size_and_explicit_height(): void
    {
        $document = new Document(version: 1.4);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Height must not be provided when using a PageSize.');

        $document->addPage(PageSize::A4(), 100.0);
    }

    #[Test]
    public function it_registers_unicode_fonts_together_with_their_descendant_font_objects(): void
    {
        $document = new Document(version: 1.4);

        $returnedDocument = $document->addFont('NotoSansCJKsc-Regular', 'CIDFontType2', unicode: true);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->fonts);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertSame(9, $document->fonts[0]->id);
        self::assertSame(7, $document->fonts[0]->descendantFont->id);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_registers_embedded_fonts_via_their_direct_font_names(): void
    {
        $document = new Document(version: 1.4);

        $document
            ->addFont('NotoSans-Regular')
            ->addFont('NotoSansCJKsc-Regular');

        self::assertCount(2, $document->fonts);
        self::assertSame('NotoSans-Regular', $document->fonts[0]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertNotNull($document->fonts[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[0]->descendantFont->cidToGidMap);
        self::assertSame('NotoSansCJKsc-Regular', $document->fonts[1]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[1]);
        self::assertNotNull($document->fonts[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[1]->descendantFont->cidToGidMap);
        self::assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            array_map(
                static fn (object $object): int => $object->id,
                $document->getDocumentObjects(),
            ),
        );
    }

    #[Test]
    public function it_registers_the_noto_sans_font_as_an_embedded_font(): void
    {
        $document = new Document(version: 1.4);

        $document->addFont('NotoSans-Regular');

        self::assertCount(1, $document->fonts);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertSame('NotoSans-Regular', $document->fonts[0]->getBaseFont());
        self::assertNotNull($document->fonts[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_an_embedded_font_by_its_direct_font_name(): void
    {
        $document = new Document(version: 1.4);

        $document->addFont('NotoSans-Regular');

        self::assertCount(1, $document->fonts);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertSame('NotoSans-Regular', $document->fonts[0]->getBaseFont());
        self::assertNotNull($document->fonts[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_serif_and_mono_fonts_as_embedded_fonts_by_their_direct_names(): void
    {
        $document = new Document(version: 1.4);

        $document
            ->addFont('NotoSerif-Regular')
            ->addFont('NotoSansMono-Regular');

        self::assertCount(2, $document->fonts);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[1]);
        self::assertSame('NotoSerif-Regular', $document->fonts[0]->getBaseFont());
        self::assertSame('NotoSansMono-Regular', $document->fonts[1]->getBaseFont());
        self::assertNotNull($document->fonts[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[0]->descendantFont->cidToGidMap);
        self::assertNotNull($document->fonts[1]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_can_use_a_document_specific_font_configuration(): void
    {
        $document = new Document(
            version: 1.4,
            fontConfig: [
                [
                    'baseFont' => 'CustomSans-Regular',
                    'path' => 'assets/fonts/NotoSans-Regular.ttf',
                    'unicode' => true,
                    'subtype' => 'CIDFontType2',
                    'encoding' => 'Identity-H',
                ],
            ],
        );

        $document->addFont('CustomSans-Regular');

        self::assertCount(1, $document->fonts);
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[0]);
        self::assertSame('CustomSans-Regular', $document->fonts[0]->getBaseFont());
        self::assertSame($document->getFontConfig(), [
            [
                'baseFont' => 'CustomSans-Regular',
                'path' => 'assets/fonts/NotoSans-Regular.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
        ]);
    }

    #[Test]
    public function it_normalizes_keywords_uniquely_while_preserving_first_occurrence(): void
    {
        $document = new Document();

        $document
            ->addKeyword('  pdf ')
            ->addKeyword('testing')
            ->addKeyword('pdf')
            ->addKeyword(' testing ');

        self::assertSame(['pdf', 'testing'], $document->keywords);
    }

    #[Test]
    public function it_adds_structure_elements_and_links_them_to_the_document_root(): void
    {
        $document = new Document(version: 1.4);

        $result = $document->addStructElem('P', 42);

        self::assertSame($document, $result);
        self::assertSame([1, 2, 4, 5, 6, 7, 3], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('6 0 obj' . "\n" . '<< /Type /StructElem /S /Document /K [7 0 R] >>', $document->render());
        self::assertStringContainsString('7 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 6 0 R /K [] >>', $document->render());
    }

    #[Test]
    public function it_renders_a_pdf_document_with_structure_metadata_for_version_1_4(): void
    {
        $document = new Document(
            version: 1.4,
            title: 'Spec',
            author: 'Kalle',
            subject: 'Tests',
            language: 'de-DE',
        );

        $document->addKeyword('pdf');
        $document->addFont('Helvetica');
        $document->addPage();

        $output = $document->render();

        self::assertStringStartsWith("%PDF-1.4\n", $output);
        self::assertStringContainsString('/Title (Spec)', $output);
        self::assertStringContainsString('/Author (Kalle)', $output);
        self::assertStringContainsString('/Subject (Tests)', $output);
        self::assertStringContainsString('/Keywords (pdf)', $output);
        self::assertStringContainsString('/Lang (de-DE)', $output);
        self::assertStringNotContainsString('/MarkInfo', $output);
        self::assertStringNotContainsString('/StructTreeRoot', $output);
        self::assertStringContainsString("xref\n0 8\n", $output);
        self::assertStringContainsString("trailer\n<< /Size 8\n/Root 1 0 R\n/Info 3 0 R >>\n", $output);
        self::assertMatchesRegularExpression('/\/CreationDate \(D:\d{14}\)/', $output);
        self::assertStringEndsWith('%%EOF', $output);
    }

    #[Test]
    public function it_enables_structure_metadata_only_after_tagged_content_is_added(): void
    {
        $document = new Document(version: 1.4, language: 'de-DE');
        $document->addFont('Helvetica');
        $document->addPage()->addText('Hello', 10, 20, 'Helvetica', 12, 'P');

        $output = $document->render();

        self::assertStringContainsString('/Lang (de-DE)', $output);
        self::assertStringContainsString('/StructTreeRoot 8 0 R', $output);
        self::assertStringContainsString('/StructParents 0', $output);
    }
}
