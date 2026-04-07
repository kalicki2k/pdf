<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Page;
use Kalle\Pdf\Table;
use Kalle\Pdf\TextFrame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublicApiTest extends TestCase
{
    protected function tearDown(): void
    {
        $tempFiles = glob(sys_get_temp_dir() . '/pdf-public-api-*');

        if ($tempFiles === false) {
            return;
        }

        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_returns_the_small_public_page_type_from_the_public_document_api(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(PageSize::A4());

        self::assertInstanceOf(Page::class, $page);
    }

    #[Test]
    public function it_passes_the_public_page_type_to_header_callbacks(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');

        $receivedPage = null;
        $document->addHeader(static function (Page $page, int $pageNumber) use (&$receivedPage): void {
            $receivedPage = $page;
            $page->addText("Header $pageNumber", new Position(10, 90), 'Helvetica', 10);
        });

        $document->addPage(PageSize::custom(100, 100));
        $document->render();

        self::assertInstanceOf(Page::class, $receivedPage);
        self::assertStringContainsString('(Header 1) Tj', $document->render());
    }

    #[Test]
    public function it_can_exclude_a_public_page_from_logical_numbering(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');

        $coverPage = $document->addPage(PageSize::custom(100, 100));
        $contentPage = $document->addPage(PageSize::custom(100, 100));

        $document->excludePageFromNumbering($coverPage);
        $document->addOutline('Kapitel', $contentPage);
        $tocPage = $document->addTableOfContents(
            PageSize::A6(),
            new TableOfContentsOptions(
                title: 'Inhalt',
                baseFont: 'Helvetica',
                titleSize: 16,
                entrySize: 10,
                margin: 10,
                placement: TableOfContentsPlacement::start(),
                useLogicalPageNumbers: true,
            ),
        );

        self::assertStringContainsString('(2) Tj', $document->render());
        self::assertInstanceOf(Page::class, $tocPage);
    }

    #[Test]
    public function it_exposes_no_public_properties_on_the_public_document_api(): void
    {
        $reflection = new \ReflectionClass(Document::class);
        $publicProperties = array_filter(
            $reflection->getProperties(\ReflectionProperty::IS_PUBLIC),
            static fn (\ReflectionProperty $property): bool => !$property->isStatic(),
        );

        self::assertCount(0, $publicProperties);
    }

    #[Test]
    public function it_keeps_text_frame_and_table_page_access_on_the_public_page_type(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(PageSize::custom(200, 200));

        $textFrame = $page->createTextFrame(new Position(10, 190), 100);
        $table = $page->createTable(new Position(10, 190), 100, [50, 50]);

        self::assertInstanceOf(TextFrame::class, $textFrame);
        self::assertInstanceOf(Table::class, $table);
        self::assertInstanceOf(Page::class, $textFrame->getPage());
        self::assertInstanceOf(Page::class, $table->getPage());
    }

    #[Test]
    public function it_exposes_and_updates_document_metadata_through_the_public_api(): void
    {
        $document = new Document(
            version: 1.4,
            creator: 'Initial Creator',
            creatorTool: 'Initial Tool',
        );

        self::assertSame('Initial Creator', $document->getCreator());
        self::assertSame('Initial Tool', $document->getCreatorTool());

        $document
            ->setCreator('Updated Creator')
            ->setProducer('Updated Producer')
            ->setCreatorTool('Updated Tool');

        self::assertSame('Updated Creator', $document->getCreator());
        self::assertSame('Updated Producer', $document->getProducer());
        self::assertSame('Updated Tool', $document->getCreatorTool());
    }

    #[Test]
    public function it_forwards_public_api_registrations_to_the_internal_document(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(PageSize::custom(100, 100));

        $attachmentPath = sys_get_temp_dir() . '/pdf-public-api-attachment.txt';
        file_put_contents($attachmentPath, 'attachment-body');

        $layer = $document->addLayer('Layer A', false);
        $document
            ->addOutline('Kapitel', $page)
            ->addDestination('chapter-1', $page)
            ->addAttachment('manual.txt', 'hello world', 'manual')
            ->addAttachmentFromFile($attachmentPath, 'copy.txt')
            ->addKeyword('pdf')
            ->registerFont('Helvetica')
            ->encrypt(new EncryptionOptions('user', 'owner', EncryptionPermissions::readOnly(), EncryptionAlgorithm::RC4_128));

        $internalDocument = $this->internalDocument($document);

        self::assertSame($layer, $internalDocument->getOptionalContentGroups()[0]);
        self::assertArrayHasKey('chapter-1', $internalDocument->getDestinations());
        self::assertCount(1, $internalDocument->outlineRoot?->getItems() ?? []);
        self::assertCount(2, $internalDocument->getAttachments());
        self::assertNotNull($internalDocument->getAttachment('manual.txt'));
        self::assertNotNull($internalDocument->getAttachment('copy.txt'));
        self::assertSame('pdf', $internalDocument->getKeywords()[0]);
        self::assertNotNull($internalDocument->getFontByBaseFont('Helvetica'));
        self::assertNotNull($internalDocument->getEncryptionOptions());
    }

    #[Test]
    public function it_adds_footers_and_page_numbers_through_the_public_api(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $document->addFooter(static function (Page $page, int $pageNumber): void {
            $page->addText("Footer $pageNumber", new Position(10, 10), 'Helvetica', 10);
        });
        $document->addPageNumbers(new Position(10, 20), 'Helvetica', 10, '{{page}} / {{pages}}');

        $document->addPage(PageSize::custom(100, 100));
        $document->addPage(PageSize::custom(100, 100));

        $rendered = $document->render();

        self::assertStringContainsString('(Footer 1) Tj', $rendered);
        self::assertStringContainsString('(Footer 2) Tj', $rendered);
        self::assertStringContainsString('(1 / 2) Tj', $rendered);
        self::assertStringContainsString('(2 / 2) Tj', $rendered);
    }

    #[Test]
    public function it_forwards_basic_public_page_operations(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $returnedPage = $page
            ->addText('Hello', new Position(10, 100), 'Helvetica', 10)
            ->addLine(new Position(10, 90), new Position(40, 90), 1.0, Color::rgb(0, 0, 0))
            ->addRectangle(new Rect(10, 60, 20, 10), 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 0, 0))
            ->addLink(new Rect(10, 40, 30, 10), 'https://example.com')
            ->addInternalLink(new Rect(10, 25, 30, 10), 'chapter-1');

        $internalPage = $this->internalPage($page);

        self::assertSame($page, $returnedPage);
        self::assertSame(120.0, $page->getWidth());
        self::assertSame(140.0, $page->getHeight());
        self::assertStringContainsString('(Hello) Tj', $internalPage->contents->render());
        self::assertCount(2, $internalPage->getAnnotations());
    }

    #[Test]
    public function it_wraps_public_page_layers_with_public_page_instances(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(120, 140));

        $receivedPage = null;
        $page->layer('Layer A', static function (Page $layerPage) use (&$receivedPage): void {
            $receivedPage = $layerPage;
            $layerPage->addText('Layered', new Position(10, 100), 'Helvetica', 10);
        });

        $internalPage = $this->internalPage($page);

        self::assertInstanceOf(Page::class, $receivedPage);
        self::assertStringContainsString('(Layered) Tj', $internalPage->contents->render());
    }

    #[Test]
    public function it_forwards_public_table_operations(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 200));

        $table = $page->createTable(new Position(10, 190), 100, [50, 50]);
        $returnedTable = $table
            ->font('Helvetica', 10)
            ->style(new TableStyle())
            ->rowStyle(new RowStyle())
            ->headerStyle(new HeaderStyle())
            ->addRow(['H1', 'H2'], true)
            ->addRow(['A', 'B']);

        self::assertSame($table, $returnedTable);
        self::assertInstanceOf(Page::class, $table->getPage());
        self::assertLessThan(190.0, $table->getCursorY());
    }

    #[Test]
    public function it_forwards_public_text_frame_operations(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 200));

        $frame = $page->createTextFrame(new Position(10, 190), 100);
        $returnedFrame = $frame
            ->addText('Intro', 'Helvetica', 10)
            ->addParagraph('Paragraph', 'Helvetica', 10)
            ->addBulletList(['One', 'Two'], 'Helvetica', 10)
            ->addNumberedList(['First', 'Second'], 'Helvetica', 10)
            ->addHeading('Heading', 'Helvetica', 12)
            ->addSpacer(8.0);

        self::assertSame($frame, $returnedFrame);
        self::assertInstanceOf(Page::class, $frame->getPage());
        self::assertLessThan(190.0, $frame->getCursorY());

        $rendered = $this->internalPage($page)->contents->render();

        self::assertStringContainsString('(Intro) Tj', $rendered);
        self::assertStringContainsString('(Paragraph) Tj', $rendered);
        self::assertStringContainsString('(One) Tj', $rendered);
        self::assertStringContainsString('(First) Tj', $rendered);
        self::assertStringContainsString('(Heading) Tj', $rendered);
    }

    private function internalDocument(Document $document): \Kalle\Pdf\Document\Document
    {
        $property = new \ReflectionProperty($document, 'document');

        /** @var \Kalle\Pdf\Document\Document $internalDocument */
        $internalDocument = $property->getValue($document);

        return $internalDocument;
    }

    private function internalPage(Page $page): \Kalle\Pdf\Document\Page
    {
        $property = new \ReflectionProperty($page, 'page');

        /** @var \Kalle\Pdf\Document\Page $internalPage */
        $internalPage = $property->getValue($page);

        return $internalPage;
    }
}
