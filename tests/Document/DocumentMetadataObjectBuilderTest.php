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

    public function testPdfA4MetadataAllocationSkipsOutputIntentAndInfoDictionary(): void
    {
        $document = new Document(
            profile: Profile::pdfA4(),
            title: 'Archive Copy',
            author: 'Kalle',
        );
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
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

        self::assertNotNull($state->metadataObjectId);
        self::assertNull($state->iccProfileObjectId);
        self::assertNull($state->infoObjectId);

        $entries = (new DocumentMetadataObjectBuilder())->buildCatalogEntries($document, $state->metadataObjectId, $state->iccProfileObjectId);

        self::assertSame([
            '/Metadata ' . $state->metadataObjectId . ' 0 R',
        ], $entries);
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

    public function testItEncodesUnicodeMetadataIntoTheInfoDictionary(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Projektübersicht',
            author: 'Jörg Example',
            subject: 'Überblick',
            keywords: 'prüfung, pdfa',
            creator: 'Rocket 🚀',
            creatorTool: 'pdf2 Prüfsuite',
        );
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
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

        $objects = (new DocumentMetadataObjectBuilder())->buildObjects(
            $document,
            $state,
            new DateTimeImmutable('2026-04-12T10:00:00+02:00'),
            '',
        );

        self::assertCount(3, $objects);
        self::assertStringContainsString('/Title (Projekt\\374bersicht)', $objects[2]->contents);
        self::assertStringContainsString('/Author (J\\366rg Example)', $objects[2]->contents);
        self::assertStringContainsString('/Subject (\\334berblick)', $objects[2]->contents);
        self::assertStringContainsString('/Keywords (pr\\374fung, pdfa)', $objects[2]->contents);
        self::assertStringContainsString('/Producer (pdf2 Pr\\374fsuite)', $objects[2]->contents);
        self::assertStringContainsString('/Creator (\\376\\377', $objects[2]->contents);
        self::assertStringNotContainsString('Projektübersicht', $objects[2]->contents);
        self::assertStringNotContainsString('Jörg Example', $objects[2]->contents);
        self::assertStringNotContainsString('Überblick', $objects[2]->contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Projektübersicht</rdf:li>', $objects[0]->contents);
        self::assertStringContainsString('<rdf:li>Jörg Example</rdf:li>', $objects[0]->contents);
        self::assertStringContainsString('<pdf:Keywords>prüfung, pdfa</pdf:Keywords>', $objects[0]->contents);
    }

    public function testItEscapesSpecialCharactersAndAvoidsRawUtf8AcrossInfoMetadataFields(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Title (draft) \\ check',
            author: 'Jörg Example',
            subject: "Überblick\nQ2",
            keywords: 'foo, (bar), €',
            creator: 'Rocket 🚀 \\ QA',
            creatorTool: 'pdf2 € suite',
        );
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
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

        $objects = (new DocumentMetadataObjectBuilder())->buildObjects(
            $document,
            $state,
            new DateTimeImmutable('2026-04-12T10:00:00+02:00'),
            '',
        );

        self::assertStringContainsString('/Title (Title \\(draft\\) \\\\ check)', $objects[2]->contents);
        self::assertStringContainsString('/Author (J\\366rg Example)', $objects[2]->contents);
        self::assertStringContainsString('/Subject (\\334berblick\\012Q2)', $objects[2]->contents);
        self::assertStringContainsString('/Keywords (foo, \\(bar\\), \\240)', $objects[2]->contents);
        self::assertStringContainsString('/Producer (pdf2 \\240 suite)', $objects[2]->contents);
        self::assertStringContainsString('/Creator (\\376\\377', $objects[2]->contents);
        self::assertStringNotContainsString('🚀', $objects[2]->contents);
        self::assertStringNotContainsString('Überblick', $objects[2]->contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Title (draft) \\ check</rdf:li>', $objects[0]->contents);
        self::assertStringContainsString('<rdf:li>Jörg Example</rdf:li>', $objects[0]->contents);
        self::assertStringContainsString('<dc:description>', $objects[0]->contents);
        self::assertStringContainsString('<rdf:li xml:lang="x-default">Überblick', $objects[0]->contents);
        self::assertStringContainsString('<pdf:Keywords>foo, (bar), €</pdf:Keywords>', $objects[0]->contents);
        self::assertStringContainsString('<xmp:CreatorTool>Rocket 🚀 \\ QA</xmp:CreatorTool>', $objects[0]->contents);
        self::assertStringContainsString('<pdf:Producer>pdf2 € suite</pdf:Producer>', $objects[0]->contents);
    }
}
