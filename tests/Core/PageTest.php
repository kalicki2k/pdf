<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Core;

use InvalidArgumentException;
use Kalle\Pdf\Core\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    #[Test]
    public function it_renders_the_page_dictionary(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage(100.0, 200.0);

        self::assertSame(
            "7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 9 0 R /Contents 8 0 R /StructParents 0 >>\nendobj\n",
            $page->render(),
        );
    }

    #[Test]
    public function it_returns_itself_when_adding_an_image_placeholder(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        self::assertSame($page, $page->addImage());
    }

    #[Test]
    public function it_rejects_text_with_an_unregistered_font(): void
    {
        $document = new Document(version: 1.4);
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' is not registered.");

        $page->addText('Hello', 10, 20, 'Helvetica', 12, 'P');
    }

    #[Test]
    public function it_adds_text_to_contents_and_registers_the_font_resource(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $result = $page->addText('Hello', 10, 20, 'Helvetica', 12, 'P');

        self::assertSame($page, $result);
        self::assertStringContainsString('/Font << /F1 7 0 R >>', $page->resources->render());
        self::assertStringContainsString('/P << /MCID 0 >> BDC', $page->contents->render());
        self::assertStringContainsString('(Hello) Tj', $page->contents->render());
        self::assertStringContainsString('5 0 obj' . "\n" . '<< /Type /StructElem /S /Document /K [11 0 R] >>', $document->render());
        self::assertStringContainsString('11 0 obj' . "\n" . '<< /Type /StructElem /S /P /P 5 0 R /Pg 8 0 R /K 0 >>', $document->render());
    }

    #[Test]
    public function it_rejects_text_that_is_not_supported_by_the_registered_font(): void
    {
        $document = new Document(version: 1.4);
        $document->addFont('Helvetica');
        $page = $document->addPage();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Font 'Helvetica' does not support the provided text.");

        $page->addText('漢', 10, 20, 'Helvetica', 12, 'P');
    }
}
