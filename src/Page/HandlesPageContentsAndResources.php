<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Object\StreamLengthObject;
use Kalle\Pdf\Page\Annotation\PageAnnotation;
use Kalle\Pdf\Page\Content\Contents;
use Kalle\Pdf\Page\Content\Instruction\ContentInstruction;
use Kalle\Pdf\Page\Resources\ImageObject;
use Kalle\Pdf\Page\Resources\Resources;
use Kalle\Pdf\Style\Opacity;

trait HandlesPageContentsAndResources
{
    public function getResources(): Resources
    {
        return $this->resources;
    }

    public function getContents(): Contents
    {
        return $this->contents;
    }

    public function prepareContentsLengthObject(): StreamLengthObject
    {
        $lengthObject = $this->contents->getLengthObject();

        if ($lengthObject !== null) {
            return $lengthObject;
        }

        return $this->contents->prepareLengthObject($this->document->getUniqObjectId());
    }

    public function addContentElement(ContentInstruction $element): void
    {
        $this->contents->addElement($element);
    }

    public function addFontResource(FontDefinition $font): string
    {
        return $this->resources->addFont($font);
    }

    public function addImageResource(ImageObject $image): string
    {
        return $this->resources->addImage($image);
    }

    public function addPropertyResource(OptionalContentGroup $group): string
    {
        return $this->resources->addProperty($group);
    }

    public function addOpacityResource(Opacity $opacity): string
    {
        return $this->resources->addOpacity($opacity);
    }

    /**
     * @return list<IndirectObject&PageAnnotation>
     */
    public function getAnnotations(): array
    {
        return $this->collaborators->existingAnnotations()?->all() ?? [];
    }
}
