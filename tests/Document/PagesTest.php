<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Pages;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PagesTest extends TestCase
{
    #[Test]
    public function it_renders_an_empty_page_tree(): void
    {
        $document = new Document();
        $pages = new Pages(2, $document);

        self::assertSame(
            "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n",
            $pages->render(),
        );
    }

    #[Test]
    public function it_adds_a_page_and_returns_it(): void
    {
        $document = new Document(version: 1.4);
        $pages = new Pages(2, $document);

        $page = $pages->addPage(6, 7, 8, 0, 100.0, 200.0);

        self::assertSame($page, $pages->pages[0]);
        self::assertSame(6, $page->id);
        self::assertSame(
            "6 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 8 0 R /Contents 7 0 R /StructParents 0 >>\nendobj\n",
            $page->render(),
        );
    }

    #[Test]
    public function it_renders_all_page_references_and_the_page_count(): void
    {
        $document = new Document(version: 1.4);
        $pages = new Pages(2, $document);
        $pages->addPage(6, 7, 8, 0, 100.0, 200.0);
        $pages->addPage(9, 10, 11, 1, 210.0, 297.0);

        self::assertSame(
            "2 0 obj\n<< /Type /Pages /Kids [6 0 R 9 0 R] /Count 2 >>\nendobj\n",
            $pages->render(),
        );
    }
}
