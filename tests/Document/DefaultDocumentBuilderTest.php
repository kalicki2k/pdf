<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\ListOptions;
use Kalle\Pdf\Document\ListType;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Outline;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AnnotationMetadata;
use Kalle\Pdf\Page\FreeTextAnnotation;
use Kalle\Pdf\Page\FreeTextAnnotationOptions;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\HighlightAnnotationOptions;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkAnnotationOptions;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageOrientation;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\TextAnnotation;
use Kalle\Pdf\Page\TextAnnotationOptions;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class DefaultDocumentBuilderTest extends TestCase
{
    public function testItBuildsADocumentFromConfiguredMetadata(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->title('Example Title')
            ->author('Sebastian Kalicki')
            ->subject('Example Subject')
            ->language('de-DE')
            ->creator('Kalle PDF')
            ->creatorTool('pdf2 test suite')
            ->pageSize(PageSize::A5())
            ->text('Hello (PDF) \\ Test', new TextOptions(
                x: Units::mm(20),
                y: Units::mm(250),
                fontSize: 14,
                fontName: 'Times-Roman',
            ))
            ->build();

        self::assertSame(Version::V1_4, $document->version());
        self::assertSame('Example Title', $document->title);
        self::assertSame('Sebastian Kalicki', $document->author);
        self::assertSame('Example Subject', $document->subject);
        self::assertSame('de-DE', $document->language);
        self::assertSame('Kalle PDF', $document->creator);
        self::assertSame('pdf2 test suite', $document->creatorTool);
        self::assertCount(1, $document->pages);
        self::assertSame(PageSize::A5()->width(), $document->pages[0]->size->width());
        self::assertSame(PageSize::A5()->height(), $document->pages[0]->size->height());
        self::assertStringContainsString("BT\n/F1 14 Tf\n56.693 708.661 Td\n[", $document->pages[0]->contents);
        self::assertStringContainsString('] TJ' . "\nET", $document->pages[0]->contents);
        self::assertEquals(
            ['F1' => new PageFont('Times-Roman', StandardFontEncoding::WIN_ANSI)],
            $document->pages[0]->fontResources,
        );
    }

    public function testItBuildsMultiplePagesWhenNewPageIsUsed(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage(new PageOptions(
                pageSize: PageSize::A5(),
                orientation: PageOrientation::LANDSCAPE,
                margin: Margin::all(24.0),
                backgroundColor: Color::hex('#f5f5f5'),
                label: 'appendix',
                name: 'appendix-a',
            ))
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertStringContainsString('[<50>', $document->pages[0]->contents);
        self::assertStringContainsString('[<50>', $document->pages[1]->contents);
        self::assertStringContainsString('] TJ', $document->pages[0]->contents);
        self::assertStringContainsString('] TJ', $document->pages[1]->contents);
        self::assertSame(PageSize::A5()->landscape()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->landscape()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
        self::assertNotNull($document->pages[1]->backgroundColor);
        self::assertSame(ColorSpace::RGB, $document->pages[1]->backgroundColor->space);
        self::assertSame([245 / 255, 245 / 255, 245 / 255], $document->pages[1]->backgroundColor->components());
        self::assertSame('appendix', $document->pages[1]->label);
        self::assertSame('appendix-a', $document->pages[1]->name);
    }

    public function testNewPageWithoutOptionsKeepsDocumentPageDefaults(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(PageSize::A5()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
    }

    public function testNewPageOptionsOverrideOnlyExplicitFieldsOnTopOfDefaults(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pageSize(PageSize::A5())
            ->margin(Margin::all(24.0))
            ->text('Page 1')
            ->newPage(new PageOptions(
                orientation: PageOrientation::LANDSCAPE,
            ))
            ->text('Page 2')
            ->build();

        self::assertCount(2, $document->pages);
        self::assertSame(PageSize::A5()->landscape()->width(), $document->pages[1]->size->width());
        self::assertSame(PageSize::A5()->landscape()->height(), $document->pages[1]->size->height());
        self::assertSame(24.0, $document->pages[1]->margin?->top);
    }

    public function testItBuildsADocumentWithAnExplicitProfile(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::standard(Version::V1_7))
            ->build();

        self::assertSame(Version::V1_7, $document->version());
        self::assertSame(Version::V1_7, $document->profile->version());
    }

    public function testItBuildsADocumentWithACustomPdfAOutputIntent(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pdfaOutputIntent(new PdfAOutputIntent('/tmp/test.icc', 'Custom RGB', 'Custom profile', 4))
            ->build();

        self::assertNotNull($document->pdfaOutputIntent);
        self::assertSame('/tmp/test.icc', $document->pdfaOutputIntent->iccProfilePath);
        self::assertSame('Custom RGB', $document->pdfaOutputIntent->outputConditionIdentifier);
        self::assertSame('Custom profile', $document->pdfaOutputIntent->info);
        self::assertSame(4, $document->pdfaOutputIntent->colorComponents);
    }

    public function testItAddsEmbeddedFileAttachmentsToTheDocument(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->attachment(
                'source-data.xml',
                '<root/>',
                'Machine-readable source',
                'application/xml',
                AssociatedFileRelationship::DATA,
            )
            ->build();

        self::assertCount(1, $document->attachments);
        self::assertSame('source-data.xml', $document->attachments[0]->filename);
        self::assertSame('<root/>', $document->attachments[0]->embeddedFile->contents);
        self::assertSame('application/xml', $document->attachments[0]->embeddedFile->mimeType);
        self::assertSame('Machine-readable source', $document->attachments[0]->description);
        self::assertSame(AssociatedFileRelationship::DATA, $document->attachments[0]->associatedFileRelationship);
    }

    public function testItAddsEmbeddedFileAttachmentsFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-attachment-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary path for the attachment import test.');
        }

        file_put_contents($path, 'attachment-data');

        try {
            $document = DefaultDocumentBuilder::make()
                ->attachmentFromFile(
                    $path,
                    filename: 'custom.txt',
                    description: 'Imported attachment',
                    mimeType: 'text/plain',
                )
                ->build();

            self::assertCount(1, $document->attachments);
            self::assertSame('custom.txt', $document->attachments[0]->filename);
            self::assertSame('attachment-data', $document->attachments[0]->embeddedFile->contents);
            self::assertSame('Imported attachment', $document->attachments[0]->description);
            self::assertSame('text/plain', $document->attachments[0]->embeddedFile->mimeType);
        } finally {
            unlink($path);
        }
    }

    public function testItRejectsMissingAttachmentFiles(): void
    {
        $path = sys_get_temp_dir() . '/pdf2-missing-attachment-' . uniqid('', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Attachment file '$path' does not exist.");

        DefaultDocumentBuilder::make()->attachmentFromFile($path);
    }

    public function testItAddsATextFieldToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->build();

        self::assertNotNull($document->acroForm);
        self::assertCount(1, $document->acroForm->fields);
        self::assertInstanceOf(TextField::class, $document->acroForm->fields[0]);
        self::assertSame('customer_name', $document->acroForm->fields[0]->name);
        self::assertSame(1, $document->acroForm->fields[0]->pageNumber);
        self::assertSame('Ada', $document->acroForm->fields[0]->value);
    }

    public function testItAddsACheckboxToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->newPage()
            ->checkbox('accept_terms', 40, 500, 14, true, 'Accept terms')
            ->build();

        self::assertNotNull($document->acroForm);
        self::assertCount(1, $document->acroForm->fields);
        self::assertInstanceOf(CheckboxField::class, $document->acroForm->fields[0]);
        self::assertSame('accept_terms', $document->acroForm->fields[0]->name);
        self::assertSame(2, $document->acroForm->fields[0]->pageNumber);
        self::assertTrue($document->acroForm->fields[0]->checked);
    }

    public function testItGroupsRadioButtonsUnderASingleAcroFormField(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->radioButton('delivery', 'standard', 40, 500, 14, false, 'Standard delivery', 'Delivery method')
            ->radioButton('delivery', 'express', 80, 500, 14, true, 'Express delivery')
            ->build();

        self::assertNotNull($document->acroForm);
        self::assertCount(1, $document->acroForm->fields);
        self::assertInstanceOf(RadioButtonGroup::class, $document->acroForm->fields[0]);
        self::assertCount(2, $document->acroForm->fields[0]->choices);
        self::assertSame('Delivery method', $document->acroForm->fields[0]->alternativeName);
    }

    public function testItAddsAComboBoxToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->build();

        self::assertInstanceOf(ComboBoxField::class, $document->acroForm?->fields[0]);
        self::assertSame('done', $document->acroForm?->fields[0]->value);
    }

    public function testItAddsAListBoxToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->listBox('skills', 40, 500, 120, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
            ->build();

        self::assertInstanceOf(ListBoxField::class, $document->acroForm?->fields[0]);
        self::assertSame(['php', 'pdf'], $document->acroForm?->fields[0]->value);
    }

    public function testItAddsAPushButtonToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->pushButton('open_docs', 'Open docs', 40, 500, 120, 18, 'Open documentation', 'https://example.com/docs')
            ->build();

        self::assertInstanceOf(PushButtonField::class, $document->acroForm?->fields[0]);
        self::assertSame('Open docs', $document->acroForm?->fields[0]->label);
        self::assertSame('https://example.com/docs', $document->acroForm?->fields[0]->url);
    }

    public function testItAddsASignatureFieldToTheCurrentPageAcroForm(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->newPage()
            ->signatureField('approval_signature', 40, 500, 140, 28, 'Approval signature')
            ->build();

        self::assertInstanceOf(SignatureField::class, $document->acroForm?->fields[0]);
        self::assertSame('approval_signature', $document->acroForm?->fields[0]->name);
        self::assertSame(2, $document->acroForm?->fields[0]->pageNumber);
        self::assertSame('Approval signature', $document->acroForm?->fields[0]->alternativeName);
    }

    public function testItRegistersImageResourcesAndPlacementCommands(): void
    {
        $image = ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB);

        $document = DefaultDocumentBuilder::make()
            ->image($image, ImagePlacement::at(40, 500, width: 120), ImageAccessibility::alternativeText('Logo'))
            ->build();

        self::assertCount(1, $document->pages[0]->imageResources);
        self::assertCount(1, $document->pages[0]->images);
        self::assertSame($image, $document->pages[0]->imageResources['Im1']);
        self::assertSame('Im1', $document->pages[0]->images[0]->resourceAlias);
        self::assertSame('Logo', $document->pages[0]->images[0]->accessibility?->altText);
        self::assertStringContainsString("120 0 0 60 40 500 cm\n/Im1 Do", $document->pages[0]->contents);
    }

    public function testItWrapsTaggedPdfImagesInMarkedContent(): void
    {
        $image = ImageSource::jpeg('jpeg-bytes', 200, 100, ImageColorSpace::RGB);

        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->image($image, ImagePlacement::at(40, 500, width: 120), ImageAccessibility::alternativeText('Logo'))
            ->image($image, ImagePlacement::at(40, 420, width: 120), ImageAccessibility::decorative())
            ->build();

        self::assertSame(0, $document->pages[0]->images[0]->markedContentId);
        self::assertNull($document->pages[0]->images[1]->markedContentId);
        self::assertStringContainsString("/Figure << /MCID 0 >> BDC\nq\n120 0 0 60 40 500 cm\n/Im1 Do\nQ\nEMC", $document->pages[0]->contents);
        self::assertStringContainsString("/Artifact BMC\nq\n120 0 0 60 40 420 cm\n/Im1 Do\nQ\nEMC", $document->pages[0]->contents);
    }

    public function testItWrapsTaggedPdfTextBlocksInMarkedContent(): void
    {
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

        self::assertCount(2, $document->taggedTextBlocks);
        self::assertSame('H1', $document->taggedTextBlocks[0]->tag);
        self::assertSame('P', $document->taggedTextBlocks[1]->tag);
        self::assertStringContainsString("/H1 << /MCID 0 >> BDC\nBT", $document->pages[0]->contents);
        self::assertStringContainsString("/P << /MCID 1 >> BDC\nBT", $document->pages[0]->contents);
    }

    public function testItBuildsTaggedBulletLists(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                text: new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    width: 240,
                ),
            )
            ->build();

        self::assertCount(1, $document->taggedLists);
        self::assertCount(2, $document->taggedLists[0]->items);
        self::assertStringContainsString("/Lbl << /MCID 0 >> BDC\nBT", $document->pages[0]->contents);
        self::assertStringContainsString("/LBody << /MCID 1 >> BDC\nBT", $document->pages[0]->contents);
        self::assertStringContainsString("/Lbl << /MCID 2 >> BDC\nBT", $document->pages[0]->contents);
        self::assertStringContainsString("/LBody << /MCID 3 >> BDC\nBT", $document->pages[0]->contents);
    }

    public function testItWrapsTaggedTableDecorationGraphicsAsArtifacts(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->table(
                \Kalle\Pdf\Document\Table::define(
                    \Kalle\Pdf\Document\TableColumn::fixed(120.0),
                )
                    ->withPlacement(\Kalle\Pdf\Document\TablePlacement::at(72.0, 700.0, 120.0))
                    ->withRows(
                        \Kalle\Pdf\Document\TableRow::fromCells(
                            \Kalle\Pdf\Document\TableCell::text('Cell')->withBackgroundColor(Color::rgb(0.9, 0.9, 0.9)),
                        ),
                    )
                    ->withTextOptions(new TextOptions(
                        fontSize: 12,
                        lineHeight: 15,
                        embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    )),
            )
            ->build();

        self::assertStringContainsString("/Artifact BMC\nq\n0.9 0.9 0.9 rg", $document->pages[0]->contents);
        self::assertStringContainsString("/Artifact BMC\nq\n0.5 w", $document->pages[0]->contents);
        self::assertStringContainsString('/TD << /MCID ', $document->pages[0]->contents);
    }

    public function testItBuildsTaggedNumberedLists(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->list(
                ['Erster Punkt Привет', 'Zweiter Punkt Привет'],
                new ListOptions(type: ListType::NUMBERED, start: 7),
                new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                    width: 240,
                ),
            )
            ->build();

        self::assertCount(1, $document->taggedLists);
        self::assertCount(2, $document->taggedLists[0]->items);
        self::assertStringContainsString('/Lbl << /MCID 0 >> BDC', $document->pages[0]->contents);
        self::assertStringContainsString('/LBody << /MCID 1 >> BDC', $document->pages[0]->contents);
        self::assertStringContainsString('<00010002> Tj', $document->pages[0]->contents);
        self::assertStringContainsString('<00130002> Tj', $document->pages[0]->contents);
    }

    public function testItAddsLinkAnnotationsToTheCurrentPage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Example')
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[0]);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(LinkAnnotation::class, $annotation);
        self::assertTrue($annotation->target->isExternalUrl());
        self::assertSame('https://example.com', $annotation->target->externalUrlValue());
        self::assertSame('Open Example', $annotation->contents);
    }

    public function testItAddsTextAnnotationsToTheCurrentPage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Example')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertInstanceOf(TextAnnotation::class, $document->pages[0]->annotations[0]);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(TextAnnotation::class, $annotation);
        self::assertSame('Kommentar', $annotation->contents);
        self::assertSame('QA', $annotation->title);
        self::assertSame('Comment', $annotation->icon);
        self::assertTrue($annotation->open);
    }

    public function testItAddsHighlightAnnotationsToTheCurrentPage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Example')
            ->highlightAnnotation(40, 500, 80, 10, Color::rgb(1, 1, 0), 'Markiert', 'QA')
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertInstanceOf(HighlightAnnotation::class, $document->pages[0]->annotations[0]);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(HighlightAnnotation::class, $annotation);
        self::assertSame('Markiert', $annotation->contents);
        self::assertSame('QA', $annotation->title);
        self::assertSame([1.0, 1.0, 0.0], $annotation->color?->components());
    }

    public function testItAddsTextAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textAnnotationWithOptions(
                40,
                500,
                18,
                18,
                'Kommentar',
                new TextAnnotationOptions(title: 'QA', icon: 'Help', open: true),
            )
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(TextAnnotation::class, $annotation);
        self::assertSame('QA', $annotation->title);
        self::assertSame('Help', $annotation->icon);
        self::assertTrue($annotation->open);
    }

    public function testItAddsHighlightAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->highlightAnnotationWithOptions(
                40,
                500,
                80,
                10,
                new HighlightAnnotationOptions(
                    color: Color::rgb(0.9, 0.8, 0.2),
                    contents: 'Markiert',
                    title: 'QA',
                ),
            )
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(HighlightAnnotation::class, $annotation);
        self::assertSame('Markiert', $annotation->contents);
        self::assertSame('QA', $annotation->title);
        self::assertSame([0.9, 0.8, 0.2], $annotation->color?->components());
    }

    public function testItAddsFreeTextAnnotationsToTheCurrentPage(): void
    {
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

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertInstanceOf(FreeTextAnnotation::class, $document->pages[0]->annotations[0]);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame('Kommentar', $annotation->contents);
        self::assertSame('QA', $annotation->title);
        self::assertSame(12.0, $annotation->fontSize);
        self::assertStringContainsString('/' . $annotation->fontAlias . ' 12 Tf', $annotation->appearanceContents);
    }

    public function testItAddsFreeTextAnnotationsWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->freeTextAnnotationWithOptions(
                'Kommentar',
                40,
                500,
                120,
                32,
                new TextOptions(fontSize: 12),
                new FreeTextAnnotationOptions(
                    textColor: Color::rgb(0, 0, 0.4),
                    borderColor: Color::rgb(0.2, 0.2, 0.2),
                    fillColor: Color::rgb(1, 1, 0.8),
                    metadata: new AnnotationMetadata(title: 'QA'),
                ),
            )
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        $annotation = $document->pages[0]->annotations[0];
        self::assertInstanceOf(FreeTextAnnotation::class, $annotation);
        self::assertSame('QA', $annotation->title);
        self::assertSame([0.0, 0.0, 0.4], $annotation->textColor?->components());
        self::assertSame([0.2, 0.2, 0.2], $annotation->borderColor?->components());
        self::assertSame([1.0, 1.0, 0.8], $annotation->fillColor?->components());
    }

    public function testItAddsInternalLinkAnnotationsToTheCurrentPage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Page 1')
            ->newPage()
            ->text('Page 2')
            ->newPage()
            ->linkToPage(2, 40, 500, 120, 16, 'Go to page 2')
            ->linkToPagePosition(1, 72, 700, 40, 460, 120, 16, 'Back to heading')
            ->build();

        self::assertCount(2, $document->pages[2]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[2]->annotations[0]);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[2]->annotations[1]);
        self::assertTrue($document->pages[2]->annotations[0]->target->isPage());
        self::assertSame(2, $document->pages[2]->annotations[0]->target->pageNumberValue());
        self::assertTrue($document->pages[2]->annotations[1]->target->isPosition());
        self::assertSame(1, $document->pages[2]->annotations[1]->target->pageNumberValue());
        self::assertSame(72.0, $document->pages[2]->annotations[1]->target->xValue());
        self::assertSame(700.0, $document->pages[2]->annotations[1]->target->yValue());
    }

    public function testItAddsNamedDestinationsAndTextLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->namedDestination('intro')
            ->text('Open intro', new TextOptions(
                link: LinkTarget::namedDestination('intro'),
            ))
            ->build();

        self::assertCount(1, $document->pages[0]->namedDestinations);
        self::assertSame('intro', $document->pages[0]->namedDestinations[0]->name);
        self::assertNotEmpty($document->pages[0]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[0]);
        self::assertTrue($document->pages[0]->annotations[0]->target->isNamedDestination());
        self::assertSame('intro', $document->pages[0]->annotations[0]->target->namedDestinationValue());
    }

    public function testItAddsDocumentOutlines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->outline('Intro')
            ->text('Page 1')
            ->newPage()
            ->outlineAt('Details', 2, 72, 640)
            ->build();

        self::assertCount(2, $document->outlines);
        self::assertInstanceOf(Outline::class, $document->outlines[0]);
        self::assertSame('Intro', $document->outlines[0]->title);
        self::assertSame(1, $document->outlines[0]->pageNumber);
        self::assertFalse($document->outlines[0]->hasPosition());
        self::assertSame('Details', $document->outlines[1]->title);
        self::assertSame(2, $document->outlines[1]->pageNumber);
        self::assertSame(72.0, $document->outlines[1]->x);
        self::assertSame(640.0, $document->outlines[1]->y);
    }

    public function testItRejectsOutlineCoordinatesWhenOnlyOneCoordinateIsProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Outline coordinates must be provided together.');

        DefaultDocumentBuilder::make()->outlineAt('Broken', 1, 10);
    }

    public function testItAddsMultipleLinkedTextSegmentsInOneCall(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                new TextSegment('Docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' und '),
                new TextSegment('API', LinkTarget::externalUrl('https://example.com/api')),
            ])
            ->build();

        self::assertCount(2, $document->pages[0]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[0]);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[1]);
        self::assertSame('https://example.com/docs', $document->pages[0]->annotations[0]->target->externalUrlValue());
        self::assertSame('Docs', $document->pages[0]->annotations[0]->contents);
        self::assertSame('https://example.com/api', $document->pages[0]->annotations[1]->target->externalUrlValue());
        self::assertSame('API', $document->pages[0]->annotations[1]->contents);
    }

    public function testItMergesAdjacentTextSegmentsWithTheSameLink(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                new TextSegment('Read', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' docs', LinkTarget::externalUrl('https://example.com/docs')),
                new TextSegment(' now'),
            ])
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[0]);
        self::assertSame('https://example.com/docs', $document->pages[0]->annotations[0]->target->externalUrlValue());
        self::assertSame('Read docs', $document->pages[0]->annotations[0]->contents);
    }

    public function testItKeepsTheSameTaggedGroupForWrappedTextLinks(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfUa1())
            ->title('Accessible Copy')
            ->language('de-DE')
            ->textSegments([
                new TextSegment('Read docs', LinkTarget::externalUrl('https://example.com/docs')),
            ], new TextOptions(width: 45))
            ->build();

        self::assertCount(2, $document->pages[0]->annotations);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[0]);
        self::assertInstanceOf(LinkAnnotation::class, $document->pages[0]->annotations[1]);
        self::assertSame(
            $document->pages[0]->annotations[0]->taggedGroupKey,
            $document->pages[0]->annotations[1]->taggedGroupKey,
        );
        self::assertNotNull($document->pages[0]->annotations[0]->taggedGroupKey);
    }

    public function testItSeparatesAnnotationContentsFromAccessibleLinkLabel(): void
    {
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

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertSame('Open docs section', $document->pages[0]->annotations[0]->contents);
        self::assertSame('Read the documentation section', $document->pages[0]->annotations[0]->accessibleLabel);
    }

    public function testExplicitTextLinkGroupKeysCanPreventMergingForTheSameTarget(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->textSegments([
                TextSegment::link('Docs', TextLink::externalUrl('https://example.com/docs', groupKey: 'docs-a')),
                TextSegment::link(' API', TextLink::externalUrl('https://example.com/docs', groupKey: 'docs-b')),
            ])
            ->build();

        self::assertCount(2, $document->pages[0]->annotations);
    }

    public function testItAddsRectLinksWithExplicitOptions(): void
    {
        $document = DefaultDocumentBuilder::make()
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
            ->build();

        self::assertCount(1, $document->pages[0]->annotations);
        self::assertSame('Open docs section', $document->pages[0]->annotations[0]->contents);
        self::assertSame('Read the documentation section', $document->pages[0]->annotations[0]->accessibleLabel);
        self::assertSame('docs-link', $document->pages[0]->annotations[0]->taggedGroupKey);
    }
}
