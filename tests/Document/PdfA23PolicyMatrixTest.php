<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;

use InvalidArgumentException;

use function iterator_to_array;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class PdfA23PolicyMatrixTest extends TestCase
{
    public function testItAllowsPdfA2uUriLinkAnnotationsWithinTheCurrentScope(): void
    {
        $document = $this->pdfA2uBaselineBuilder()
            ->link('https://example.com/spec', 40, 500, 120, 16, 'Specification Link')
            ->build();

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array((new DocumentSerializationPlanBuilder())->build($document)->objects),
        ));

        self::assertStringContainsString('/A << /S /URI /URI (https://example.com/spec) >>', $serialized);
    }

    public function testItRejectsPdfA2uPopupRelatedObjects(): void
    {
        $document = $this->pdfA2uBaselineBuilder()
            ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
            ->popupAnnotation(70, 520, 120, 60, true)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u does not allow popup related objects in the current PDF/A-2/3 scope for page annotation 1 on page 1.',
        );

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA2uAnnotationsOutsideTheExplicitScope(): void
    {
        $document = $this->pdfA2uBaselineBuilder()
            ->squareAnnotation(40, 280, 80, 24, Color::rgb(1, 0, 0), Color::gray(0.9), 'Quadrat', 'QA')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u only allows Link, Text, Highlight and FreeText annotations in the current PDF/A-2/3 scope on page 1.',
        );

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA2uAcroFormsInTheCurrentScope(): void
    {
        $document = $this->pdfA2uBaselineBuilder()
            ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-2u does not allow AcroForm fields in the current PDF/A-2/3 scope.',
        );

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    public function testItRejectsPdfA3bPageLevelFileAttachmentAnnotations(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA3b())
            ->title('Archive Package')
            ->fileAttachmentAnnotation('demo.txt', 'hello', 40, 500, 12, 14, 'Graph', 'Anhang')
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-3b does not allow page-level file attachment annotations in the current PDF/A-2/3 scope. Use document-level associated files instead.',
        );

        (new DocumentSerializationPlanBuilder())->build($document);
    }

    private function pdfA2uBaselineBuilder(): DefaultDocumentBuilder
    {
        return DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2u())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('PDF/A-2u Regression Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
            ));
    }
}
