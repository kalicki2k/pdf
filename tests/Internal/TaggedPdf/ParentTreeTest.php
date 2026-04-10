<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\TaggedPdf;

use Kalle\Pdf\Internal\TaggedPdf\ParentTree;
use Kalle\Pdf\Internal\TaggedPdf\StructElem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParentTreeTest extends TestCase
{
    #[Test]
    public function it_renders_marked_content_and_object_entries(): void
    {
        $parentTree = new ParentTree(7);
        $parentTree
            ->add(0, new StructElem(11, 'P'))
            ->add(0, new StructElem(12, 'Span'))
            ->addObject(1, new StructElem(13, 'Link'));

        self::assertSame(
            "7 0 obj\n<< /Nums [0 [11 0 R 12 0 R] 1 13 0 R] >>\nendobj\n",
            $parentTree->render(),
        );
    }
}
