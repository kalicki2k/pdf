<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Resources;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Graphics\Opacity;
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

        self::assertSame('F1', $resources->addFont($fontOne));
        self::assertSame('F2', $resources->addFont($fontTwo));
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

        self::assertSame('F1', $resources->addFont($font));
        self::assertSame('F1', $resources->addFont($font));
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
}
