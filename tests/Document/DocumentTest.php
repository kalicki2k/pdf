<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Page;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\StructureTag;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsPosition;
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

        self::assertSame([1, 2, 3, 4], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringNotContainsString('/StructTreeRoot', $document->catalog->render());
    }

    #[Test]
    public function it_assigns_object_ids_to_added_fonts_and_pages(): void
    {
        $document = new Document(version: 1.4);

        $returnedDocument = $document->registerFont('Helvetica');
        $page = $document->addPage(100.0, 200.0);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertSame(4, $document->getFonts()[0]->id);
        self::assertSame(5, $page->id);
        self::assertSame(6, $page->contents->id);
        self::assertSame(7, $page->resources->id);
        self::assertSame([1, 2, 3, 4, 5, 7, 6, 8], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_registers_standard_fonts_via_the_explicit_register_font_api(): void
    {
        $document = new Document(version: 1.4);

        $returnedDocument = $document->registerFont('Helvetica');

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertSame('Helvetica', $document->getFonts()[0]->getBaseFont());
    }

    #[Test]
    public function it_uses_distinct_defaults_for_creator_producer_and_creator_tool_metadata(): void
    {
        $document = new Document(version: 1.4);

        self::assertSame('kalle/pdf', $document->getCreator());
        self::assertStringStartsWith('kalle/pdf', $document->getProducer());
        self::assertSame('kalle/pdf', $document->getCreatorTool());
    }

    #[Test]
    public function it_allows_custom_creator_producer_and_creator_tool_metadata(): void
    {
        $document = new Document(
            version: 1.4,
            creator: 'Acme Invoice Service',
            creatorTool: 'Acme Backoffice',
        );

        $returnedDocument = $document->setProducer('kalle/pdf 1.0');

        self::assertSame($document, $returnedDocument);
        self::assertSame('Acme Invoice Service', $document->getCreator());
        self::assertSame('kalle/pdf 1.0', $document->getProducer());
        self::assertSame('Acme Backoffice', $document->getCreatorTool());
    }

    #[Test]
    public function it_falls_back_to_the_package_name_for_an_empty_creator_metadata_value(): void
    {
        $document = new Document(version: 1.4, creator: '');

        self::assertSame('kalle/pdf', $document->getCreator());
    }

    #[Test]
    public function it_rejects_an_empty_producer_metadata_value(): void
    {
        $document = new Document(version: 1.4);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Producer must not be empty.');

        $document->setProducer('');
    }

    #[Test]
    public function it_falls_back_to_the_package_name_for_an_empty_creator_tool_metadata_value(): void
    {
        $document = new Document(version: 1.4, creatorTool: '');

        self::assertSame('kalle/pdf', $document->getCreatorTool());
    }

    #[Test]
    public function it_uses_a_pdf_1_0_compatible_default_encoding_for_standard_fonts(): void
    {
        $document = new Document(version: 1.0);

        $document->registerFont('Helvetica');

        self::assertStringContainsString('/Encoding ', $document->getFonts()[0]->render());
        self::assertStringContainsString('/BaseEncoding /StandardEncoding', $document->render());
    }

    #[Test]
    public function it_keeps_the_win_ansi_default_for_newer_pdf_versions(): void
    {
        $document = new Document(version: 1.4);

        $document->registerFont('Helvetica');

        self::assertStringContainsString('/Encoding /WinAnsiEncoding', $document->getFonts()[0]->render());
    }

    #[Test]
    public function it_still_validates_explicitly_provided_encodings_against_the_pdf_version(): void
    {
        $document = new Document(version: 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Encoding 'WinAnsiEncoding' is not allowed in PDF 1.0.");

        $document->registerFont('Helvetica', encoding: 'WinAnsiEncoding');
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
    public function it_adds_attachments_as_indirect_objects(): void
    {
        $document = new Document(version: 1.4);

        $document->addAttachment('demo.txt', 'hello', 'Demo attachment', 'text/plain');

        self::assertSame([1, 2, 5, 4, 3, 6], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
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
        $document->registerFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", new Position(10, 10), 'Helvetica', 10);
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
        $document->registerFont('Helvetica');
        $document
            ->addHeader(static function (Page $page, int $pageNumber): void {
                $page->addText("Header $pageNumber", new Position(10, 50), 'Helvetica', 10);
            })
            ->addFooter(static function (Page $page, int $pageNumber): void {
                $page->addText("Footer $pageNumber", new Position(10, 5), 'Helvetica', 10);
            });

        $firstPage = $document->addPage(100, 60);
        $frame = $firstPage->createTextFrame(new Position(10, 40), 80, 10);
        $frame->addParagraph(str_repeat('Wort ', 80), 'Helvetica', 12, new ParagraphOptions(structureTag: StructureTag::Paragraph));
        $lastPage = $document->pages->pages[array_key_last($document->pages->pages)];
        $lastPageNumber = count($document->pages->pages);

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertStringContainsString('(Header 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString('(Footer 1) Tj', $firstPage->contents->render());
        self::assertStringContainsString("(Header $lastPageNumber) Tj", $lastPage->contents->render());
        self::assertStringContainsString("(Footer $lastPageNumber) Tj", $lastPage->contents->render());
    }

    #[Test]
    public function it_adds_footer_page_numbers_with_total_page_count(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $document->addPage(100, 100);
        $document->addPage(100, 100);

        $document->addPageNumbers(new Position(10, 10));
        $document->render();

        self::assertStringContainsString('(Seite 1 von 2) Tj', $document->pages->pages[0]->contents->render());
        self::assertStringContainsString('(Seite 2 von 2) Tj', $document->pages->pages[1]->contents->render());
    }

    #[Test]
    public function it_adds_header_page_numbers_with_a_custom_template(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $document->addPage(100, 100);
        $document->addPage(100, 100);
        $document->addPage(100, 100);

        $document->addPageNumbers(new Position(10, 90), 'Helvetica', 10, 'Seite {{page}} / {{pages}}', false);
        $document->render();

        self::assertStringContainsString('(Seite 1 / 3) Tj', $document->pages->pages[0]->contents->render());
        self::assertStringContainsString('(Seite 3 / 3) Tj', $document->pages->pages[2]->contents->render());
    }

    #[Test]
    public function it_rejects_empty_page_number_templates(): void
    {
        $document = new Document(version: 1.4);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page number template must not be empty.');

        $document->addPageNumbers(new Position(10, 10), template: '');
    }

    #[Test]
    public function it_adds_a_table_of_contents_from_existing_outlines(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(140, 100, 'Inhalt', 'Helvetica', 16, 10, 10);

        self::assertSame($document->pages->pages[array_key_last($document->pages->pages)], $tocPage);
        self::assertStringContainsString('(Inhalt) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(Erste Seite) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(1) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(Zweite Seite) Tj', $tocPage->contents->render());
        self::assertStringContainsString('(2) Tj', $tocPage->contents->render());
        self::assertStringContainsString('/Dests << /toc-page-5 [5 0 R /Fit] /toc-page-8 [8 0 R /Fit] >>', $document->render());
    }

    #[Test]
    public function it_can_move_the_table_of_contents_to_the_start(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $firstPage = $document->addPage(100, 100);
        $secondPage = $document->addPage(100, 100);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        $tocPage = $document->addTableOfContents(140, 100, 'Inhalt', 'Helvetica', 16, 10, 10, TableOfContentsPosition::START);

        self::assertSame($tocPage, $document->pages->pages[0]);
        self::assertSame($firstPage, $document->pages->pages[1]);
        self::assertSame($secondPage, $document->pages->pages[2]);
    }

    #[Test]
    public function it_numbers_entries_correctly_when_a_multi_page_table_of_contents_moves_to_the_start(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $firstContentPage = null;

        foreach (range('A', 'I') as $label) {
            $page = $document->addPage(100, 100);
            $firstContentPage ??= $page;
            $document->addOutline("Eintrag $label", $page);
        }

        $document->addTableOfContents(140, 100, 'Inhalt', 'Helvetica', 16, 10, 10, TableOfContentsPosition::START);

        $firstContentPageIndex = array_search($firstContentPage, $document->pages->pages, true);
        self::assertIsInt($firstContentPageIndex);
        self::assertGreaterThan(1, $firstContentPageIndex);

        $tocPages = array_slice($document->pages->pages, 0, $firstContentPageIndex);
        $tocContents = implode('', array_map(
            static fn (Page $page): string => $page->contents->render(),
            $tocPages,
        ));

        self::assertStringContainsString('(' . ($firstContentPageIndex + 1) . ') Tj', $tocContents);
        self::assertStringContainsString('(' . ($firstContentPageIndex + 9) . ') Tj', $tocContents);
        self::assertStringNotContainsString('(1) Tj', $tocContents);
        self::assertStringNotContainsString('(2) Tj', $tocContents);
    }

    #[Test]
    public function it_rejects_a_table_of_contents_without_outline_entries(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents requires at least one outline entry.');

        $document->addTableOfContents(140, 100, 'Inhalt', 'Helvetica', 16, 10, 10);
    }

    #[Test]
    public function it_registers_outline_objects_and_links_them_to_pages(): void
    {
        $document = new Document(version: 1.4);
        $firstPage = $document->addPage(100.0, 200.0);
        $secondPage = $document->addPage(100.0, 200.0);

        $document
            ->addOutline('Erste Seite', $firstPage)
            ->addOutline('Zweite Seite', $secondPage);

        self::assertSame([1, 2, 10, 11, 12, 3, 4, 6, 5, 7, 9, 8, 13], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('10 0 obj' . "\n" . '<< /Type /Outlines /Count 2 /First 11 0 R /Last 12 0 R >>', $document->render());
        self::assertStringContainsString('11 0 obj' . "\n" . '<< /Title (Erste Seite) /Parent 10 0 R /Dest [4 0 R /Fit] /Next 12 0 R >>', $document->render());
        self::assertStringContainsString('12 0 obj' . "\n" . '<< /Title (Zweite Seite) /Parent 10 0 R /Dest [7 0 R /Fit] /Prev 11 0 R >>', $document->render());
    }

    #[Test]
    public function it_registers_named_destinations_on_the_catalog(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(100.0, 200.0);

        $document->addDestination('table-demo', $page);

        self::assertStringContainsString('/Dests << /table-demo [4 0 R /Fit] >>', $document->render());
    }

    #[Test]
    public function it_rejects_invalid_destination_names(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(100.0, 200.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination name may contain only letters, numbers, dots, underscores and hyphens.');

        $document->addDestination('table demo', $page);
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

        $returnedDocument = $document->registerFont('NotoSansCJKsc-Regular', 'CIDFontType2', unicode: true);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame(9, $document->getFonts()[0]->id);
        self::assertSame(7, $document->getFonts()[0]->descendantFont->id);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
    }

    #[Test]
    public function it_registers_embedded_fonts_via_their_direct_font_names(): void
    {
        $document = new Document(version: 1.4);

        $document
            ->registerFont('NotoSans-Regular')
            ->registerFont('NotoSansCJKsc-Regular');

        self::assertCount(2, $document->getFonts());
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
        self::assertSame('NotoSansCJKsc-Regular', $document->getFonts()[1]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[1]);
        self::assertNotNull($document->getFonts()[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[1]->descendantFont->cidToGidMap);
        self::assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16],
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

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_an_embedded_font_by_its_direct_font_name(): void
    {
        $document = new Document(version: 1.4);

        $document->registerFont('NotoSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('NotoSans-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
    }

    #[Test]
    public function it_registers_serif_and_mono_fonts_as_embedded_fonts_by_their_direct_names(): void
    {
        $document = new Document(version: 1.4);

        $document
            ->registerFont('NotoSerif-Regular')
            ->registerFont('NotoSansMono-Regular');

        self::assertCount(2, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[1]);
        self::assertSame('NotoSerif-Regular', $document->getFonts()[0]->getBaseFont());
        self::assertSame('NotoSansMono-Regular', $document->getFonts()[1]->getBaseFont());
        self::assertNotNull($document->getFonts()[0]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->getFonts()[0]->descendantFont->cidToGidMap);
        self::assertNotNull($document->getFonts()[1]->descendantFont->cidToGidMap);
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

        $document->registerFont('CustomSans-Regular');

        self::assertCount(1, $document->getFonts());
        self::assertInstanceOf(UnicodeFont::class, $document->getFonts()[0]);
        self::assertSame('CustomSans-Regular', $document->getFonts()[0]->getBaseFont());
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

        self::assertSame(['pdf', 'testing'], $document->getKeywords());
    }

    #[Test]
    public function it_discards_empty_keywords_after_trimming(): void
    {
        $document = new Document(version: 1.4, title: 'Spec');

        $document
            ->addKeyword(' ')
            ->addKeyword('pdf')
            ->addKeyword('   ');

        self::assertSame(['pdf'], $document->getKeywords());
        self::assertStringContainsString('/Keywords (pdf)', $document->render());
        self::assertStringNotContainsString('/Keywords (,', $document->render());
        self::assertStringNotContainsString('<rdf:li></rdf:li>', $document->render());
        self::assertStringContainsString('<pdf:Keywords>pdf</pdf:Keywords>', $document->render());
    }

    #[Test]
    public function it_adds_structure_elements_and_links_them_to_the_document_root(): void
    {
        $document = new Document(version: 1.4);

        $result = $document->addStructElem(StructureTag::Paragraph, 42);

        self::assertSame($document, $result);
        self::assertSame([1, 2, 4, 5, 6, 7, 3, 8], array_map(
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
        $document->registerFont('Helvetica');
        $document->addPage();

        $output = $document->render();

        self::assertStringStartsWith("%PDF-1.4\n", $output);
        self::assertStringContainsString('/Title (Spec)', $output);
        self::assertStringContainsString('/Author (Kalle)', $output);
        self::assertStringContainsString('/Subject (Tests)', $output);
        self::assertStringContainsString('/Keywords (pdf)', $output);
        self::assertStringContainsString('/Metadata 8 0 R', $output);
        self::assertStringContainsString('<dc:format>application/pdf</dc:format>', $output);
        self::assertStringContainsString('<rdf:li>de-DE</rdf:li>', $output);
        self::assertStringNotContainsString('/Lang (de-DE)', $output);
        self::assertStringNotContainsString('/MarkInfo', $output);
        self::assertStringNotContainsString('/StructTreeRoot', $output);
        self::assertStringContainsString("xref\n0 9\n", $output);
        self::assertStringContainsString("trailer\n<< /Size 9\n/Root 1 0 R\n/Info 3 0 R\n/ID [<", $output);
        self::assertMatchesRegularExpression('/\/CreationDate \(D:\d{14}[+-]\d{4}\)/', $output);
        self::assertMatchesRegularExpression('/\/ModDate \(D:\d{14}[+-]\d{4}\)/', $output);
        self::assertStringEndsWith('%%EOF', $output);
    }

    #[Test]
    public function it_enables_structure_metadata_only_after_tagged_content_is_added(): void
    {
        $document = new Document(version: 1.4, language: 'de-DE');
        $document->registerFont('Helvetica');
        $document->addPage()->addText('Hello', new Position(10, 20), 'Helvetica', 12, new TextOptions(structureTag: StructureTag::Paragraph));

        $output = $document->render();

        self::assertStringContainsString('/Lang (de-DE)', $output);
        self::assertStringContainsString('/Metadata 12 0 R', $output);
        self::assertStringContainsString('/StructTreeRoot 8 0 R', $output);
        self::assertStringContainsString('/StructParents 0', $output);
    }
}
