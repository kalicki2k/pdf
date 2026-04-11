<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class BidiMirroring
{
    /**
     * @var array<string, string>
     */
    private const array MAP = [
        '(' => ')',
        ')' => '(',
        '[' => ']',
        ']' => '[',
        '{' => '}',
        '}' => '{',
        '<' => '>',
        '>' => '<',
    ];

    public function isMirrorable(string $character): bool
    {
        return isset(self::MAP[$character]);
    }

    public function mirror(string $character): string
    {
        return self::MAP[$character] ?? $character;
    }
}
