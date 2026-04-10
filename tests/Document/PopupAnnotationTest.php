<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Annotation\PopupAnnotation;
use Kalle\Pdf\Feature\Annotation\TextAnnotation;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PopupAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_popup_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $parent = new TextAnnotation(7, $page, 10, 20, 8, 8, 'Kommentar');
        $annotation = new PopupAnnotation(8, $page, $parent, 20, 30, 60, 40, true);

        self::assertSame(
            "8 0 obj\n"
            . "<< /Type /Annot /Subtype /Popup /Rect [20 30 80 70] /P 4 0 R /Parent 7 0 R /Open true >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_defaults_to_a_closed_popup_and_has_no_related_objects(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $parent = new TextAnnotation(7, $page, 10, 20, 8, 8, 'Kommentar');
        $annotation = new PopupAnnotation(8, $page, $parent, 20, 30, 60, 40);

        self::assertSame(
            "8 0 obj\n"
            . "<< /Type /Annot /Subtype /Popup /Rect [20 30 80 70] /P 4 0 R /Parent 7 0 R /Open false >>\n"
            . "endobj\n",
            $annotation->render(),
        );
        self::assertSame([], $annotation->getRelatedObjects());
    }
}
