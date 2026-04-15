<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedMarkedContentStructElemObjectBuilder;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class TaggedMarkedContentStructElemObjectBuilderTest extends TestCase
{
    public function testItBuildsMultiReferenceTextWithMarkedContentKids(): void
    {
        $state = $this->buildTaggedState(
            new Document(
                profile: Profile::pdfA1a(),
                pages: [new Page(PageSize::A4()), new Page(PageSize::A4())],
                taggedTextBlocks: [
                    new TaggedTextBlock('P', 0, 7, 'text:intro'),
                    new TaggedTextBlock('P', 1, 3, 'text:intro'),
                ],
            ),
        );

        $textEntry = $state->taggedStructure->textEntries[0];
        self::assertGreaterThan(1, count($textEntry['references']));

        $object = (new TaggedMarkedContentStructElemObjectBuilder())->buildTextObject(
            $textEntry,
            $state,
            $state->documentStructElemObjectId,
            static fn (array $references, array $pageObjectIds): array => array_map(
                static fn (object $reference): string => '<< /Type /MCR /Pg '
                    . $pageObjectIds[$reference->pageIndex]
                    . ' 0 R /MCID '
                    . $reference->markedContentId
                    . ' >>',
                $references,
            ),
        );

        self::assertStringContainsString('/Type /StructElem', $object->contents);
        self::assertStringContainsString('/K [', $object->contents);
        self::assertStringContainsString('/Type /MCR', $object->contents);
    }

    private function buildTaggedState(Document $document): DocumentSerializationPlanBuildState
    {
        $taggedPdfObjectBuilder = new DocumentTaggedPdfObjectBuilder();

        return new DocumentSerializationPlanObjectIdAllocator()->allocate(
            $document,
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedLinkStructure($document, $nextStructParentId),
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedPageAnnotationStructure($document, $nextStructParentId),
            fn (array $fieldObjectIds, array $relatedObjectIds, int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedFormStructure(
                $document,
                $fieldObjectIds,
                $relatedObjectIds,
                $nextStructParentId,
            ),
            static fn (): array => [],
        );
    }
}
