<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentFontAndImageObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontEncoding;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class DocumentFontAndImageObjectBuilderTest extends TestCase
{
    public function testItBuildsStandardFontObjectsFromDeduplicatedPageResources(): void
    {
        $document = new Document(pages: [
            new Page(
                PageSize::A4(),
                fontResources: [
                    'F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::WIN_ANSI),
                ],
            ),
            new Page(
                PageSize::A4(),
                fontResources: [
                    'F1' => new PageFont(StandardFont::HELVETICA->value, StandardFontEncoding::WIN_ANSI),
                ],
            ),
        ]);

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

        $objects = (new DocumentFontAndImageObjectBuilder())->buildObjects($document, $state);
        $fontObjectId = current($state->fontObjectIds);

        self::assertNotFalse($fontObjectId);
        self::assertCount(1, $objects);
        self::assertTrue($this->containsObjectId($objects, $fontObjectId));
    }

    public function testItIncludesSoftMaskImageObjectsRecursively(): void
    {
        $mask = ImageSource::alphaMask('mask-data', 2, 1);
        $image = ImageSource::flate('image-data', 2, 1, ImageColorSpace::RGB, softMask: $mask);
        $document = new Document(pages: [
            new Page(
                PageSize::A4(),
                imageResources: ['Im1' => $image],
            ),
        ]);

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

        $objects = (new DocumentFontAndImageObjectBuilder())->buildObjects($document, $state);

        self::assertCount(2, $objects);
        self::assertTrue($this->containsObjectId($objects, $state->imageObjectIds[$image->key()]));
        self::assertTrue($this->containsObjectId($objects, $state->imageObjectIds[$mask->key()]));
    }

    /**
     * @param list<object> $objects
     */
    private function containsObjectId(array $objects, int $objectId): bool
    {
        foreach ($objects as $object) {
            if ($object->objectId === $objectId) {
                return true;
            }
        }

        return false;
    }
}
