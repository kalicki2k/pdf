<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use DateTimeImmutable;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentMetadataObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Encryption;
use PHPUnit\Framework\TestCase;

final class DocumentMetadataObjectBuilderTest extends TestCase
{
    public function testItBuildsCatalogEntriesForMetadataAndOutputIntent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'icc-');

        if ($path === false) {
            self::fail('Failed to create ICC temp file.');
        }

        file_put_contents($path, 'ICC');

        try {
            $document = new Document(
                profile: Profile::pdfA2u(),
                pdfaOutputIntent: new PdfAOutputIntent($path, 'Custom RGB', 'Custom profile', 4),
            );

            $entries = (new DocumentMetadataObjectBuilder())->buildCatalogEntries($document, 5, 6);

            self::assertSame([
                '/Metadata 5 0 R',
                '/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Custom RGB) /Info (Custom profile) /DestOutputProfile 6 0 R >>]',
            ], $entries);
        } finally {
            @unlink($path);
        }
    }

    public function testItBuildsMetadataInfoAndEncryptObjects(): void
    {
        $document = new Document(
            profile: Profile::pdf14(),
            title: 'Example Title',
            encryption: Encryption::rc4_128('user', 'owner'),
        );
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

        $objects = (new DocumentMetadataObjectBuilder())->buildObjects(
            $document,
            $state,
            new DateTimeImmutable('2026-04-12T10:00:00+02:00'),
            '<< /Filter /Standard >>',
        );

        self::assertCount(3, $objects);
        self::assertStringContainsString('/Type /Metadata /Subtype /XML', $objects[0]->contents);
        self::assertStringContainsString('/Title (Example Title)', $objects[1]->contents);
        self::assertSame('<< /Filter /Standard >>', $objects[2]->contents);
        self::assertFalse($objects[2]->encryptable);
    }
}
