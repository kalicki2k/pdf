<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Annotation\StampAnnotation;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Graphics\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StampAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_stamp_annotation(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new StampAnnotation(7, $page, 10, 20, 80, 24, 'Approved', Color::rgb(0, 128, 0), 'Freigegeben', 'QA');

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Stamp /Rect [10 20 90 44] /P 4 0 R /Name /Approved /C [0 0.501961 0] /Contents (Freigegeben) /T (QA) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_uses_the_default_icon_and_omits_optional_fields(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new StampAnnotation(7, $page, 10, 20, 80, 24);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Stamp /Rect [10 20 90 44] /P 4 0 R /Name /Draft >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_cmyk_stamp_color(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();
        $annotation = new StampAnnotation(7, $page, 10, 20, 80, 24, 'Approved', Color::cmyk(0.1, 0.2, 0.3, 0.4));

        self::assertStringContainsString('/C [0.1 0.2 0.3 0.4]', $annotation->render());
    }
}
