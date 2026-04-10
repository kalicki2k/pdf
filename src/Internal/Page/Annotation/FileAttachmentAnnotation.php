<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Internal\Document\Attachment\FileSpecification;
use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class FileAttachmentAnnotation extends DictionaryIndirectObject implements PageAnnotation, StructParentAwareAnnotation
{
    use HasStructParent;

    public function __construct(
        int $id,
        private readonly Page $page,
        private readonly float $x,
        private readonly float $y,
        private readonly float $width,
        private readonly float $height,
        private readonly FileSpecification $file,
        private readonly string $icon = 'PushPin',
        private readonly ?string $contents = null,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Annot'),
            'Subtype' => new NameType('FileAttachment'),
            'Rect' => new ArrayType([
                $this->x,
                $this->y,
                $this->x + $this->width,
                $this->y + $this->height,
            ]),
            'P' => new ReferenceType($this->page),
            'FS' => new ReferenceType($this->file),
            'Name' => new NameType($this->icon),
        ]);

        $this->addStructParentEntry($dictionary);

        if ($this->contents !== null && $this->contents !== '') {
            $dictionary->add('Contents', new StringType($this->contents));
        }

        return $dictionary;
    }

    public function getRelatedObjects(): array
    {
        return [];
    }
}
