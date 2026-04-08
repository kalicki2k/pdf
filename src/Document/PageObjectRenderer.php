<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Annotation\PageAnnotation;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

/**
 * @internal Renders the PDF page object dictionary.
 */
final class PageObjectRenderer
{
    public function __construct(private readonly Page $page)
    {
    }

    public function render(bool $hasMarkedContent): string
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Page'),
            'Parent' => new ReferenceType($this->page->getDocument()->pages),
            'MediaBox' => new ArrayType([0, 0, $this->page->getWidth(), $this->page->getHeight()]),
            'Resources' => new ReferenceType($this->page->resources),
            'Contents' => new ReferenceType($this->page->contents),
        ]);

        if ($hasMarkedContent && $this->page->getDocument()->hasStructure()) {
            $dictionary->add('StructParents', $this->page->structParentId);
        }

        $annotations = $this->page->getAnnotations();

        if ($annotations !== []) {
            $dictionary->add('Annots', $this->renderAnnotations($annotations));

            if ($this->page->getDocument()->getProfile()->requiresPageAnnotationTabOrder()) {
                $dictionary->add('Tabs', new NameType('S'));
            }
        }

        return $this->page->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    /**
     * @param list<IndirectObject&PageAnnotation> $annotations
     */
    private function renderAnnotations(array $annotations): ArrayType
    {
        return new ArrayType(array_map(
            static fn (IndirectObject $annotation): ReferenceType => new ReferenceType($annotation),
            $annotations,
        ));
    }
}
