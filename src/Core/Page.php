<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use InvalidArgumentException;
use Kalle\Pdf\Elements\Text;
use Kalle\Pdf\Types\ArrayValue;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;
use Kalle\Pdf\Types\Reference;

final class Page extends IndirectObject
{
    private int $markedContentId = 0;
    public Contents $contents;
    public Resources $resources;

    public function __construct(
        public int                $id,
        int                       $contentsId,
        int                       $resourcesId,
        private readonly float    $width,
        private readonly float    $height,
        private readonly Document $document
    )
    {
        parent::__construct($this->id);

        $this->contents = new Contents($contentsId);
        $this->resources = new Resources($resourcesId);
    }

    public function addText(string $text, float $x, float $y, string $baseFont, int $size, string $tag): self
    {
        $font = null;
        $markedContentId = $this->markedContentId++;

        foreach ($this->document->fonts as $registeredFont) {
            if ($registeredFont->getBaseFont() === $baseFont) {
                $font = $registeredFont;
                break;
            }
        }

        if ($font === null) {
            throw new InvalidArgumentException("Font '$baseFont' is not registered.");
        }

        $this->contents->addElement(new Text($markedContentId, $text, $x, $y, $this->resources->addFont($font), $size, $tag));
        $this->document->addStructElem($tag, $markedContentId);

        return $this;
    }

    public function addImage(): self
    {
        return $this;
    }

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Type' => new Name('Page'),
            'Parent' => new Reference($this->document->pages),
            'MediaBox' => new ArrayValue([0, 0, $this->width, $this->height]),
            'Resources' => new Reference($this->resources),
            'Contents' => new Reference($this->contents),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
