<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function getimagesizefromstring;
use function sprintf;

use InvalidArgumentException;

final readonly class ImageFormatSniffer
{
    /**
     * @return array{
     *   format: ImageFormat,
     *   imageInfo: array<string|int, mixed>
     * }
     */
    public function sniff(string $data, string $path = 'memory'): array
    {
        $imageInfo = getimagesizefromstring($data);

        if ($imageInfo === false) {
            throw new InvalidArgumentException(sprintf(
                "Image path '%s' uses an unsupported image format.",
                $path,
            ));
        }

        return [
            'format' => ImageFormat::fromImageInfo($imageInfo, $path),
            'imageInfo' => $imageInfo,
        ];
    }
}
