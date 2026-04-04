<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use Kalle\Pdf\Core\Document;
use Kalle\Pdf\Core\UnicodeFont;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
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

        self::assertSame([1, 2, 3, 4, 5, 6], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('/StructTreeRoot 3 0 R', $document->catalog->render());
    }

    #[Test]
    public function it_assigns_object_ids_to_added_fonts_and_pages(): void
    {
        $document = new Document(version: 1.4);

        $returnedDocument = $document->addFont('Helvetica');
        $page = $document->addPage(100.0, 200.0);

        self::assertSame($document, $returnedDocument);
        self::assertCount(1, $document->fonts);
        self::assertSame(7, $document->fonts[0]->id);
        self::assertSame(8, $page->id);
        self::assertSame(9, $page->contents->id);
        self::assertSame(10, $page->resources->id);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 10, 9], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
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
    public function it_registers_font_presets_via_the_registry_group_name(): void
    {
        $document = new Document(version: 1.4);

        $document
            ->addFont('sans')
            ->addFont('global');

        self::assertCount(2, $document->fonts);
        self::assertSame('NotoSans-Regular', $document->fonts[0]->getBaseFont());
        self::assertSame('NotoSansCJKsc-Regular', $document->fonts[1]->getBaseFont());
        self::assertInstanceOf(UnicodeFont::class, $document->fonts[1]);
        self::assertNotNull($document->fonts[1]->descendantFont->fontDescriptor);
        self::assertNotNull($document->fonts[1]->descendantFont->cidToGidMap);
        self::assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13],
            array_map(
                static fn (object $object): int => $object->id,
                $document->getDocumentObjects(),
            ),
        );
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
        self::assertSame([1, 2, 3, 4, 5, 7, 6], array_map(
            static fn (object $object): int => $object->id,
            $document->getDocumentObjects(),
        ));
        self::assertStringContainsString('5 0 obj' . "\n" . '<< /Type /StructElem /S /Document /K [7 0 R] >>', $document->render());
        self::assertStringContainsString('7 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 5 0 R /K [] >>', $document->render());
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
        self::assertStringContainsString('/StructTreeRoot 3 0 R', $output);
        self::assertStringContainsString("xref\n0 11\n", $output);
        self::assertStringContainsString("trailer\n<< /Size 11\n/Root 1 0 R\n/Info 6 0 R >>\n", $output);
        self::assertMatchesRegularExpression('/\/CreationDate \(D:\d{14}\)/', $output);
        self::assertStringEndsWith('%%EOF', $output);
    }
}
