<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Page\Serialization\PageObjectRenderer;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageObjectRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_page_object_with_the_built_dictionary(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage(100.0, 200.0);

        $renderer = PageObjectRenderer::forPage($page);

        self::assertSame(
            "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 200] /Resources 6 0 R /Contents 5 0 R >>\nendobj\n",
            $renderer->render(false),
        );
    }
}
