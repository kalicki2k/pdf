<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Resources;

use InvalidArgumentException;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Font\StandardFont;
use Kalle\Pdf\Internal\Page\Resources\ImageObject;
use Kalle\Pdf\Internal\Page\Resources\Resources;
use Kalle\Pdf\Style\Opacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResourcesTest extends TestCase
{
    #[Test]
    public function it_renders_empty_font_resources(): void
    {
        $resources = new Resources(8);

        self::assertSame(
            "8 0 obj\n<< /Font <<  >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_assigns_incrementing_font_resource_names(): void
    {
        $resources = new Resources(8);
        $fontOne = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);
        $fontTwo = new StandardFont(7, 'Times-Roman', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame('F1', $resources->registerFont($fontOne));
        self::assertSame('F2', $resources->registerFont($fontTwo));
        self::assertSame(
            "8 0 obj\n<< /Font << /F1 6 0 R /F2 7 0 R >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_reuses_the_existing_resource_name_for_the_same_font_id(): void
    {
        $resources = new Resources(8);
        $font = new StandardFont(6, 'Helvetica', 'Type1', 'WinAnsiEncoding', 1.4);

        self::assertSame('F1', $resources->registerFont($font));
        self::assertSame('F1', $resources->registerFont($font));
        self::assertSame(
            "8 0 obj\n<< /Font << /F1 6 0 R >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_assigns_incrementing_extgstate_resource_names_for_opacity(): void
    {
        $resources = new Resources(8);

        self::assertSame('GS1', $resources->addOpacity(Opacity::fill(0.5)));
        self::assertSame('GS2', $resources->addOpacity(Opacity::stroke(0.25)));
        self::assertSame(
            "8 0 obj\n<< /Font <<  >> /ExtGState << /GS1 << /ca 0.5 >> /GS2 << /CA 0.25 >> >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_reuses_the_existing_extgstate_resource_name_for_the_same_opacity(): void
    {
        $resources = new Resources(8);

        self::assertSame('GS1', $resources->addOpacity(Opacity::both(0.4)));
        self::assertSame('GS1', $resources->addOpacity(Opacity::both(0.4)));
        self::assertSame(
            "8 0 obj\n<< /Font <<  >> /ExtGState << /GS1 << /ca 0.4 /CA 0.4 >> >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_registers_image_xobjects_as_named_resources(): void
    {
        $resources = new Resources(8);
        $image = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));

        self::assertSame('Im1', $resources->addImage($image));
        self::assertSame(
            "8 0 obj\n<< /Font <<  >> /XObject << /Im1 9 0 R >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_reuses_the_existing_image_resource_name_for_the_same_image_id(): void
    {
        $resources = new Resources(8);
        $image = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));

        self::assertSame('Im1', $resources->addImage($image));
        self::assertSame('Im1', $resources->addImage($image));
    }

    #[Test]
    public function it_registers_optional_content_groups_as_properties_resources(): void
    {
        $resources = new Resources(8);
        $group = new OptionalContentGroup(9, 'Notes');

        self::assertSame('OC1', $resources->addProperty($group));
        self::assertSame(
            "8 0 obj\n<< /Font <<  >> /Properties << /OC1 9 0 R >> >>\nendobj\n",
            $resources->render(),
        );
    }

    #[Test]
    public function it_reuses_the_existing_property_resource_name_for_the_same_group_id(): void
    {
        $resources = new Resources(8);
        $group = new OptionalContentGroup(9, 'Notes');

        self::assertSame('OC1', $resources->addProperty($group));
        self::assertSame('OC1', $resources->addProperty($group));
    }

    #[Test]
    public function it_returns_image_objects_including_related_soft_masks(): void
    {
        $resources = new Resources(8);
        $softMask = new ImageObject(10, new Image(320, 200, 'DeviceGray', 'FlateDecode', 'mask'));
        $image = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'), $softMask);

        $resources->addImage($image);

        self::assertSame([$image, $softMask], $resources->getImages());
    }

    #[Test]
    public function it_rejects_fonts_that_are_not_indirect_objects(): void
    {
        $resources = new Resources(8);
        $font = new class () implements FontDefinition {
            public function getId(): int
            {
                return 1;
            }

            public function getBaseFont(): string
            {
                return 'CustomFont';
            }

            public function supportsText(string $text): bool
            {
                return true;
            }

            public function encodeText(string $text): string
            {
                return $text;
            }

            public function measureTextWidth(string $text, float $size): float
            {
                return 0.0;
            }

            public function render(): string
            {
                return '';
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Font resources must be indirect objects.');

        $resources->addFont($font);
    }
}
