<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Action\ButtonAction;
use Kalle\Pdf\Document\Annotation\AnnotationBorderStyle;
use Kalle\Pdf\Document\Annotation\LineEndingStyle;
use Kalle\Pdf\Document\EmbeddedFileStream;
use Kalle\Pdf\Document\Geometry\Position;
use Kalle\Pdf\Document\Geometry\Rect;
use Kalle\Pdf\Document\PathBuilder;
use Kalle\Pdf\Document\Table\Style\HeaderStyle;
use Kalle\Pdf\Document\Table\Style\RowStyle;
use Kalle\Pdf\Document\Table\Style\TableStyle;
use Kalle\Pdf\Document\FileSpecification;
use Kalle\Pdf\Document\Form\FormFieldFlags;
use Kalle\Pdf\Document\Style\BadgeStyle;
use Kalle\Pdf\Document\Style\CalloutStyle;
use Kalle\Pdf\Document\Style\PanelStyle;
use Kalle\Pdf\Document\Text\FlowTextOptions;
use Kalle\Pdf\Document\Text\TextBoxOptions;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Encryption\EncryptionPermissions;
use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Layout\TextOverflow;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\TableOfContentsOptions;
use Kalle\Pdf\Layout\TableOfContentsPlacement;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Table;
use Kalle\Pdf\TextFrame;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage(PageSize::A4());

        self::assertInstanceOf(Page::class, $page);
    }

    #[Test]
    public function it_exposes_the_selected_pdf_profile_through_the_public_api(): void
    {
        $document = new Document(profile: Profile::pdfA2u());

        self::assertSame('PDF/A-2u', $document->getProfile()->name());
        self::assertSame(1.7, $document->getProfile()->version());
    }

    #[Test]
    public function it_passes_the_public_page_type_to_header_callbacks(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
            profile: \Kalle\Pdf\Profile::standard(1.4),
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
    public function it_exposes_document_dates_default_pages_attachments_and_table_of_contents_through_the_public_api(): void
    {
        $document = new Document(
            profile: \Kalle\Pdf\Profile::standard(1.4),
            title: 'Public API Document',
            author: 'Alice',
            subject: 'Coverage',
            language: 'de',
            creator: 'Creator',
            creatorTool: 'Tool',
        );
        $document->registerFont('Helvetica');

        $creationDate = $document->getCreationDate();
        $modificationDate = $document->getModificationDate();
        $firstPage = $document->addPage();
        $secondPage = $document->addPage(PageSize::custom(100, 100));

        $document
            ->addAttachment('guide.txt', 'Guide body', 'Guide')
            ->addOutline('Kapitel', $secondPage);

        $attachment = $document->getAttachment('guide.txt');
        $tocPage = $document->addTableOfContents();
        $rendered = $document->render();

        self::assertInstanceOf(\DateTimeImmutable::class, $creationDate);
        self::assertInstanceOf(\DateTimeImmutable::class, $modificationDate);
        self::assertGreaterThanOrEqual($creationDate->getTimestamp(), $modificationDate->getTimestamp());
        self::assertEqualsWithDelta(595.28, $firstPage->getWidth(), 0.01);
        self::assertEqualsWithDelta(841.89, $firstPage->getHeight(), 0.01);
        self::assertInstanceOf(FileSpecification::class, $attachment);
        self::assertSame($this->internalDocument($document)->getAttachment('guide.txt'), $attachment);
        self::assertInstanceOf(Page::class, $tocPage);
        self::assertStringStartsWith('%PDF-', $rendered);
    }

    #[Test]
    public function it_forwards_basic_public_page_operations(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
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

    #[Test]
    public function it_forwards_public_page_text_shape_and_measurement_operations(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(200, 220));

        $path = $page->addPath();
        $image = new Image(1, 1, 'DeviceRGB', 'FlateDecode', 'x');

        $returnedPage = $page
            ->addBadge('Badge', new Position(10, 200), 'Helvetica', 10, new BadgeStyle(fillColor: Color::rgb(230, 230, 230), textColor: Color::rgb(0, 0, 0)))
            ->addPanel('Body', 10, 135, 80, 55, 'Title', 'Helvetica', new PanelStyle())
            ->addCallout('Body', 100, 135, 80, 55, 90, 125, 'Callout', 'Helvetica', new CalloutStyle())
            ->addFlowText('Flow text', new Position(10, 130), 80, 'Helvetica', 10, new FlowTextOptions())
            ->addTextBox('Box text', new Rect(10, 100, 80, 20), 'Helvetica', 10, new TextBoxOptions())
            ->addRoundedRectangle(new Rect(10, 70, 20, 10), 2.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(0, 1, 0))
            ->addCircle(50, 75, 5.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(0, 0, 1))
            ->addEllipse(80, 75, 7.0, 4.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 0, 1))
            ->addPolygon([[100, 70], [110, 80], [120, 70]], 1.0, Color::rgb(0, 0, 0), Color::rgb(1, 1, 0))
            ->addArrow(new Position(10, 50), new Position(40, 50), color: Color::rgb(0, 0, 0))
            ->addStar(70, 45, 5, 8.0, 4.0, 1.0, Color::rgb(0, 0, 0), Color::rgb(255, 128, 0))
            ->addImage($image, new Position(130, 40), 10, 10);

        self::assertInstanceOf(PathBuilder::class, $path);
        self::assertSame($page, $returnedPage);
        self::assertGreaterThan(0, $page->countParagraphLines('Hello world', 'Helvetica', 10, 40));
        self::assertSame(22.78, $page->measureTextWidth('Hello', 'Helvetica', 10));

        $rendered = $this->internalPage($page)->contents->render();

        self::assertStringContainsString('(Badge) Tj', $rendered);
        self::assertStringContainsString('(Title) Tj', $rendered);
        self::assertStringContainsString('(Callout) Tj', $rendered);
        self::assertStringContainsString('(Flow text) Tj', $rendered);
        self::assertStringContainsString('(Box text) Tj', $rendered);
    }

    #[Test]
    public function it_forwards_public_page_annotation_form_and_attachment_operations(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage(PageSize::custom(220, 260));
        $file = new FileSpecification(99, 'manual.txt', new EmbeddedFileStream(100, 'data'));

        $returnedPage = $page
            ->addFileAttachment(new Rect(10, 230, 10, 10), $file)
            ->addTextAnnotation(new Rect(25, 230, 10, 10), 'Text note', 'Alice')
            ->addFreeTextAnnotation(new Rect(40, 220, 40, 20), 'Free text', 'Helvetica', 10, Color::rgb(0, 0, 0))
            ->addHighlightAnnotation(new Rect(10, 205, 30, 8), Color::rgb(1, 1, 0), 'Highlight', 'Bob')
            ->addUnderlineAnnotation(new Rect(45, 205, 30, 8), Color::rgb(0, 0, 1), 'Underline', 'Bob')
            ->addStrikeOutAnnotation(new Rect(80, 205, 30, 8), Color::rgb(1, 0, 0), 'Strike', 'Bob')
            ->addSquigglyAnnotation(new Rect(115, 205, 30, 8), Color::rgb(0, 1, 0), 'Squiggle', 'Bob')
            ->addStampAnnotation(new Rect(150, 200, 25, 12))
            ->addSquareAnnotation(new Rect(10, 180, 20, 20), Color::rgb(0, 0, 0), Color::rgb(1, 0, 0), 'Square', 'Bob', AnnotationBorderStyle::dashed())
            ->addCircleAnnotation(new Rect(35, 180, 20, 20), Color::rgb(0, 0, 0), Color::rgb(0, 1, 0), 'Circle', 'Bob', AnnotationBorderStyle::solid())
            ->addInkAnnotation(new Rect(60, 180, 30, 20), [[[60.0, 180.0], [70.0, 190.0]]], Color::rgb(0, 0, 0), 'Ink', 'Bob')
            ->addLineAnnotation(new Position(100, 180), new Position(130, 195), Color::rgb(0, 0, 0), 'Line', 'Bob', LineEndingStyle::OPEN_ARROW, LineEndingStyle::CLOSED_ARROW, 'Subject', AnnotationBorderStyle::solid())
            ->addPolyLineAnnotation([[140, 180], [150, 190], [160, 180]], Color::rgb(0, 0, 0), 'Polyline', 'Bob', LineEndingStyle::NONE, LineEndingStyle::SLASH, 'Subject', AnnotationBorderStyle::solid())
            ->addPolygonAnnotation([[170, 180], [180, 190], [190, 180]], Color::rgb(0, 0, 0), Color::rgb(1, 1, 0), 'Polygon', 'Bob', 'Subject', AnnotationBorderStyle::solid())
            ->addCaretAnnotation(new Rect(10, 160, 10, 10), 'Caret', 'Bob')
            ->addTextField('field', new Rect(10, 130, 40, 15), 'value', 'Helvetica', 10, false, Color::rgb(0, 0, 0), new FormFieldFlags(readOnly: true), 'default')
            ->addCheckbox('check', new Position(60, 137), 10, true)
            ->addRadioButton('radio', 'yes', new Position(80, 137), 10, true)
            ->addComboBox('combo', new Rect(95, 130, 40, 15), ['a' => 'A', 'b' => 'B'], 'a', 'Helvetica', 10, Color::rgb(0, 0, 0), new FormFieldFlags(editable: true), 'b')
            ->addListBox('list', new Rect(140, 120, 50, 30), ['a' => 'A', 'b' => 'B'], ['a'], 'Helvetica', 10, Color::rgb(0, 0, 0), new FormFieldFlags(multiSelect: true), ['b'])
            ->addSignatureField('signature', new Rect(10, 100, 50, 20))
            ->addPushButton(
                'push',
                'Go',
                new Rect(70, 100, 40, 20),
                'Helvetica',
                10,
                Color::rgb(0, 0, 0),
                new class implements ButtonAction
                {
                    public function toPdfDictionary(): DictionaryType
                    {
                        return new DictionaryType([
                            'S' => new NameType('ResetForm'),
                        ]);
                    }
                },
            );

        $internalPage = $this->internalPage($page);
        $annotations = $internalPage->getAnnotations();

        self::assertSame($page, $returnedPage);
        self::assertGreaterThanOrEqual(18, count($annotations));

        $popupParent = $annotations[1];
        $page->addPopupAnnotation($popupParent, new Rect(195, 200, 15, 15), true);

        self::assertGreaterThanOrEqual(19, count($internalPage->getAnnotations()));
        self::assertNotNull($this->internalDocument($document)->acroForm);
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
