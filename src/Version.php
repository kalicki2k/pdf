<?php

namespace Kalle\Pdf;

use InvalidArgumentException;

final readonly class Profile
{
    public const float V1_0 = 1.0;
    public const float V1_1 = 1.1;
    public const float V1_2 = 1.2;
    public const float V1_3 = 1.3;
    public const float V1_4 = 1.4;
    public const float V1_5 = 1.5;
    public const float V1_6 = 1.6;
    public const float V1_7 = 1.7;
    public const float V2_0 = 2.0;

    private function __construct(private string $name, private float $version)
    {
    }

    public static function standard(float $version = self::V1_4): self
    {
        self::assertSupportedStandardVersion($version);

        return new self('standard', $version);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): float
    {
        return $this->version;
    }

    /**
     * @return list<float>
     */
    private static function all(): array
    {
        return [
            self::V1_0,
            self::V1_1,
            self::V1_2,
            self::V1_3,
            self::V1_4,
            self::V1_5,
            self::V1_6,
            self::V1_7,
            self::V2_0,
        ];
    }

    private static function assertSupportedStandardVersion(float $version): void
    {
        $supportedVersions = self::all();

        if (!in_array($version, $supportedVersions, true)) {
            throw new InvalidArgumentException('Unsupported PDF version. Supported versions are 1.0 to 1.7 and 2.0.');
        }
    }
}