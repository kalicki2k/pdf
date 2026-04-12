<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonChoice;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\ListOptions;
use Kalle\Pdf\Document\ListType;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TableHeaderScope;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Font\StandardFontGlyphRun;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Tests\Font\TrueTypeFontFixture;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Writer\IndirectObject;
use PHPUnit\Framework\TestCase;

use function preg_match;

final class DocumentSerializationPlanBuilderTest extends TestCase
{
    public function testItBuildsAMinimalSerializationPlan(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(Profile::standard(Version::V1_7));

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(Version::V1_7, $plan->fileStructure->version);
        self::assertSame(5, $plan->fileStructure->trailer->size);
        self::assertSame(1, $plan->fileStructure->trailer->rootObjectId);
        self::assertCount(4, $objects);
        self::assertSame(1, $objects[0]->objectId);
        self::assertSame('<< /Type /Catalog /Pages 2 0 R >>', $objects[0]->contents);
        self::assertSame(2, $objects[1]->objectId);
        self::assertSame('<< /Type /Pages /Count 1 /Kids [3 0 R] >>', $objects[1]->contents);
        self::assertSame(3, $objects[2]->objectId);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R >>', $objects[2]->contents);
        self::assertSame(4, $objects[3]->objectId);
        self::assertSame("<< /Length 0 >>\nstream\nendstream", $objects[3]->contents);
    }

    public function testItAddsAnInfoObjectWhenDocumentMetadataIsPresent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::standard(),
            title: 'Example Title',
            author: 'Sebastian Kalicki',
            subject: 'Example Subject',
            creator: 'Kalle PDF',
            creatorTool: 'pdf2 test suite',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(7, $plan->fileStructure->trailer->size);
        self::assertSame(6, $plan->fileStructure->trailer->infoObjectId);
        self::assertCount(6, $objects);
        self::assertSame('<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>', $objects[0]->contents);
        self::assertSame(5, $objects[4]->objectId);
        self::assertStringStartsWith('<< /Type /Metadata /Subtype /XML /Length ', $objects[4]->contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Example Title</rdf:li>', $objects[4]->contents);
        self::assertStringContainsString('<pdf:Producer>pdf2 test suite</pdf:Producer>', $objects[4]->contents);
        self::assertMatchesRegularExpression('/<xmp:CreateDate>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/xmp:CreateDate>/', $objects[4]->contents);
        self::assertSame(6, $objects[5]->objectId);
        self::assertStringStartsWith(
            '<< /Title (Example Title) /Author (Sebastian Kalicki) /Subject (Example Subject) /Creator (Kalle PDF) /Producer (pdf2 test suite) /CreationDate (D:',
            $objects[5]->contents,
        );
        self::assertStringContainsString('/ModDate (', $objects[5]->contents);
    }

