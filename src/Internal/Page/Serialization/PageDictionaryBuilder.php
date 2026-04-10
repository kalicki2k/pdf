<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Serialization;

use Kalle\Pdf\Feature\Annotation\PageAnnotation;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;

/**
 * @internal Builds the PDF page object dictionary before serialization.
 */
final class PageDictionaryBuilder
{
    public function build(Page $page, bool $hasMarkedContent): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => new NameType('Page'),
            'Parent' => new ReferenceType($page->getDocument()->pages),
            'MediaBox' => new ArrayType([0, 0, $page->getWidth(), $page->getHeight()]),
            'Resources' => new ReferenceType($page->getResources()),
            'Contents' => new ReferenceType($page->getContents()),
        ]);

        if ($hasMarkedContent && $page->getDocument()->hasStructure()) {
            $dictionary->add('StructParents', $page->structParentId);
        }

        $annotations = $page->getAnnotations();

        if ($annotations !== []) {
            $dictionary->add('Annots', $this->buildAnnotations($annotations));

            if ($page->getDocument()->getProfile()->requiresPageAnnotationTabOrder()) {
                $dictionary->add('Tabs', new NameType('S'));
            }
        }

        return $dictionary;
    }

    /**
     * @param list<IndirectObject&PageAnnotation> $annotations
     */
    private function buildAnnotations(array $annotations): ArrayType
    {
        return new ArrayType(array_map(
            static fn (IndirectObject $annotation): ReferenceType => new ReferenceType($annotation),
            $annotations,
        ));
    }
}
