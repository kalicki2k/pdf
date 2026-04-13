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
                new TextOptions(
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
        $document = DefaultDocumentBuilder::make()
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
            )
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-4f does not allow page-level file attachment annotations in the current PDF/A-4 scope. Use document-level associated files instead.',
        );

        new DocumentSerializationPlanBuilder()->build($document);
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
}
