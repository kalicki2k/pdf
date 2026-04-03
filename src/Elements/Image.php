<?php

declare(strict_types=1);

namespace Kalle\Pdf\Elements;

use Kalle\Pdf\Core\Element;

class Image extends Element
{
    private int $width;
    private int $height;
    private string $colorSpace;
    private string $filter;
    private string $data;

    public function __construct(
        int $width,
        int $height,
        string $colorSpace,
        string $filter,
        string $data,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->filter = $filter;
        $this->data = $data;
    }

    public function render(): string
    {
        $output = '<< /Type /XObject' . PHP_EOL;
        $output .= '/Subtype /Image' . PHP_EOL;
        $output .= "/Width {$this->width}" . PHP_EOL;
        $output .= "/Height {$this->height}" . PHP_EOL;
        $output .= "/ColorSpace /{$this->colorSpace}" . PHP_EOL;
        $output .= '/BitsPerComponent 8' . PHP_EOL;
        $output .= "/Filter /{$this->filter}" . PHP_EOL;
        $output .= '/Length ' . strlen($this->data) . ' >>' . PHP_EOL;
        $output .= 'stream' . PHP_EOL;
        $output .= $this->data . PHP_EOL;
        $output .= 'endstream' . PHP_EOL;

        return $output;
    }
}
