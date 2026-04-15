<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\LinkAnnotation;

final readonly class TaggedPageAnnotationStructureCollector
{
    public function __construct(
        private TaggedPageAnnotationResolver $taggedPageAnnotationResolver = new TaggedPageAnnotationResolver(),
    ) {
    }

    /**
     * @return array{
     *   entries: list<array{
     *     key: string,
     *     pageIndex: int,
     *     annotationIndex: int,
     *     altText: string,
     *     tag: string
     *   }>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<string, int>,
     *   nextStructParentId: int
     * }
     */
    public function collect(Document $document, int $nextStructParentId): array
    {
        if (!$document->profile->requiresTaggedPageAnnotations()) {
            return [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ];
        }

        $entries = [];
        $structParentRegistry = new TaggedAnnotationStructParentRegistry($nextStructParentId);

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                if ($annotation instanceof LinkAnnotation || !$this->taggedPageAnnotationResolver->supports($document, $annotation)) {
                    continue;
                }

                $altText = $this->taggedPageAnnotationResolver->altText($document, $annotation);

                if ($altText === null || $altText === '') {
                    continue;
                }

                $entryKey = 'annotation:' . $pageIndex . ':' . $annotationIndex;
                $entries[] = [
                    'key' => $entryKey,
                    'pageIndex' => $pageIndex,
                    'annotationIndex' => $annotationIndex,
                    'altText' => $altText,
                    'tag' => $this->taggedPageAnnotationResolver->structureTag($document, $annotation) ?? 'Annot',
                ];
                $structParentRegistry->register($pageIndex . ':' . $annotationIndex, $entryKey);
            }
        }

        return [
            'entries' => $entries,
            'parentTreeEntries' => $structParentRegistry->parentTreeEntries(),
            'structParentIds' => $structParentRegistry->stringStructParentIds(),
            'nextStructParentId' => $structParentRegistry->nextStructParentId(),
        ];
    }
}
