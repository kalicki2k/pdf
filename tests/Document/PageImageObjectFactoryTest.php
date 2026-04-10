<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Page\Content\PageImageObjectFactory;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageImageObjectFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_an_image_object_without_a_soft_mask(): void
    {
        $factory = new PageImageObjectFactory(new Document(profile: Profile::standard(1.4)));

        $imageObject = $factory->create(new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));

        self::assertCount(1, $imageObject->getRelatedObjects());
        self::assertSame($imageObject, $imageObject->getRelatedObjects()[0]);
    }

    #[Test]
    public function it_creates_related_image_objects_recursively_for_soft_masks(): void
    {
        $factory = new PageImageObjectFactory(new Document(profile: Profile::standard(1.4)));
        $image = new Image(
            1,
            1,
            'DeviceRGB',
            'FlateDecode',
            "\x00\x00\x00",
            softMask: new Image(1, 1, 'DeviceGray', 'FlateDecode', "\x00"),
        );

        $imageObject = $factory->create($image);
        $relatedObjects = $imageObject->getRelatedObjects();

        self::assertCount(2, $relatedObjects);
        self::assertSame($imageObject, $relatedObjects[0]);
        self::assertSame($imageObject->getId() + 1, $relatedObjects[1]->getId());
    }
}
