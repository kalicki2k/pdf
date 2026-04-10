<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Annotation\CaretAnnotation;
use Kalle\Pdf\Page\Annotation\TextAnnotationAppearanceStream;
use Kalle\Pdf\Profile\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CaretAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_caret_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CaretAnnotation(7, $page, 10, 20, 16, 18, 'Einfuegen', 'QA', 'P');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Caret /Rect [10 20 26 38] /P 4 0 R /Sy /P /Contents (Einfuegen) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_rejects_an_invalid_caret_symbol(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Caret annotation symbol must be "None" or "P".');

        new CaretAnnotation(7, $page, 10, 20, 16, 18, symbol: 'Insert');
    }

    #[Test]
    public function it_uses_the_default_symbol_and_omits_empty_optional_fields(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new CaretAnnotation(7, $page, 10, 20, 16, 18, '', '');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Caret /Rect [10 20 26 38] /P 4 0 R /Sy /None >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_pdf_a_caret_annotation_with_print_flag_and_appearance(): void
    {
        $document = new Document(profile: Profile::pdfA2u());
        $page = $document->addPage();
        $annotation = new CaretAnnotation(7, $page, 10, 20, 16, 18, 'Einfuegen', 'QA', 'P');
        $annotation->withAppearance(new TextAnnotationAppearanceStream(8, 16, 18));

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Caret /Rect [10 20 26 38] /P 4 0 R /Sy /P /F 4 /Contents (Einfuegen) /T (QA) /AP << /N 8 0 R >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertCount(1, $annotation->getRelatedObjects());
    }
}
