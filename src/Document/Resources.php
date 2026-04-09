<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Graphics\Opacity;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\ReferenceType;

final class Resources extends DictionaryIndirectObject
{
    /** @var array<int, FontDefinition&IndirectObject>  */
    private array $fonts = [];
    /** @var ImageObject[] */
    private array $images = [];
    /** @var list<string> */
    private array $extGStates = [];
    /** @var OptionalContentGroup[] */
    private array $properties = [];

    public function __construct(int $id)
    {
        parent::__construct($id);
    }

    public function addFont(FontDefinition $font): string
    {
        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('Font resources must be indirect objects.');
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

    public function registerFont(FontDefinition $font): string
    {
        return $this->addFont($font);
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

    public function addProperty(OptionalContentGroup $group): string
    {
        foreach ($this->properties as $index => $registeredGroup) {
            if ($registeredGroup->id === $group->id) {
                return 'OC' . ($index + 1);
            }
        }

        $this->properties[] = $group;

        return 'OC' . count($this->properties);
    }

    /**
     * @return list<ImageObject>
     */
    public function getImages(): array
    {
        $images = [];

        foreach ($this->images as $image) {
            foreach ($image->getRelatedObjects() as $relatedImage) {
                $images[] = $relatedImage;
            }
        }

        return $images;
    }

    protected function dictionary(): DictionaryType
    {
        $fontReferences = [];
        $imageReferences = [];
        $extGStateEntries = [];
        $propertyReferences = [];

        foreach ($this->fonts as $index => $registeredFont) {
            $fontReferences['F' . ($index + 1)] = new ReferenceType($registeredFont);
        }

        foreach ($this->extGStates as $index => $registeredExtGState) {
            $extGStateEntries['GS' . ($index + 1)] = $registeredExtGState;
        }

        foreach ($this->images as $index => $registeredImage) {
            $imageReferences['Im' . ($index + 1)] = new ReferenceType($registeredImage);
        }

        foreach ($this->properties as $index => $registeredProperty) {
            $propertyReferences['OC' . ($index + 1)] = new ReferenceType($registeredProperty);
        }

        $dictionary = new DictionaryType([
            'Font' => new DictionaryType($fontReferences),
        ]);

        if ($imageReferences !== []) {
            $dictionary->add('XObject', new DictionaryType($imageReferences));
        }

        if ($extGStateEntries !== []) {
            $dictionary->add('ExtGState', new DictionaryType($extGStateEntries));
        }

        if ($propertyReferences !== []) {
            $dictionary->add('Properties', new DictionaryType($propertyReferences));
        }

        return $dictionary;
    }
}
