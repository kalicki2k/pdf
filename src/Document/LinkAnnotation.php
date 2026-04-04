<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;
use Kalle\Pdf\Types\StringValue;

final class LinkAnnotation extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly string $url,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Annot'),
            'Subtype' => new Name('Link'),
            'Rect' => new ArrayValue([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'Border' => new ArrayValue([0, 0, 0]),
            'A' => new Dictionary([
                'S' => new Name('URI'),
                'URI' => new StringValue($this->url),
            ]),
            'P' => new Reference($this->page),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
