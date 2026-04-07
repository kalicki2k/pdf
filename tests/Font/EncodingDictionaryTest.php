<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\EncodingDictionary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncodingDictionaryTest extends TestCase
{
    #[Test]
    public function it_renders_encoding_differences_with_compact_code_ranges(): void
    {
        $dictionary = new EncodingDictionary(7, 'StandardEncoding', [
            128 => 'Adieresis',
            129 => 'Odieresis',
            140 => 'germandbls',
        ]);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Encoding /BaseEncoding /StandardEncoding /Differences [128 /Adieresis /Odieresis 140 /germandbls] >>\n"
            . "endobj\n",
            $dictionary->render(),
        );
    }
}
