<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\TaggedPdf;

use Kalle\Pdf\Internal\TaggedPdf\StructTreeRoot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructTreeRootTest extends TestCase
{
    #[Test]
    public function it_renders_an_empty_structure_tree_root(): void
    {
        $root = new StructTreeRoot(3);

        self::assertSame(
            "3 0 obj\n<< /Type /StructTreeRoot /K [] >>\nendobj\n",
            $root->render(),
        );
    }

    #[Test]
    public function it_adds_kids_and_renders_their_references(): void
    {
        $root = new StructTreeRoot(3);

        $result = $root->addKid(4)->addKid(10);

        self::assertSame($root, $result);
        self::assertSame(
            "3 0 obj\n<< /Type /StructTreeRoot /K [4 0 R 10 0 R] >>\nendobj\n",
            $root->render(),
        );
    }
}
