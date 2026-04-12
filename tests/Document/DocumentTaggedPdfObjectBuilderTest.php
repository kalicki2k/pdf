<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class DocumentTaggedPdfObjectBuilderTest extends TestCase
{
    public function testItGroupsTaggedLinkAltTextWithWhitespaceBetweenWordParts(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible',
            language: 'de-DE',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new LinkAnnotation(
                            LinkTarget::externalUrl('https://example.com/docs'),
                            10,
                            10,
                            50,
                            10,
                            accessibleLabel: 'Read',
                            taggedGroupKey: 'docs-link',
                        ),
                        new LinkAnnotation(
                            LinkTarget::externalUrl('https://example.com/docs'),
                            10,
                            30,
                            50,
                            10,
                            accessibleLabel: 'Docs',
                            taggedGroupKey: 'docs-link',
                        ),
                    ],
                ),
            ],
        );

        $structure = (new DocumentTaggedPdfObjectBuilder())->collectTaggedLinkStructure($document, 0);

        self::assertCount(1, $structure['linkEntries']);
        self::assertSame('Read Docs', $structure['linkEntries'][0]['altText']);
        self::assertSame([0, 1], $structure['linkEntries'][0]['annotationIndices']);
    }

    public function testItBuildsNoTaggedObjectsForPlainDocuments(): void
    {
        $document = new Document();
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
            $document,
            static fn (int $nextStructParentId): array => [
                'linkEntries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ],
            static fn (array $fieldObjectIds, array $relatedObjectIds, int $nextStructParentId): array => [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
            ],
            static fn (): array => [],
        );

        $objects = (new DocumentTaggedPdfObjectBuilder())->buildObjects($document, $state);

        self::assertSame([], $objects);
    }
}
