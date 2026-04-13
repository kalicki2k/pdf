<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Image;

use Kalle\Pdf\Image\LzwDecoder;
use Kalle\Pdf\Image\LzwEncoder;
use PHPUnit\Framework\TestCase;

final class LzwDecoderTest extends TestCase
{
    public function testItDecodesDataEncodedByTheMatchingEncoder(): void
    {
        $source = "ABCABCABC\x00\xFF";
        $encoded = (new LzwEncoder())->encode($source);

        self::assertSame($source, (new LzwDecoder())->decode($encoded));
    }

    public function testItDecodesAnEmptyPayload(): void
    {
        $encoded = (new LzwEncoder())->encode('');

        self::assertSame('', (new LzwDecoder())->decode($encoded));
    }
}
