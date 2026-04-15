<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureTag;
use Kalle\Pdf\Document\TaggedStructureLayoutPolicy;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextSegment;
use PHPUnit\Framework\TestCase;

final class TaggedStructureLayoutPolicyTest extends TestCase
{
    public function testItKeepsInlineLinkChildrenOutOfDocumentOrder(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA2a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->text([
                new TextSegment('Read '),
                new TextSegment('more', LinkTarget::externalUrl('https://example.com')),
                new TextSegment(' now'),
            ], TextOptions::make(tag: TaggedStructureTag::P))
            ->build();
        $state = $this->buildState($document);
        $policy = new TaggedStructureLayoutPolicy();

        $linkKey = $state->taggedLinkStructure['linkEntries'][0]['key'];
        $parentKey = $policy->explicitParentKey($linkKey, $state);

        self::assertNotNull($parentKey);
        self::assertContains($parentKey, $policy->orderedDocumentChildKeys($state));
        self::assertNotContains($linkKey, $policy->orderedDocumentChildKeys($state));
    }

    /**
     * @return DocumentSerializationPlanBuildState
     */
    private function buildState(Document $document): DocumentSerializationPlanBuildState
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
