<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
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

    private function internalDocument(Document $document): \Kalle\Pdf\Document\Document
    {
        $property = new \ReflectionProperty($document, 'document');

        /** @var \Kalle\Pdf\Document\Document $internalDocument */
        $internalDocument = $property->getValue($document);

        return $internalDocument;
    }
}
