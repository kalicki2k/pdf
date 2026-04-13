<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;
use function iterator_to_array;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class PdfA4PolicyMatrixTest extends TestCase
{
    public function testItAllowsPdfA4TextAnnotationsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/Subtype /Text', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
    }

    public function testItAllowsPdfA4fFreeTextAnnotationsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Copy')
            ->freeTextAnnotation(
                'Kommentar',
                40,
                500,
                120,
                28,
                TextOptions::make(
                    embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
            )
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/Subtype /FreeText', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
    }

    public function testItAllowsPdfA4eTextAnnotationsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/Subtype /Text', $serialized);
        self::assertStringContainsString('/AP << /N ', $serialized);
    }

    public function testItAllowsPdfA4ChoiceFieldsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 40, 450, 120, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php'], 'Skills')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AcroForm ', $serialized);
        self::assertStringContainsString('/FT /Ch', $serialized);
        self::assertStringNotContainsString('/Helv', $serialized);
    }

    public function testItAllowsPdfA4fTextAndCheckboxFieldsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Copy')
            ->textField('customer_name', 40, 500, 140, 18, 'Ada', 'Customer name')
            ->checkbox('accept_terms', 40, 460, 14, true, 'Accept terms')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AcroForm ', $serialized);
        self::assertStringContainsString('/FT /Tx', $serialized);
        self::assertStringContainsString('/FT /Btn', $serialized);
        self::assertStringNotContainsString('/Helv', $serialized);
    }

    public function testItAllowsPdfA4eChoiceFieldsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 40, 450, 120, 48, ['cad' => 'CAD', 'pdf' => 'PDF'], ['cad'], 'Skills')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AcroForm ', $serialized);
        self::assertStringContainsString('/FT /Ch', $serialized);
        self::assertStringNotContainsString('/Helv', $serialized);
    }

    public function testItAllowsPdfA4eOptionalContentGroupsWithinTheCurrentConstrainedScope(): void
    {
        $document = new Document(
            profile: Profile::pdfA4e(),
            title: 'Engineering Layers',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "/OC /Layer1 BDC\nq\n0 0 20 20 re\nf\nQ\nEMC",
                    optionalContentGroups: [
                        'Layer1' => new OptionalContentGroup('Engineering View'),
                    ],
                ),
            ],
        );

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/OCProperties << /OCGs [', $serialized);
        self::assertStringContainsString('/Type /OCG /Name (Engineering View)', $serialized);
        self::assertStringContainsString('/Properties << /Layer1 ', $serialized);
    }

    public function testItRejectsPdfA4PopupRelatedObjects(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->popupAnnotation(70, 520, 120, 60, true)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4 does not allow popup related objects in the current PDF/A-4 scope for page annotation 1 on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA4fPageLevelFileAttachmentAnnotations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4f does not allow page-level file attachment annotations in the current PDF/A-4 scope. Use document-level associated files instead.',
        );

        DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Copy')
            ->fileAttachmentAnnotation(
                'demo.txt',
                new EmbeddedFile('hello', 'text/plain'),
                40,
                500,
                12,
                14,
                null,
                'Graph',
                'Anhang',
            );
    }

    public function testItRejectsPdfA4ePopupRelatedObjects(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->popupAnnotation(70, 520, 120, 60, true)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4e does not allow popup related objects in the current constrained PDF/A-4e scope for page annotation 1 on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA4ePageLevelFileAttachmentAnnotations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4e does not allow page-level file attachment annotations in the current constrained PDF/A-4e scope. Use document-level associated files instead.',
        );

        DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->fileAttachmentAnnotation(
                'demo.txt',
                new EmbeddedFile('hello', 'text/plain'),
                40,
                500,
                12,
                14,
                null,
                'Graph',
                'Anhang',
            );
    }

    public function testItRejectsPdfA4SquareAnnotationsOutsideTheCurrentSubset(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->squareAnnotation(40, 280, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Quadrat', 'QA')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4 only allows Link, Text, Highlight and FreeText annotations in the current PDF/A-4 scope on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA4PushButtonsOutsideTheCurrentFormSubset(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4())
            ->title('Archive Copy')
            ->pushButton('ack', 'Acknowledge', 40, 500, 120, 18, 'Acknowledge')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4 only allows text fields, checkboxes, radio buttons and choice fields in the current PDF/A-4 form policy.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA4fSignatureFieldsOutsideTheCurrentFormSubset(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4f())
            ->title('Archive Copy')
            ->signatureField('approval_signature', 40, 420, 160, 24, 'Approval signature')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4f only allows text fields, checkboxes, radio buttons and choice fields in the current PDF/A-4 form policy.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA4ePushButtonsOutsideTheCurrentFormSubset(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA4e())
            ->title('Engineering Archive Copy')
            ->pushButton('ack', 'Acknowledge', 40, 500, 120, 18, 'Acknowledge')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4e only allows text fields, checkboxes, radio buttons and choice fields in the current constrained PDF/A-4e form policy.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }
}
