<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

enum ImageColorSpace: string
{
    case GRAY = '/DeviceGray';
    case RGB = '/DeviceRGB';
    case CMYK = '/DeviceCMYK';

    public function pdfName(): string
    {
        return $this->value;
    }
}
