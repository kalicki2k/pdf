<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Geometry\Rect;
use Kalle\Pdf\Internal\Page\Serialization\PageDictionaryBuilder;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageDictionaryBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_basic_page_dictionary(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $dictionary = (new PageDictionaryBuilder())->build($page, false);

        self::assertSame(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 6 0 R /Contents 5 0 R >>',
            $dictionary->render(),
        );
    }

    #[Test]
    public function it_adds_annotations_tabs_and_struct_parents_when_needed(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $page->addTextAnnotation(new Rect(10, 20, 16, 18), 'Kommentar', 'QA');

        $dictionary = (new PageDictionaryBuilder())->build($page, true);
        $rendered = $dictionary->render();

        self::assertStringContainsString('/StructParents 0', $rendered);
        self::assertMatchesRegularExpression('/\/Annots \[\d+ 0 R\]/', $rendered);
        self::assertStringContainsString('/Tabs /S', $rendered);
    }
}
