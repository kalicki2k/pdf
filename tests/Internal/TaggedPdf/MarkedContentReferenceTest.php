<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\TaggedPdf;

use Kalle\Pdf\Internal\TaggedPdf\MarkedContentReference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkedContentReferenceTest extends TestCase
{
    #[Test]
    public function it_renders_a_marked_content_reference_object(): void
    {
        $reference = new MarkedContentReference(11);

        self::assertSame(
            "11 0 obj\n<< /Type /MCR\n/MCID 0 >>\nendobj\n",
            $reference->render(),
        );
    }
}
