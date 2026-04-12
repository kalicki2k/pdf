<?php

declare(strict_types=1);

namespace Kalle\Pdf\Color;

use InvalidArgumentException;

final readonly class Color
{
    /**
     * @param list<float> $components
     */
    private function __construct(
        public ColorSpace $space,
        public array $components,
    ) {
    }

    public static function hex(string $color): self
    {
        $normalizedColor = ltrim($color, '#');

        if (strlen($normalizedColor) === 3) {
            $normalizedColor = $normalizedColor[0] . $normalizedColor[0]
                . $normalizedColor[1] . $normalizedColor[1]
                . $normalizedColor[2] . $normalizedColor[2];
        }

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $normalizedColor)) {
            throw new InvalidArgumentException('Color hex value must be a 3- or 6-digit hexadecimal string.');
        }

        return self::rgb(
            hexdec(substr($normalizedColor, 0, 2)) / 255,
            hexdec(substr($normalizedColor, 2, 2)) / 255,
            hexdec(substr($normalizedColor, 4, 2)) / 255,
        );
    }

    public static function rgb(float $red, float $green, float $blue): self
    {
        self::assertChannel($red, 'red');
        self::assertChannel($green, 'green');
        self::assertChannel($blue, 'blue');

        return new self(ColorSpace::RGB, [$red, $green, $blue]);
    }

    public static function gray(float $value): self
    {
        self::assertChannel($value, 'gray');

        return new self(ColorSpace::GRAY, [$value]);
    }

    public static function cmyk(float $cyan, float $magenta, float $yellow, float $black): self
    {
        self::assertChannel($cyan, 'cyan');
        self::assertChannel($magenta, 'magenta');
        self::assertChannel($yellow, 'yellow');
        self::assertChannel($black, 'black');

        return new self(ColorSpace::CMYK, [$cyan, $magenta, $yellow, $black]);
    }

    public static function black(): self
    {
        return self::gray(0.0);
    }

    public static function white(): self
    {
        return self::gray(1.0);
    }

    public static function red(): self
    {
        return self::hex('#ff0000');
    }

    public static function green(): self
    {
        return self::hex('#008000');
    }

    public static function blue(): self
    {
        return self::hex('#0000ff');
    }

    public static function yellow(): self
    {
        return self::hex('#ffff00');
    }

    public static function cyan(): self
    {
        return self::hex('#00ffff');
    }

    public static function magenta(): self
    {
        return self::hex('#ff00ff');
    }

    public static function orange(): self
    {
        return self::hex('#ffa500');
    }

    public static function purple(): self
    {
        return self::hex('#800080');
    }

    public static function pink(): self
    {
        return self::hex('#ffc0cb');
    }

    public static function brown(): self
    {
        return self::hex('#a52a2a');
    }

    public static function lime(): self
    {
        return self::hex('#00ff00');
    }

    public static function navy(): self
    {
        return self::hex('#000080');
    }

    public static function teal(): self
    {
        return self::hex('#008080');
    }

    public static function olive(): self
    {
        return self::hex('#808000');
    }

    public static function maroon(): self
    {
        return self::hex('#800000');
    }

    public static function silver(): self
    {
        return self::hex('#c0c0c0');
    }

    /**
     * @return list<float>
     */
    public function components(): array
    {
        return $this->components;
    }

    private static function assertChannel(float $value, string $channel): void
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException(sprintf(
                'Color channel "%s" must be between 0.0 and 1.0.',
                $channel,
            ));
        }
    }
}
