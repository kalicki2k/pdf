<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentAttachmentObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentAttachmentObjectBuilderTest extends TestCase
{
    public function testItBuildsAttachmentCatalogEntriesWithAssociatedFiles(): void
    {
        $document = new Document(
            profile: Profile::pdfA3b(),
            attachments: [
                new FileAttachment(
                    'data.xml',
                    new EmbeddedFile('<root/>', 'application/xml'),
                    associatedFileRelationship: AssociatedFileRelationship::SOURCE,
                ),
            ],
        );

        $catalogEntries = new DocumentAttachmentObjectBuilder()->buildCatalogEntries($document, [6]);

        self::assertSame([
            '/Names << /EmbeddedFiles << /Names [(data.xml) 6 0 R] >> >>',
            '/AF [6 0 R]',
        ], $catalogEntries);
    }

    public function testItBuildsEmbeddedFileAndFilespecObjects(): void
    {
        $document = new Document(
            profile: Profile::pdf20(),
            attachments: [
                new FileAttachment(
                    'demo.txt',
                    new EmbeddedFile('hello', 'text/plain'),
                    description: 'Demo attachment',
                ),
            ],
        );
        $state = new DocumentSerializationPlanObjectIdAllocator()->allocate(
            $document,
            static fn (int $nextStructParentId): array => [
                'linkEntries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ],
            static fn (int $nextStructParentId): array => [
                'entries' => [],
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

        $objects = new DocumentAttachmentObjectBuilder()->buildObjects($document, $state);

        self::assertCount(2, $objects);
        self::assertStringContainsString('/Type /EmbeddedFile', $objects[0]->contents);
        self::assertStringContainsString('/Subtype /text#2Fplain', $objects[0]->contents);
        self::assertStringContainsString('/Type /Filespec', $objects[1]->contents);
        self::assertStringContainsString('/Desc (Demo attachment)', $objects[1]->contents);
    }
}
