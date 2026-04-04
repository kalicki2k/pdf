<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Font;

use Kalle\Pdf\Font\FontDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FontDefinitionTest extends TestCase
{
    #[Test]
    public function it_can_be_implemented_as_a_font_definition_contract(): void
    {
        $fontDefinition = new class () implements FontDefinition {
            public function getId(): int
            {
                return 42;
            }

            public function getBaseFont(): string
            {
                return 'Helvetica';
            }

            public function supportsText(string $text): bool
            {
                return $text !== '';
            }

            public function encodeText(string $text): string
            {
                return strtoupper($text);
            }

            public function render(): string
            {
                return '42 0 obj';
            }
        };

        self::assertInstanceOf(FontDefinition::class, $fontDefinition);
        self::assertSame(42, $fontDefinition->getId());
        self::assertSame('Helvetica', $fontDefinition->getBaseFont());
        self::assertTrue($fontDefinition->supportsText('abc'));
        self::assertFalse($fontDefinition->supportsText(''));
        self::assertSame('ABC', $fontDefinition->encodeText('abc'));
        self::assertSame('42 0 obj', $fontDefinition->render());
    }
}
