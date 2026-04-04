<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Image;
use Kalle\Pdf\Object\IndirectObject;

final class ImageObject extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly Image $image,
    ) {
        parent::__construct($id);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function render(): string
    {
        return $this->id . ' 0 obj' . PHP_EOL
            . $this->image->render()
            . 'endobj' . PHP_EOL;
    }
}
