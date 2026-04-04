<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Reference;

final class Resources extends IndirectObject
{
    /** @var array<int, FontDefinition&IndirectObject>  */
    private array $fonts = [];
    /** @var ImageObject[] */
    private array $images = [];
    /** @var list<string> */
    private array $extGStates = [];

    public function __construct(int $id)
    {
        parent::__construct($id);
    }

    public function addFont(FontDefinition $font): string
    {
        if (!$font instanceof IndirectObject) {
            throw new \InvalidArgumentException('Font resources must be indirect objects.');
        }

        foreach ($this->fonts as $index => $registeredFont) {
            if ($registeredFont->getId() === $font->getId()) {
                return 'F' . ($index + 1);
            }
        }

        /** @var FontDefinition&IndirectObject $font */
        $this->fonts[] = $font;

        return 'F' . count($this->fonts);
    }

    public function addOpacity(Opacity $opacity): string
    {
        $renderedOpacity = $opacity->renderExtGStateDictionary();

        foreach ($this->extGStates as $index => $registeredExtGState) {
            if ($registeredExtGState === $renderedOpacity) {
                return 'GS' . ($index + 1);
            }
        }

        $this->extGStates[] = $renderedOpacity;

        return 'GS' . count($this->extGStates);
    }

    public function addImage(ImageObject $image): string
    {
        foreach ($this->images as $index => $registeredImage) {
            if ($registeredImage->getId() === $image->getId()) {
                return 'Im' . ($index + 1);
            }
        }

        $this->images[] = $image;

        return 'Im' . count($this->images);
    }

    /**
     * @return list<ImageObject>
     */
    public function getImages(): array
    {
        return array_values($this->images);
    }

    public function render(): string
    {
        $fontReferences = [];
        $imageReferences = [];
        $extGStateEntries = [];

        foreach ($this->fonts as $index => $registeredFont) {
            $fontReferences['F' . ($index + 1)] = new Reference($registeredFont);
        }

        foreach ($this->extGStates as $index => $registeredExtGState) {
            $extGStateEntries['GS' . ($index + 1)] = $registeredExtGState;
        }

        foreach ($this->images as $index => $registeredImage) {
            $imageReferences['Im' . ($index + 1)] = new Reference($registeredImage);
        }

        $dictionary = new Dictionary([
            'Font' => new Dictionary($fontReferences),
        ]);

        if ($imageReferences !== []) {
            $dictionary->add('XObject', new Dictionary($imageReferences));
        }

        if ($extGStateEntries !== []) {
            $dictionary->add('ExtGState', new Dictionary($extGStateEntries));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
