<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Utilities;

use Kalle\Pdf\Utilities\StringListNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringListNormalizerTest extends TestCase
{
    #[Test]
    public function it_trims_values_and_keeps_first_unique_occurrence(): void
    {
        $values = ['  alpha  ', 'beta', 'alpha', ' beta ', 'gamma'];

        self::assertSame(['alpha', 'beta', 'gamma'], StringListNormalizer::unique($values));
    }
}
