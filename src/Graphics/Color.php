<?php

declare(strict_types=1);

namespace Kalle\Pdf\Graphics;

use InvalidArgumentException;

final readonly class Color
{
    private function __construct(
        private string $colorSpace,
        /** @var list<float> */
        private array $components,
    ) {
    }

    public static function rgb(int $red, int $green, int $blue): self
    {
        self::assertByte($red, 'red');
        self::assertByte($green, 'green');
        self::assertByte($blue, 'blue');

        return new self('rgb', [
            $red / 255,
            $green / 255,
            $blue / 255,
        ]);
    }

    public static function gray(float $value): self
    {
        self::assertUnitInterval($value, 'gray');

        return new self('gray', [$value]);
    }

    public static function cmyk(float $cyan, float $magenta, float $yellow, float $black): self
    {
        self::assertUnitInterval($cyan, 'cyan');
        self::assertUnitInterval($magenta, 'magenta');
        self::assertUnitInterval($yellow, 'yellow');
        self::assertUnitInterval($black, 'black');

        return new self('cmyk', [$cyan, $magenta, $yellow, $black]);
    }

    public static function hex(string $value): self
    {
        if (!preg_match('/^#?([A-Fa-f0-9]{6})$/', $value, $matches)) {
            throw new InvalidArgumentException("Hex color must be a 6-digit RGB value, got '$value'.");
        }

        $hex = $matches[1];

        return self::rgb(
            self::parseHexByte(substr($hex, 0, 2)),
            self::parseHexByte(substr($hex, 2, 2)),
            self::parseHexByte(substr($hex, 4, 2)),
        );
    }

    public function renderNonStrokingOperator(): string
    {
        $components = implode(' ', array_map(self::formatComponent(...), $this->components));

        return match ($this->colorSpace) {
            'gray' => $components . ' g',
            'rgb' => $components . ' rg',
            'cmyk' => $components . ' k',
            default => throw new InvalidArgumentException("Unsupported color space '$this->colorSpace'."),
        };
    }

    public function renderStrokingOperator(): string
    {
        $components = implode(' ', array_map(self::formatComponent(...), $this->components));

        return match ($this->colorSpace) {
            'gray' => $components . ' G',
            'rgb' => $components . ' RG',
            'cmyk' => $components . ' K',
            default => throw new InvalidArgumentException("Unsupported color space '$this->colorSpace'."),
        };
    }

    private static function assertByte(int $value, string $name): void
    {
        if ($value < 0 || $value > 255) {
            throw new InvalidArgumentException(ucfirst($name) . " channel must be between 0 and 255, got $value.");
        }
    }

    private static function assertUnitInterval(float $value, string $name): void
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException(ucfirst($name) . " value must be between 0.0 and 1.0, got $value.");
        }
    }

    private static function formatComponent(float $value): string
    {
        $formatted = sprintf('%.6F', $value);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function parseHexByte(string $value): int
    {
        return (int) hexdec($value);
    }
}
