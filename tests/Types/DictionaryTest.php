<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Types;

use Kalle\Pdf\Types\BooleanValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DictionaryTest extends TestCase
{
    #[Test]
    public function it_renders_entries_in_insertion_order(): void
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Catalog'),
            'Count' => 2,
            'Open' => new BooleanValue(true),
            'Version' => '1.4',
        ]);

        self::assertSame('<< /Type /Catalog /Count 2 /Open true /Version 1.4 >>', $dictionary->render());
    }

    #[Test]
    public function it_can_add_entries_after_construction(): void
    {
        $dictionary = new Dictionary(['Type' => new Name('Pages')])
            ->add('Count', 3);

        self::assertSame('<< /Type /Pages /Count 3 >>', $dictionary->render());
    }
}
