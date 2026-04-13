<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;
use function iterator_to_array;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class PdfA23PolicyMatrixTest extends TestCase
{
    public function testItAllowsPdfA2aTaggedParagraphsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2a())
            ->text('Getaggter Absatz fuer PDF/A-2a. Привет.', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/MarkInfo << /Marked true >>', $serialized);
        self::assertStringContainsString('/Type /StructTreeRoot', $serialized);
        self::assertStringContainsString('/S /Document', $serialized);
        self::assertStringContainsString('/S /P', $serialized);
    }

    public function testItAllowsPdfA2bUriLinkAnnotationsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2b())
            ->link('https://example.com/spec', 40, 500, 120, 16, 'Specification Link')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/spec) >>', $serialized);
    }

    public function testItAllowsPdfA2uUriLinkAnnotationsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2u())
            ->link('https://example.com/spec', 40, 500, 120, 16, 'Specification Link')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/spec) >>', $serialized);
    }

    public function testItRejectsPdfA2uPopupRelatedObjects(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2u())
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->popupAnnotation(70, 520, 120, 60, true)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u does not allow popup related objects in the current PDF/A-2/3 scope for page annotation 1 on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA2uAnnotationsOutsideTheExplicitScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2u())
            ->squareAnnotation(40, 280, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Quadrat', 'QA')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u only allows Link, Text, Highlight and FreeText annotations in the current PDF/A-2/3 scope on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItAllowsPdfA2uAcroFormsInTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2u())
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AcroForm ', $serialized);
        self::assertStringContainsString('/FT /Tx', $serialized);
        self::assertStringNotContainsString('/Helv', $serialized);
    }

    public function testItRejectsPdfA3bPageLevelFileAttachmentAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
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
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-3b does not allow page-level file attachment annotations in the current PDF/A-2/3 scope. Use document-level associated files instead.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItAllowsPdfA3uDocumentAssociatedFilesWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3u())
            ->title('Archive Package')
            ->language('de-DE')
            ->text('PDF/A-3u Package Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->attachment('data.xml', '<root/>', 'Source data', 'application/xml')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AFRelationship /Data', $serialized);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $serialized);
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $serialized);
        self::assertStringContainsString('/Encoding /Identity-H', $serialized);
    }

    public function testItAllowsPdfA3bChoiceFieldsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
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

    public function testItRejectsPdfA2uPushButtonsOutsideTheCurrentFormSubset(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2u())
            ->pushButton('ack', 'Acknowledge', 40, 500, 120, 18, 'Acknowledge')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u only allows text fields, checkboxes, radio buttons and choice fields in the current PDF/A-2/3 form policy.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItAllowsPdfA3aTaggedDocumentsWithDocumentAssociatedFiles(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3a())
            ->title('Archive Package')
            ->language('de-DE')
            ->text('PDF/A-3a Package Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->attachment('data.xml', '<root/>', 'Source data', 'application/xml')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/AFRelationship /Data', $serialized);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $serialized);
        self::assertStringContainsString('<pdfaid:conformance>A</pdfaid:conformance>', $serialized);
        self::assertStringContainsString('/Type /StructTreeRoot', $serialized);
    }

    public function testItAllowsPdfA2aTaggedTextAnnotationsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2a())
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/Subtype /Text', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Annot', $serialized);
        self::assertStringContainsString('/Alt (Kommentar)', $serialized);
    }

    public function testItAllowsPdfA3aTaggedTextAnnotationsWithinTheCurrentScope(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3a())
            ->title('Archive Package')
            ->language('de-DE')
            ->text('PDF/A-3a Package Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertStringContainsString('/Subtype /Text', $serialized);
        self::assertStringContainsString('/Type /StructElem /S /Annot', $serialized);
        self::assertStringContainsString('/Alt (Kommentar)', $serialized);
    }

    public function testItAllowsPdfA2aTaggedChoiceFieldsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2a())
            ->comboBox('status', 40, 500, 120, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
            ->listBox('skills', 40, 450, 120, 48, ['php' => 'PHP', 'pdf' => 'PDF'], ['php', 'pdf'], 'Skills')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array(new DocumentSerializationPlanBuilder()->build($document)->objects),
        ));

        self::assertSame(2, substr_count($serialized, '/Type /StructElem /S /Form'));
        self::assertStringContainsString('/Alt (Status)', $serialized);
        self::assertStringContainsString('/Alt (Skills)', $serialized);
        self::assertStringNotContainsString('/Helv', $serialized);
    }

    public function testItRejectsPdfA2aPushButtonsOutsideTheTaggedFormSubset(): void
    {
        $document = $this->pdfA2BaselineBuilder(Profile::pdfA2a())
            ->pushButton('ack', 'Acknowledge', 40, 500, 120, 18, 'Acknowledge')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2a only allows text fields, checkboxes, radio buttons and choice fields in the current PDF/A-2/3 form policy.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    public function testItRejectsPdfA3aSquareAnnotationsOutsideTheTaggedAnnotationSubset(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3a())
            ->title('Archive Package')
            ->language('de-DE')
            ->text('PDF/A-3a Package Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ))
            ->squareAnnotation(40, 280, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Quadrat', 'QA')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-3a only allows Link, Text, Highlight and FreeText annotations in the current PDF/A-2/3 scope on page 1.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
    }

    private function pdfA2BaselineBuilder(Profile $profile): DefaultDocumentBuilder
    {
        return DefaultDocumentBuilder::make()
            ->profile($profile)
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('PDF/A Regression Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ));
    }
}