    public function testItBuildsPdfA1bInfoDictionaryWithUnicodeMetadataWithoutRawUtf8Bytes(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Projektübersicht')
            ->author('Jörg Example')
            ->subject('Überblick')
            ->text('Unicode-Inhalt Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $infoObjectId = $plan->fileStructure->trailer->infoObjectId;
        $infoObject = current(array_filter(
            $objects,
            static fn ($object): bool => $object->objectId === $infoObjectId,
        ));

        self::assertNotFalse($infoObject);

        self::assertStringContainsString('/Title (Projekt\\374bersicht)', $infoObject->contents);
        self::assertStringContainsString('/Author (J\\366rg Example)', $infoObject->contents);
        self::assertStringContainsString('/Subject (\\334berblick)', $infoObject->contents);
        self::assertStringNotContainsString('Projektübersicht', $infoObject->contents);
        self::assertStringNotContainsString('Jörg Example', $infoObject->contents);
        self::assertStringNotContainsString('Überblick', $infoObject->contents);
    }

    public function testItAllowsValidatedLowLevelPageContentsForPdfA1(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "q\n0.5 g\n0 0 20 20 re\nf\nQ",
                ),
            ],
        );

        $plan = $builder->build($document);

        self::assertSame(8, $plan->fileStructure->trailer->size);
    }

    public function testItRejectsUnsafeRawPageContentsForPdfA1(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "q\n/GS1 gs\nQ",
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow low-level PDF operator "gs" in page content stream on page 1.',
        );

        $builder->build($document);
    }

    public function testItRejectsUnvalidatedLowLevelPageOperatorsForPdfA1(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "0 0 10 10 re\nfoo",
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow unvalidated low-level PDF operator "foo" in page content stream on page 1.',
        );

        $builder->build($document);
    }

    public function testItRejectsAdditionalImageDictionaryEntriesForPdfAProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    imageResources: [
                        'Im1' => new ImageSource(
                            width: 1,
                            height: 1,
                            colorSpace: ImageColorSpace::RGB,
                            bitsPerComponent: 8,
                            data: 'x',
                            additionalDictionaryEntries: ['/Intent /Perceptual'],
                        ),
                    ],
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow additional low-level image dictionary entries for image resource 1 on page 1 because they cannot be validated safely for PDF/A.',
        );

        $builder->build($document);
    }

    public function testItRejectsManualAnnotationsWithForbiddenActionsForPdfAProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $annotation = new readonly class implements PageAnnotation, AppearanceStreamAnnotation {
            public function pdfObjectContents(PageAnnotationRenderContext $context): string
            {
                return '<< /Type /Annot /Subtype /Link /Rect [0 0 20 20] /P '
                    . $context->pageObjectId
                    . ' 0 R /A << /S /Launch /F (calc.exe) >> >>';
            }

            public function markedContentId(): ?int
            {
                return null;
            }

            public function appearanceStreamDictionaryContents(?\Kalle\Pdf\Page\AnnotationAppearanceRenderContext $context = null): string
            {
                return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 20 20] /Resources << >> /Length 0 >>';
            }

            public function appearanceStreamContents(?\Kalle\Pdf\Page\AnnotationAppearanceRenderContext $context = null): string
            {
                return '';
            }
        };
        $document = new Document(
            profile: Profile::pdfA2b(),
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [$annotation],
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2b does not allow low-level key /Launch in annotation 1 on page 1.',
        );

        $builder->build($document);
    }

    public function testItAddsAnEncryptObjectAndTrailerEntriesForEncryptedDocuments(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf14(),
            encryption: Encryption::rc4_128('user', 'owner'),
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(6, $plan->fileStructure->trailer->size);
        self::assertSame(5, $plan->fileStructure->trailer->encryptObjectId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/i', (string) $plan->fileStructure->trailer->documentId);
        self::assertNotNull($plan->objectEncryptor);
        self::assertSame(5, $objects[4]->objectId);
        self::assertFalse($objects[4]->encryptable);
        self::assertStringStartsWith('<< /Filter /Standard /V 2 /R 3 /Length 128 /P -4 /O <', $objects[4]->contents);
        self::assertStringContainsString('/R 3 /Length 128 /P -4', $objects[4]->contents);
    }

    public function testItBuildsEmbeddedFileObjectsAndCatalogEntries(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            attachments: [
                new FileAttachment(
                    'demo.txt',
                    new EmbeddedFile('hello', 'text/plain'),
                    'Demo attachment',
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names [(demo.txt) 6 0 R] >> >> >>',
            $objects[0]->contents,
        );
        self::assertSame(5, $objects[4]->objectId);
        self::assertSame(
            "<< /Type /EmbeddedFile /Length 5 /Params << /Size 5 >> /Subtype /text#2Fplain >>\nstream\nhello\nendstream",
            $objects[4]->contents,
        );
        self::assertSame(
            '<< /Type /Filespec /F (demo.txt) /UF (demo.txt) /EF << /F 5 0 R /UF 5 0 R >> /Desc (Demo attachment) >>',
            $objects[5]->contents,
        );
    }

    public function testItDefaultsPdfA3AttachmentsToAssociatedFiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA3b(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    'Machine-readable source',
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/AF [6 0 R]', $objects[0]->contents);
        self::assertStringContainsString('/AFRelationship /Data', $objects[5]->contents);
    }

    public function testItDefaultsPdfA4fAttachmentsToAssociatedFiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA4f(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    'Machine-readable source',
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/AF [6 0 R]', $objects[0]->contents);
        self::assertStringContainsString('/AFRelationship /Data', $objects[5]->contents);
    }

    public function testItAllowsExplicitDocumentLevelAssociatedFilesForPdf20(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf20(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    associatedFileRelationship: AssociatedFileRelationship::SOURCE,
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/AF [6 0 R]', $objects[0]->contents);
        self::assertStringContainsString('/AFRelationship /Source', $objects[5]->contents);
    }

    public function testItDoesNotMarkPlainPdf20AttachmentsAsAssociatedFilesWithoutRelationship(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf20(),
            attachments: [
                new FileAttachment(
                    'demo.txt',
                    new EmbeddedFile('hello', 'text/plain'),
                    'Demo attachment',
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringNotContainsString('/AF [', $objects[0]->contents);
        self::assertStringNotContainsString('/AFRelationship /', $objects[5]->contents);
    }

    public function testItRejectsEmbeddedFileAttachmentsForUnsupportedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            attachments: [
                new FileAttachment('demo.txt', new EmbeddedFile('hello')),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow embedded file attachments.');

        $builder->build($document);
    }

    public function testItRejectsEmbeddedFileAttachmentsForPdfA1b(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            attachments: [
                new FileAttachment('demo.txt', new EmbeddedFile('hello')),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow embedded file attachments.');

        $builder->build($document);
    }

    public function testItRejectsAssociatedFilesForUnsupportedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf17(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    associatedFileRelationship: AssociatedFileRelationship::DATA,
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile standard does not allow document-level associated files for attachment 1.');

        $builder->build($document);
    }

    public function testItAddsAnAcroFormAndWidgetFieldObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField($this->testWidgetField()),
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertCount(6, $objects);
        self::assertSame('<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>', $objects[0]->contents);
        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R /Annots [6 0 R] >>',
            $objects[2]->contents,
        );
        self::assertSame('<< /Fields [6 0 R] /NeedAppearances true >>', $objects[4]->contents);
        self::assertSame(
            '<< /Type /Annot /Subtype /Widget /Rect [10 20 90 32] /P 3 0 R /T (customer_name) /TU (Customer name) /FT /Tx >>',
            $objects[5]->contents,
        );
    }

    public function testItRejectsAcroFormsForUnsupportedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            acroForm: (new AcroForm())->withField($this->testWidgetField()),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow AcroForm fields in the current implementation.');

        $builder->build($document);
    }

    public function testItRejectsAcroFormsForPdfA1b(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            acroForm: (new AcroForm())->withField($this->testWidgetField()),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow AcroForm fields in the current implementation.');

        $builder->build($document);
    }

    public function testItRejectsFormFieldsThatReferenceMissingPages(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField($this->testWidgetField(pageNumber: 2)),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form field "customer_name" targets page 2 which does not exist.');

        $builder->build($document);
    }

    public function testItBuildsATextFieldWithDefaultResources(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new TextField('customer_name', 1, 10.0, 20.0, 80.0, 12.0, 'Ada', 'Customer name'),
            ),
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/AcroForm 5 0 R', $objects[0]->contents);
        self::assertStringContainsString('/DR << /Font << /Helv << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>', $objects[4]->contents);
        self::assertSame(
            '<< /Type /Annot /Subtype /Widget /Rect [10 20 90 32] /P 3 0 R /T (customer_name) /TU (Customer name) /FT /Tx /DA (/Helv 12 Tf 0 g) /V (Ada) >>',
            $objects[5]->contents,
        );
    }

    public function testItBuildsACheckboxWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new CheckboxField('accept_terms', 1, 10.0, 20.0, 12.0, true, 'Accept terms'),
            ),
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertCount(8, $objects);
        self::assertStringContainsString('/Annots [6 0 R]', $objects[2]->contents);
        self::assertSame(
            '<< /Type /Annot /Subtype /Widget /Rect [10 20 22 32] /P 3 0 R /T (accept_terms) /TU (Accept terms) /FT /Btn /V /Yes /AS /Yes /AP << /N << /Off 7 0 R /Yes 8 0 R >> >> >>',
            $objects[5]->contents,
        );
        self::assertStringContainsString('/Subtype /Form', $objects[6]->contents);
        self::assertStringContainsString('/Subtype /Form', $objects[7]->contents);
    }

    public function testItBuildsTaggedPdfUaSingleWidgetFormFields(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Form',
            language: 'de-DE',
            acroForm: (new AcroForm())
                ->withField(new TextField('customer_name', 1, 10.0, 20.0, 80.0, 12.0, 'Ada', 'Customer name'))
                ->withField(new CheckboxField('accept_terms', 1, 10.0, 40.0, 12.0, true, 'Accept terms')),
        );

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($builder->build($document)->objects),
        ));

        self::assertStringContainsString('/Tabs /S', $serialized);
        self::assertSame(2, substr_count($serialized, '/Type /StructElem /S /Form'));
        self::assertStringContainsString('/StructParent 0', $serialized);
        self::assertStringContainsString('/StructParent 1', $serialized);
        self::assertStringContainsString('/Alt (Customer name)', $serialized);
        self::assertStringContainsString('/Alt (Accept terms)', $serialized);
        self::assertSame(2, substr_count($serialized, '/Type /OBJR /Obj'));
    }

    public function testItRejectsPdfUaRadioButtonsUntilTaggedChoiceStructureExists(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Form',
            language: 'de-DE',
            acroForm: (new AcroForm())->withField(
                (new RadioButtonGroup('delivery', alternativeName: 'Delivery method'))
                    ->withChoice(new RadioButtonChoice(1, 10.0, 20.0, 12.0, 'standard', true, 'Standard delivery'))
                    ->withChoice(new RadioButtonChoice(1, 30.0, 20.0, 12.0, 'express', false, 'Express delivery')),
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 does not allow radio buttons in the current tagged form implementation.');

        $builder->build($document);
    }

    public function testItBuildsARadioButtonGroupWithWidgetKids(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $group = (new RadioButtonGroup('delivery', alternativeName: 'Delivery method'))
            ->withChoice(new RadioButtonChoice(1, 10.0, 20.0, 12.0, 'standard'))
            ->withChoice(new RadioButtonChoice(1, 30.0, 20.0, 12.0, 'express', true, 'Express delivery'));
        $document = new Document(acroForm: (new AcroForm())->withField($group));

        $objects = iterator_to_array($builder->build($document)->objects);

        self::assertStringContainsString('/Fields [6 0 R]', $objects[4]->contents);
        self::assertStringContainsString('/Kids [7 0 R 10 0 R]', $objects[5]->contents);
        self::assertStringContainsString('/V /express', $objects[5]->contents);
        self::assertStringContainsString('/Annots [7 0 R 10 0 R]', $objects[2]->contents);
        self::assertStringContainsString('/Parent 6 0 R', $objects[6]->contents);
        self::assertStringContainsString('/AS /express', $objects[9]->contents);
    }

    public function testItBuildsAComboBox(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new ComboBoxField('status', 1, 10.0, 20.0, 80.0, 12.0, ['new' => 'New', 'done' => 'Done'], 'done', 'Status'),
            ),
        );

        $objects = iterator_to_array($builder->build($document)->objects);

        self::assertStringContainsString('/FT /Ch', $objects[5]->contents);
        self::assertStringContainsString('/Ff 131072', $objects[5]->contents);
        self::assertStringContainsString('/Opt [[(new) (New)] [(done) (Done)]]', $objects[5]->contents);
    }

    public function testItBuildsAListBox(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new ListBoxField('skills', 1, 10.0, 20.0, 80.0, 40.0, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills'),
            ),
        );

        $objects = iterator_to_array($builder->build($document)->objects);

        self::assertStringContainsString('/FT /Ch', $objects[5]->contents);
        self::assertStringContainsString('/Ff 2097152', $objects[5]->contents);
        self::assertStringContainsString('/V [(php) (pdf)]', $objects[5]->contents);
    }

    public function testItBuildsAPushButton(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new PushButtonField('open_docs', 1, 10.0, 20.0, 90.0, 18.0, 'Open docs', 'Open documentation', 'https://example.com/docs'),
            ),
        );

        $objects = iterator_to_array($builder->build($document)->objects);

        self::assertStringContainsString('/FT /Btn', $objects[5]->contents);
        self::assertStringContainsString('/Ff 65536', $objects[5]->contents);
        self::assertStringContainsString('/MK << /CA (Open docs) >>', $objects[5]->contents);
        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/docs) >>', $objects[5]->contents);
    }

    public function testItBuildsASignatureField(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            acroForm: (new AcroForm())->withField(
                new SignatureField('approval_signature', 1, 10.0, 20.0, 100.0, 30.0, 'Approval signature'),
            ),
        );

        $objects = iterator_to_array($builder->build($document)->objects);

        self::assertStringContainsString('/SigFlags 1', $objects[4]->contents);
        self::assertStringContainsString('/FT /Sig', $objects[5]->contents);
        self::assertStringContainsString('/AP << /N 7 0 R >>', $objects[5]->contents);
        self::assertStringContainsString('/Annots [6 0 R]', $objects[2]->contents);
        self::assertStringContainsString('/Subtype /Form', $objects[6]->contents);
    }

    public function testItRejectsSignatureFieldsForUnsupportedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            acroForm: (new AcroForm())->withField(
                new SignatureField('approval_signature', 1, 10.0, 20.0, 100.0, 30.0, 'Approval signature'),
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow AcroForm fields in the current implementation.');

        $builder->build($document);
    }

    public function testItRejectsSignatureFieldsForPdfA1b(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            acroForm: (new AcroForm())->withField(
                new SignatureField('approval_signature', 1, 10.0, 20.0, 100.0, 30.0, 'Approval signature'),
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow AcroForm fields in the current implementation.');

        $builder->build($document);
    }

    public function testItBuildsPageObjectsForAllDocumentPages(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            pages: [
                new Page(PageSize::A4(), "q\nQ"),
                new Page(PageSize::A5()),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(7, $plan->fileStructure->trailer->size);
        self::assertCount(6, $objects);
        self::assertSame('<< /Type /Pages /Count 2 /Kids [3 0 R 5 0 R] >>', $objects[1]->contents);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R >>', $objects[2]->contents);
        self::assertSame("<< /Length 3 >>\nstream\nq\nQ\nendstream", $objects[3]->contents);
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 419.528 595.276] /Resources << >> /Contents 6 0 R >>', $objects[4]->contents);
        self::assertSame("<< /Length 0 >>\nstream\nendstream", $objects[5]->contents);
    }

    private function testWidgetField(int $pageNumber = 1): WidgetFormField
    {
        return new readonly class ('customer_name', $pageNumber, 10.0, 20.0, 80.0, 12.0, 'Customer name') extends WidgetFormField {
            public function pdfObjectContents(
                FormFieldRenderContext $context,
                int $fieldObjectId,
                array $relatedObjectIds = [],
            ): string {
                return '<< ' . implode(' ', [
                    ...$this->widgetDictionaryEntries($context, $fieldObjectId),
                    '/FT /Tx',
                ]) . ' >>';
            }
        };
    }

    public function testItWritesSelectedEncryptionPermissionsIntoTheEncryptDictionary(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf16(),
            encryption: Encryption::aes128('user', 'owner')->withPermissions(
                new Permissions(print: false, modify: true, copy: false, annotate: true),
            ),
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/P -24', $objects[4]->contents);
    }

    public function testItRejectsEncryptionForPdfAProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            encryption: Encryption::rc4_128('user', 'owner'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u does not allow encryption.');

        $builder->build($document);
    }

    public function testItRejectsEncryptionForPdfA1Profiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            encryption: Encryption::rc4_128('user', 'owner'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow encryption.');

        $builder->build($document);
    }

    public function testItKeepsMultiplePagesBuiltThroughTheBuilder(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame('<< /Type /Pages /Count 2 /Kids [3 0 R 5 0 R] >>', $objects[1]->contents);
        self::assertStringContainsString('[<50>', $objects[3]->contents);
        self::assertStringContainsString('[<50>', $objects[5]->contents);
        self::assertStringContainsString('] TJ', $objects[3]->contents);
        self::assertStringContainsString('] TJ', $objects[5]->contents);
    }

    public function testItPrependsBackgroundDrawingCommandsToPageContents(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Cover')
            ->newPage(new PageOptions(
                pageSize: PageSize::A5(),
                orientation: PageOrientation::LANDSCAPE,
                margin: Margin::all(24.0),
                backgroundColor: Color::hex('#f5f5f5'),
            ))
            ->text('Appendix')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString("0.961 0.961 0.961 rg\n0 0 595.276 419.528 re\nf\nQ", $objects[5]->contents);
        self::assertStringContainsString('(Appendix) Tj', $objects[5]->contents);
    }

    public function testItUsesTheCorrectPdfColorOperatorForGrayAndCmykBackgrounds(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            pages: [
                new Page(PageSize::A4(), backgroundColor: Color::gray(0.5)),
                new Page(PageSize::A4(), backgroundColor: Color::cmyk(0.1, 0.2, 0.3, 0.4)),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString("0.5 g\n0 0 595.276 841.89 re\nf\nQ", $objects[3]->contents);
        self::assertStringContainsString("0.1 0.2 0.3 0.4 k\n0 0 595.276 841.89 re\nf\nQ", $objects[5]->contents);
    }

    public function testItAddsEncodingToStandardFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Hello', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', $objects[4]->contents);
    }

    public function testItAddsWesternDifferencesEncodingForPdf10StandardFonts(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdf10())
            ->text('ÄÖÜäöüß', new TextOptions(fontName: StandardFont::HELVETICA->value))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringStartsWith(
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding << /Type /Encoding /BaseEncoding /StandardEncoding /Differences [128 /Adieresis',
            $objects[4]->contents,
        );
    }

    public function testItAddsIsoLatin1EncodingToExplicitlyConfiguredStandardFonts(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdf10(),
            pages: [
                new Page(
                    PageSize::A4(),
                    fontResources: [
                        'F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::ISO_LATIN_1),
                    ],
                ),
            ],
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /ISOLatin1Encoding >>',
            $objects[4]->contents,
        );
    }

    public function testItAddsCoreFontGlyphDifferencesWhenExplicitGlyphNamesNeedThem(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->glyphs(StandardFontGlyphRun::fromGlyphNames(StandardFont::HELVETICA, [
                'A',
                'Euro',
                'Aogonek',
            ]))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/BaseEncoding /WinAnsiEncoding', $objects[4]->contents);
        self::assertStringContainsString('/Differences [128 /Euro /Aogonek]', $objects[4]->contents);
    }

    public function testItAddsImageXObjectsToPageResources(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::alternativeText('Example image'),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>',
            $objects[2]->contents,
        );
        self::assertStringContainsString('/Subtype /Image', $objects[4]->contents);
        self::assertStringContainsString('/Filter /DCTDecode', $objects[4]->contents);
        self::assertNotNull($objects[4]->streamDictionaryContents);
        self::assertSame('jpeg-bytes', $objects[4]->streamContents);
    }

    public function testItAddsLinkAnnotationsToPageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R /Annots [5 0 R] >>',
            $objects[2]->contents,
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [40 500 160 516] /Border [0 0 0] /P 3 0 R /A << /S /URI /URI (https://example.com) >> /Contents (Open Example) >>',
            $objects[4]->contents,
        );
    }

    public function testItAddsTextAnnotationsToPageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R /Annots [5 0 R] >>',
            $objects[2]->contents,
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Text /Rect [40 500 58 518] /P 3 0 R /Contents (Kommentar) /Name /Comment /Open true /T (QA) >>',
            $objects[4]->contents,
        );
    }

    public function testItAddsHighlightAnnotationsToPageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->highlightAnnotation(40, 500, 80, 10, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 4 0 R /Annots [5 0 R] >>',
            $objects[2]->contents,
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Highlight /Rect [40 500 120 510] /P 3 0 R /QuadPoints [40 510 120 510 40 500 120 500] /C [1 1 0] /Contents (Markiert) /T (QA) >>',
            $objects[4]->contents,
        );
    }

    public function testItAddsFreeTextAnnotationsToPageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->freeTextAnnotation(
                'Kommentar',
                40,
                500,
                120,
                32,
                new TextOptions(fontSize: 12, color: Color::rgb(0, 0, 0.4)),
                Color::rgb(0.2, 0.2, 0.2),
                Color::rgb(1, 1, 0.8),
                'QA',
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /FreeText', $serialized);
        self::assertStringContainsString('/DA (/', $serialized);
        self::assertStringContainsString('/C [0.2 0.2 0.2]', $serialized);
        self::assertStringContainsString('/IC [1 1 0.8]', $serialized);
        self::assertStringContainsString('/Resources << /Font << /', $serialized);
    }

    public function testItAddsInternalLinkDestinationsToPageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage()
            ->linkToPage(1, 40, 500, 120, 16, 'Back to page 1')
            ->linkToPagePosition(1, 72, 700, 40, 460, 120, 16, 'Back to heading')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $objectsById = [];

        foreach ($objects as $object) {
            $objectsById[$object->objectId] = $object;
        }

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Resources << >> /Contents 6 0 R /Annots [8 0 R 9 0 R] >>',
            $objectsById[5]->contents,
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [40 500 160 516] /Border [0 0 0] /P 5 0 R /Dest [3 0 R /Fit] /Contents (Back to page 1) >>',
            $objectsById[8]->contents,
        );
        self::assertSame(
            '<< /Type /Annot /Subtype /Link /Rect [40 460 160 476] /Border [0 0 0] /P 5 0 R /Dest [3 0 R /XYZ 72 700 null] /Contents (Back to heading) >>',
            $objectsById[9]->contents,
        );
    }

    public function testItAddsNamedDestinationsToTheCatalogAndLinksCanReferenceThem(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->namedDestination('intro')
            ->text('Open intro', new TextOptions(
                link: LinkTarget::namedDestination('intro'),
            ))
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Dests << /intro [3 0 R /Fit] >>', $serialized);
        self::assertStringContainsString('/Dest /intro', $serialized);
    }

    public function testItAddsTaggedPdfUaLinkAnnotations(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Tabs /S', $serialized);
        self::assertStringContainsString('/StructParent 0', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Link', $serialized);
        self::assertStringContainsString('/Type /OBJR /Obj', $serialized);
    }

    public function testItRejectsPdfUaLinkAnnotationsWithoutAlternativeText(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->link('https://example.com', 40, 500, 120, 16)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires alternative text for link annotation 1 on page 1');

        $builder->build($document);
    }

    public function testItBuildsPdfA1LinkAnnotationsWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Subtype /Link', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
    }

    public function testItBuildsPdfA2uLinkAnnotationsWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('PDF/A-2u Regression Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->link('https://example.com/spec', 40, 500, 120, 16, 'Specification Link')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Link', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 120 16]', $serialized);
    }

    public function testItBuildsPdfA2uTextAnnotationsWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('PDF/A-2u Regression Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Text', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 18 18]', $serialized);
    }

    public function testItRejectsPdfA1bTextAnnotations(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('annotation appearance streams are required on page 1');

        $builder->build($document);
    }

    public function testItBuildsPdfA2uHighlightAnnotationsWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('PDF/A-2u Regression Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->highlightAnnotation(40, 500, 80, 10, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Highlight', $serialized);
        self::assertStringContainsString('/QuadPoints [40 510 120 510 40 500 120 500]', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 80 10]', $serialized);
    }

    public function testItRejectsPdfA1bHighlightAnnotations(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->highlightAnnotation(40, 500, 80, 10, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('annotation appearance streams are required on page 1');

        $builder->build($document);
    }

    public function testItBuildsPdfA2uFreeTextAnnotationsWithAppearanceStreams(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->language('de-DE')
            ->freeTextAnnotation(
                'Kommentar Привет',
                40,
                500,
                160,
                36,
                new TextOptions(
                    fontSize: 12,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    color: Color::rgb(0, 0, 0.4),
                ),
                Color::rgb(0.2, 0.2, 0.2),
                Color::rgb(1, 1, 0.8),
                'QA',
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /FreeText', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
        self::assertStringContainsString('/Subtype /Form /FormType 1 /BBox [0 0 160 36]', $serialized);
        self::assertStringContainsString('/Resources << /Font << /', $serialized);
    }

    public function testItRejectsPdfA1bFreeTextAnnotations(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->freeTextAnnotation(
                'Kommentar Привет',
                40,
                500,
                160,
                36,
                new TextOptions(
                    fontSize: 12,
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    color: Color::rgb(0, 0, 0.4),
                ),
                Color::rgb(0.2, 0.2, 0.2),
                Color::rgb(1, 1, 0.8),
                'QA',
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('annotation appearance streams are required on page 1');

        $builder->build($document);
    }

    public function testItAllowsSimpleEmbeddedFontsForPdfA1b(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('ASCII only', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringNotContainsString('/Subtype /Type0', $serialized);
        self::assertStringNotContainsString('/ToUnicode', $serialized);
    }

    public function testItRejectsSimpleEmbeddedFontsForPdfA1a(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->paragraph('ASCII only', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires embedded Unicode fonts');

        $builder->build($document);
    }

    public function testItRejectsStandardFontsForPdfA1b(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('ASCII only')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b requires embedded fonts. Found standard font "Helvetica" on page 1.');

        $builder->build($document);
    }

    public function testItAddsTaggedPdfUaTextLinks(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->text('Read more', new TextOptions(
                link: LinkTarget::externalUrl('https://example.com'),
            ))
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/P << /MCID 0 >> BDC', $serialized);
        self::assertStringContainsString('/Link << /MCID 1 >> BDC', $serialized);
        self::assertStringContainsString('/StructParent 1', $serialized);
        self::assertStringContainsString('/K [1 << /Type /OBJR /Obj', $serialized);
    }

    public function testItAddsMultipleTextSegmentLinksToSerialization(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                new TextSegment('Docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' und '),
                new TextSegment('API', LinkTarget::externalUrl('https://example.com/api')),
            ])
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/URI (https://example.com/docs)', $serialized);
        self::assertStringContainsString('/Contents (Docs)', $serialized);
        self::assertStringContainsString('/URI (https://example.com/api)', $serialized);
        self::assertStringContainsString('/Contents (API)', $serialized);
    }

    public function testItMergesAdjacentTextSegmentsWithTheSameLinkInSerialization(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                new TextSegment('Read', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' now'),
            ])
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertSame(1, substr_count($serialized, '/URI (https://example.com/docs)'));
        self::assertStringContainsString('/Contents (Read docs)', $serialized);
    }

    public function testItGroupsWrappedPdfUaTextLinksIntoOneStructElem(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                new TextSegment('Read docs', LinkTarget::externalUrl('https://example.com/docs')),
            ], new TextOptions(width: 45))
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertSame(2, substr_count($serialized, '/Subtype /Link'));
        self::assertSame(1, substr_count($serialized, '/Type /StructElem /S /Link'));
        self::assertSame(2, substr_count($serialized, '/Type /OBJR /Obj'));
        self::assertStringContainsString('/Alt (Read docs)', $serialized);
    }

    public function testItUsesSeparateAccessibleLabelsForPdfUaTextLinks(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                TextSegment::link(
                    'Docs',
                    TextLink::externalUrl(
                        'https://example.com/docs',
                        contents: 'Open docs section',
                        accessibleLabel: 'Read the documentation section',
                    ),
                ),
            ])
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Contents (Open docs section)', $serialized);
        self::assertStringContainsString('/Alt (Read the documentation section)', $serialized);
    }

    public function testItGroupsRectLinksWithExplicitOptionsIntoOnePdfUaLinkStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->linkWithOptions(
                'https://example.com/docs',
                40,
                500,
                120,
                16,
                new LinkAnnotationOptions(
                    contents: 'Open docs section',
                    accessibleLabel: 'Read the documentation section',
                    groupKey: 'docs-link',
                ),
            )
            ->linkWithOptions(
                'https://example.com/docs',
                40,
                470,
                120,
                16,
                new LinkAnnotationOptions(
                    contents: 'Open docs section',
                    accessibleLabel: 'Read the documentation section',
                    groupKey: 'docs-link',
                ),
            )
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertSame(2, substr_count($serialized, '/Subtype /Link'));
        self::assertSame(1, substr_count($serialized, '/Type /StructElem /S /Link'));
        self::assertSame(2, substr_count($serialized, '/Type /OBJR /Obj'));
        self::assertStringContainsString('/Alt (Read the documentation section)', $serialized);
    }

    public function testItBuildsSoftMaskImageObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->image(
                ImageSource::flate(
                    'rgb-data',
                    2,
                    1,
                    ImageColorSpace::RGB,
                    softMask: ImageSource::alphaMask('alpha-data', 2, 1),
                ),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('Transparent image'),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/XObject << /Im1 5 0 R >>', $objects[2]->contents);
        self::assertStringContainsString('/SMask 6 0 R', $objects[4]->contents);
        self::assertStringContainsString('/ColorSpace /DeviceGray', $objects[5]->contents);
    }

    public function testItBuildsManualImageResourcesAsExplicitStreamObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            pages: [
                new Page(
                    PageSize::A4(),
                    imageResources: [
                        'Im1' => ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                    ],
                ),
            ],
        );

        $plan = $builder->build($document);
        $imageObject = $this->findStreamObject(iterator_to_array($plan->objects), '/Subtype /Image');

        self::assertNotNull($imageObject);
        self::assertSame('jpeg-bytes', $imageObject->streamContents);
        self::assertStringContainsString('/Filter /DCTDecode', (string) $imageObject->streamDictionaryContents);
    }

    public function testItRejectsPdfUaImagesWithoutAccessibilityMetadata(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB), ImagePlacement::at(40, 500, width: 120))
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tagged PDF profiles require accessibility metadata for image 1 on page 1.');

        $builder->build($document);
    }

    public function testItAddsCatalogMetadataLangAndMarkInfoForTaggedProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
            language: 'de-DE',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertSame(
            '<< /Type /Catalog /Pages 2 0 R /Metadata 7 0 R /Lang (de-DE) /MarkInfo << /Marked true >> /StructTreeRoot 5 0 R >>',
            $objects[0]->contents,
        );
        self::assertSame('<< /Type /StructTreeRoot /K [6 0 R] >>', $objects[4]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 5 0 R /K [] >>', $objects[5]->contents);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $objects[6]->contents);
    }

    public function testItAddsTaggedPdfA1aTextStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->heading('Einleitung Привет', 1, new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->paragraph('Absatztext Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/MarkInfo << /Marked true >>', $serialized);
        self::assertStringContainsString('/H1 << /MCID 0 >> BDC', $serialized);
        self::assertStringContainsString('/P << /MCID 1 >> BDC', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /H1', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /P', $serialized);
        self::assertStringContainsString('/Nums [0 [', $serialized);
    }

    public function testItAddsTaggedPdfA1aBulletListStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                text: new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    width: 260,
                ),
            )
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', $serialized);
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /L', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /LI', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $serialized);
        self::assertStringContainsString('/Nums [0 [', $serialized);
    }

    public function testItAddsTaggedPdfA1aNumberedListStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                new ListOptions(type: ListType::NUMBERED, start: 5),
                new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    width: 260,
                ),
            )
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', $serialized);
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /L', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /LI', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Lbl', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /LBody', $serialized);
        self::assertSame(2, substr_count($serialized, '/Type /StructElem /S /Lbl'));
        self::assertStringContainsString('/Nums [0 [', $serialized);
    }

    public function testItAddsTaggedPdfA1aTableStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->table(
                Table::define(
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                )
                    ->withPlacement(TablePlacement::at(72.0, 700.0, 360.0))
                    ->withCaption(TableCaption::text('Quarterly summary Привет'))
                    ->withHeaderRows(
                        TableRow::fromCells(
                            TableCell::text('Регион', rowspan: 2)->withHeaderScope(TableHeaderScope::BOTH),
                            TableCell::text('План', colspan: 2),
                        ),
                        TableRow::fromTexts('Q1', 'Q2 Привет'),
                    )
                    ->withRows(
                        TableRow::fromCells(
                            TableCell::text('Север')->withHeaderScope(TableHeaderScope::ROW),
                            TableCell::text('12'),
                            TableCell::text('14'),
                        ),
                        TableRow::fromTexts('Юг', '10', '11'),
                    )
                    ->withFooterRows(
                        TableRow::fromTexts('Итого', '22', '25'),
                    )
                    ->withTextOptions(new TextOptions(
                        fontSize: 12,
                        lineHeight: 15,
                        embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    )),
            )
            ->build();

        $plan = $builder->build($document);
        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/Type /StructElem /S /Table', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Sect', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /TH', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /TD', $serialized);
    }

    public function testItAddsTaggedPdfA1aMultipageParentTreeEntries(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->heading('Kapitel Eins Привет', 1, new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->paragraph('Erste Seite Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->newPage()
            ->heading('Kapitel Zwei Привет', 1, new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->paragraph('Zweite Seite Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($builder->build($document)->objects),
        ));

        self::assertStringContainsString('/StructParents 0', $serialized);
        self::assertStringContainsString('/StructParents 1', $serialized);
        self::assertStringContainsString('/Nums [0 [', $serialized);
        self::assertStringContainsString('1 [', $serialized);
        self::assertGreaterThanOrEqual(2, substr_count($serialized, '/Type /StructElem /S /H1'));
        self::assertGreaterThanOrEqual(2, substr_count($serialized, '/Type /StructElem /S /P'));
    }

    public function testItRejectsPdfA1aPagesWithRawTextOperatorsWithoutTaggedStructure(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $structuredDocument = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->paragraph('Absatztext Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();
        $rawTextPage = new Page(
            $structuredDocument->pages[0]->size,
            contents: $structuredDocument->pages[0]->contents,
        );
        $document = new Document(
            profile: $structuredDocument->profile,
            pages: [$structuredDocument->pages[0], $rawTextPage],
            title: $structuredDocument->title,
            language: $structuredDocument->language,
            creator: $structuredDocument->creator,
            creatorTool: $structuredDocument->creatorTool,
            pdfaOutputIntent: $structuredDocument->pdfaOutputIntent,
            taggedTextBlocks: $structuredDocument->taggedTextBlocks,
            taggedLists: [],
            taggedTables: [],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires structured marked content on page 2');

        $builder->build($document);
    }

    public function testItRejectsPdfA1aPagesWithUnstructuredTextResources(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $structuredDocument = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->paragraph('Absatztext Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();
        $document = new Document(
            profile: $structuredDocument->profile,
            pages: $structuredDocument->pages,
            title: $structuredDocument->title,
            language: $structuredDocument->language,
            creator: $structuredDocument->creator,
            creatorTool: $structuredDocument->creatorTool,
            pdfaOutputIntent: $structuredDocument->pdfaOutputIntent,
            taggedTextBlocks: [],
            taggedLists: [],
            taggedTables: [],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires structured content');

        $builder->build($document);
    }

    public function testItAddsPdfAOutputIntentAndIdentificationMetadata(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfA2u(),
            title: 'Archive Copy',
        );

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/Metadata 5 0 R', $objects[0]->contents);
        self::assertStringContainsString('/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1', $objects[0]->contents);
        self::assertStringContainsString('/DestOutputProfile 6 0 R', $objects[0]->contents);
        self::assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $objects[4]->contents);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $objects[4]->contents);
        self::assertStringStartsWith('<< /N 3 /Length ', $objects[5]->contents);
    }

    public function testItUsesACustomPdfAOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $path = tempnam(sys_get_temp_dir(), 'pdf2-icc-');
        self::assertNotFalse($path);
        file_put_contents($path, 'ICC');

        try {
            $document = new Document(
                profile: Profile::pdfA2u(),
                title: 'Archive Copy',
                pdfaOutputIntent: new PdfAOutputIntent($path, 'Custom RGB', 'Custom profile', 4),
            );

            $plan = $builder->build($document);
            $objects = iterator_to_array($plan->objects);

            self::assertStringContainsString('/OutputConditionIdentifier (Custom RGB)', $objects[0]->contents);
            self::assertStringContainsString('/Info (Custom profile)', $objects[0]->contents);
            self::assertStringStartsWith("<< /N 4 /Length 3 >>\nstream\nICC", $objects[5]->contents);
            self::assertStringEndsWith('endstream', $objects[5]->contents);
        } finally {
            @unlink($path);
        }
    }

    public function testItRejectsTaggedProfilesWithoutLanguage(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible Copy',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires a document language.');

        $builder->build($document);
    }

    public function testItAllowsPdfA1aWithoutLanguage(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->paragraph('Absatztext Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($builder->build($document)->objects),
        ));

        self::assertStringContainsString('/MarkInfo << /Marked true >>', $serialized);
        self::assertStringNotContainsString('/Lang (', $serialized);
    }

    public function testItRejectsStandardFontsForPdfAProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->text('Hello')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-2u requires embedded fonts. Found standard font "Helvetica" on page 1.');

        $builder->build($document);
    }

    public function testItRejectsSoftMaskTransparencyForPdfA1Profiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->image(
                ImageSource::flate(
                    'rgb-data',
                    2,
                    1,
                    ImageColorSpace::RGB,
                    softMask: ImageSource::alphaMask('alpha-data', 2, 1),
                ),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('Transparent image'),
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow soft-mask image transparency for image resource 1 on page 1.');

        $builder->build($document);
    }

    public function testItRejectsCustomImageColorSpacesForPdfA1Profiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->image(
                ImageSource::indexed(
                    'palette-data',
                    1,
                    1,
                    8,
                    "\x80\x80\x80",
                ),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('Indexed image'),
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b does not allow custom image color space definitions in the current implementation for image resource 1 on page 1.');

        $builder->build($document);
    }

    public function testItAllowsCmykJpegImagesForPdfA1ProfilesWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->pdfaOutputIntent(\Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk())
            ->text('CMYK JPEG Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->image(
                $this->jpegImageSourceFromBytes(\Kalle\Pdf\Tests\Image\JpegFixture::tinyCmykJpegBytes()),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('CMYK image'),
            )
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($builder->build($document)->objects),
        ));

        self::assertStringContainsString('/OutputConditionIdentifier (Artifex CMYK SWOP Profile)', $serialized);
        self::assertStringContainsString('/N 4 /Length ', $serialized);
        self::assertStringContainsString('/ColorSpace /DeviceCMYK', $serialized);
    }

    public function testItRejectsCmykImagesForPdfA1ProfilesWithRgbOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->image(
                $this->jpegImageSourceFromBytes(\Kalle\Pdf\Tests\Image\JpegFixture::tinyCmykJpegBytes()),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('CMYK image'),
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b requires a CMYK PDF/A output intent for CMYK image resource 1 on page 1.');

        $builder->build($document);
    }

    public function testItRejectsRgbImagesForPdfA1ProfilesWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->pdfaOutputIntent(\Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk())
            ->image(
                $this->jpegImageSourceFromBytes(\Kalle\Pdf\Tests\Image\JpegFixture::tinyRgbJpegBytes()),
                ImagePlacement::at(10, 20),
                ImageAccessibility::alternativeText('RGB image'),
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/A-1b requires an RGB PDF/A output intent for RGB image resource 1 on page 1.');

        $builder->build($document);
    }

    public function testItRejectsCmykTextForPdfA1bWithRgbOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->text('CMYK text', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::cmyk(0.1, 0.2, 0.3, 0.4),
            ))
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow CMYK color in text operations in page content stream on page 1 when the active PDF/A output intent is RGB.',
        );

        $builder->build($document);
    }

    public function testItAllowsRgbTextForPdfA1bWithRgbOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    contents: "BT\n0.1 0.2 0.3 rg\n/F1 12 Tf\n10 20 Td\n(RGB) Tj\nET",
                ),
            ],
        );

        self::assertNotEmpty(iterator_to_array($builder->build($document)->objects));
    }

    public function testItRejectsRgbTextForPdfA1bWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1b())
            ->title('Archive Copy')
            ->pdfaOutputIntent(\Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk())
            ->text('RGB text', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                color: Color::rgb(0.1, 0.2, 0.3),
            ))
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow RGB color in text operations in page content stream on page 1 when the active PDF/A output intent is CMYK.',
        );

        $builder->build($document);
    }

    public function testItAllowsCmykTextForPdfA1bWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pdfaOutputIntent: \Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk(),
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    contents: "BT\n0.1 0.2 0.3 0.4 k\n/F1 12 Tf\n10 20 Td\n(CMYK) Tj\nET",
                ),
            ],
        );

        self::assertNotEmpty(iterator_to_array($builder->build($document)->objects));
    }

    public function testItRejectsCmykBackgroundGraphicsForPdfA1bWithRgbOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    backgroundColor: Color::cmyk(0.1, 0.2, 0.3, 0.4),
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow CMYK color in page background graphics on page 1 when the active PDF/A output intent is RGB.',
        );

        $builder->build($document);
    }

    public function testItAllowsRgbBackgroundGraphicsForPdfA1bWithRgbOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    backgroundColor: Color::rgb(0.1, 0.2, 0.3),
                ),
            ],
        );

        self::assertNotEmpty(iterator_to_array($builder->build($document)->objects));
    }

    public function testItRejectsRgbGraphicsOperatorsForPdfA1bWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pdfaOutputIntent: \Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk(),
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    contents: "0.1 0.2 0.3 RG\n10 10 m\n100 10 l\nS",
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow RGB color in graphics operations in page content stream on page 1 when the active PDF/A output intent is CMYK.',
        );

        $builder->build($document);
    }

    public function testItAllowsCmykGraphicsOperatorsForPdfA1bWithCmykOutputIntent(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new \Kalle\Pdf\Document\Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pdfaOutputIntent: \Kalle\Pdf\Document\Metadata\PdfAOutputIntent::defaultCmyk(),
            pages: [
                new \Kalle\Pdf\Page\Page(
                    \Kalle\Pdf\Page\PageSize::A4(),
                    contents: "0.1 0.2 0.3 0.4 K\n10 10 m\n100 10 l\nS",
                ),
            ],
        );

        self::assertNotEmpty(iterator_to_array($builder->build($document)->objects));
    }

    public function testItRejectsTaggedProfilesWithoutTitle(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = new Document(
            profile: Profile::pdfUa1(),
            language: 'de-DE',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile PDF/UA-1 requires a document title.');

        $builder->build($document);
    }

    public function testItAllowsDecorativeImagesForPdfUaProfiles(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::decorative(),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/XObject << /Im1 5 0 R >>', $objects[2]->contents);
        self::assertStringContainsString('/Artifact BMC', $objects[3]->contents);
        self::assertStringContainsString('/StructTreeRoot 6 0 R', $objects[0]->contents);
        self::assertSame('<< /Type /StructTreeRoot /K [7 0 R] >>', $objects[5]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 6 0 R /K [] >>', $objects[6]->contents);
    }

    public function testItBuildsTaggedFigureStructureForPdfUaImages(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->image(
                ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB),
                ImagePlacement::at(40, 500, width: 120),
                ImageAccessibility::alternativeText('Logo'),
            )
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);

        self::assertStringContainsString('/StructTreeRoot 6 0 R', $objects[0]->contents);
        self::assertStringContainsString('/StructParents 0', $objects[2]->contents);
        self::assertStringContainsString('/Figure << /MCID 0 >> BDC', $objects[3]->contents);
        self::assertSame('<< /Type /StructTreeRoot /K [7 0 R] /ParentTree 8 0 R >>', $objects[5]->contents);
        self::assertSame('<< /Type /StructElem /S /Document /P 6 0 R /K [9 0 R] >>', $objects[6]->contents);
        self::assertSame('<< /Nums [0 [9 0 R]] >>', $objects[7]->contents);
        self::assertSame('<< /Type /StructElem /S /Figure /P 7 0 R /Pg 3 0 R /Alt (Logo) /K 0 >>', $objects[8]->contents);
    }

    public function testItBuildsEmbeddedTrueTypeFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /TrueType', $serialized);
        self::assertStringContainsString('/FontDescriptor', $serialized);
        self::assertStringContainsString('/FontFile2', $serialized);
        self::assertStringContainsString('/BaseFont /TestFont-Regular', $serialized);
        self::assertGreaterThanOrEqual(2, $this->countStreamObjects($objects));
    }

    public function testItBuildsManualEmbeddedTrueTypeFontFileAsAnExplicitStreamObject(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $font = EmbeddedFontDefinition::fromSource(
            EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalTrueTypeFontBytes()),
        );
        $document = new Document(
            pages: [
                new Page(
                    PageSize::A4(),
                    fontResources: ['F1' => PageFont::embedded($font)],
                ),
            ],
        );

        $plan = $builder->build($document);
        $fontFileObject = $this->findStreamObject(iterator_to_array($plan->objects), '/Length1 ');

        self::assertNotNull($fontFileObject);
        self::assertSame($font->fontFileStreamData(), $fontFileObject->streamContents);
    }

    public function testItBuildsEmbeddedCffFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('A', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalCffOpenTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type1', $serialized);
        self::assertStringContainsString('/FontDescriptor', $serialized);
        self::assertStringContainsString('/FontFile3', $serialized);
        self::assertStringContainsString('/Subtype /OpenType', $serialized);
        self::assertStringContainsString('/BaseFont /TestCff-Regular', $serialized);
        self::assertGreaterThanOrEqual(2, $this->countStreamObjects($objects));
    }

    public function testItBuildsUnicodeEmbeddedTrueTypeFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->newPage()
            ->text('中😀', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type0', $serialized);
        self::assertStringContainsString('/Subtype /CIDFontType2', $serialized);
        self::assertStringContainsString('/Encoding /Identity-H', $serialized);
        self::assertStringContainsString('/CIDToGIDMap', $serialized);
        self::assertStringContainsString('/ToUnicode', $serialized);
        self::assertMatchesRegularExpression('/\\/BaseFont \\/[A-Z]{6}\\+TestFont-Regular/', $serialized);
        self::assertStringContainsString('<0001> <0416>', $serialized);
        self::assertStringContainsString('<0001> <4E2D>', $serialized);
        self::assertStringContainsString('<0002> <D83DDE00>', $serialized);
        self::assertTrue($this->containsStreamObject($objects, '/Length1'));
        self::assertTrue($this->containsStreamObject($objects, '<0001> <0416>'));
        self::assertGreaterThanOrEqual(3, $this->countStreamObjects($objects));
        self::assertSame(1, preg_match('/\\/Length1 ([0-9]+)/', $serialized, $matches));
        if (!isset($matches[1])) {
            self::fail('Expected a /Length1 entry in the serialized font stream.');
        }
        self::assertLessThan(strlen(TrueTypeFontFixture::minimalUnicodeTrueTypeFontBytes()), (int) $matches[1]);
    }

    public function testItBuildsUnicodeEmbeddedCffFontObjects(): void
    {
        $builder = new DocumentSerializationPlanBuilder();
        $document = DefaultDocumentBuilder::make()
            ->text('Ж', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->text('中😀', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromString(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()),
            ))
            ->build();

        $plan = $builder->build($document);
        $objects = iterator_to_array($plan->objects);
        $serialized = implode("\n", array_map(static fn ($object): string => $object->contents, $objects));

        self::assertStringContainsString('/Subtype /Type0', $serialized);
        self::assertStringContainsString('/Subtype /CIDFontType0', $serialized);
        self::assertStringContainsString('/Encoding /Identity-H', $serialized);
        self::assertStringContainsString('/FontFile3', $serialized);
        self::assertStringContainsString('/Subtype /OpenType', $serialized);
        self::assertMatchesRegularExpression('/\\/BaseFont \\/[A-Z]{6}\\+TestCff-Regular/', $serialized);
        self::assertStringNotContainsString('/CIDToGIDMap', $serialized);
        self::assertStringContainsString('<0001> <0416>', $serialized);
        self::assertStringContainsString('<0002> <4E2D>', $serialized);
        self::assertStringContainsString('<0003> <D83DDE00>', $serialized);
        self::assertTrue($this->containsStreamObject($objects, '/Subtype /OpenType'));
        self::assertTrue($this->containsStreamObject($objects, '<0001> <0416>'));
        self::assertGreaterThanOrEqual(2, $this->countStreamObjects($objects));
        self::assertSame(1, preg_match('/<< \\/Length ([0-9]+) \\/Subtype \\/OpenType >>/', $serialized, $matches));
        if (!isset($matches[1])) {
            self::fail('Expected an OpenType stream length in the serialized font stream.');
        }
        self::assertLessThan(strlen(TrueTypeFontFixture::minimalUnicodeCffOpenTypeFontBytes()), (int) $matches[1]);
    }

    /**
     * @param array<array-key, IndirectObject> $objects
     */
    private function findStreamObject(array $objects, string $needle): ?IndirectObject
    {
        foreach ($objects as $object) {
            if (
                ($object->streamDictionaryContents !== null && str_contains($object->streamDictionaryContents, $needle))
                || ($object->streamContents !== null && str_contains($object->streamContents, $needle))
                || str_contains($object->contents, $needle)
            ) {
                return $object;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, IndirectObject> $objects
     */
    private function containsStreamObject(array $objects, string $needle): bool
    {
        foreach ($objects as $object) {
            if (str_contains($object->contents, $needle)) {
                return true;
            }

            if ($object->streamDictionaryContents !== null && str_contains($object->streamDictionaryContents, $needle)) {
                return true;
            }

            if ($object->streamContents !== null && str_contains($object->streamContents, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, IndirectObject> $objects
     */
    private function countStreamObjects(array $objects): int
    {
        return count(array_filter(
            $objects,
            static fn (object $object): bool => $object->streamDictionaryContents !== null && $object->streamContents !== null,
        ));
    }

    private function jpegImageSourceFromBytes(string $bytes): ImageSource
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-jpeg-fixture-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary JPEG fixture path.');
        }

        $typedPath = $path . '.jpg';

        if (!rename($path, $typedPath)) {
            @unlink($path);
            self::fail('Unable to prepare a typed JPEG fixture path.');
        }

        file_put_contents($typedPath, $bytes);

        try {
            return ImageSource::fromPath($typedPath);
        } finally {
            @unlink($typedPath);
        }
    }
}
