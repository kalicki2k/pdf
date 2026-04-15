<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TaggedStructureRegistrar;
use PHPUnit\Framework\TestCase;

final class TaggedStructureRegistrarTest extends TestCase
{
    public function testItRegistersInlineContainerChildrenWithoutDuplicates(): void
    {
        $taggedTextBlocks = [];
        $taggedFigures = [];
        $taggedLists = [];
        $taggedStructureElements = [];
        $taggedDocumentChildKeys = [];
        $taggedStructureStack = [];
        $nextTaggedStructureElementId = 0;
        $registrar = new TaggedStructureRegistrar(
            $taggedTextBlocks,
            $taggedFigures,
            $taggedLists,
            $taggedStructureElements,
            $taggedDocumentChildKeys,
            $taggedStructureStack,
            $nextTaggedStructureElementId,
            true,
            static fn (int $markedContentId): array => [
                'pageIndex' => 0,
                'markedContentId' => $markedContentId,
            ],
        );

        $containerKey = $registrar->registerInlineContainer('P');
        $registrar->attachStructureChildKeyTo($containerKey, '0:link');
        $registrar->attachStructureChildKeyTo($containerKey, '0:link');
        $textKey = $registrar->registerTextBlock('Span', 4, parentKey: $containerKey);

        self::assertSame(['struct:0'], $taggedDocumentChildKeys);
        self::assertSame(['0:link', $textKey], $taggedStructureElements[$containerKey]['childKeys']);
        self::assertSame('Span', $taggedTextBlocks[0]['tag']);
        self::assertSame($containerKey, $taggedTextBlocks[0]['parentKey']);
    }
}
