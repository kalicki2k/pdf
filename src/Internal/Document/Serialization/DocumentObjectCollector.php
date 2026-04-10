<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Serialization;

use IteratorAggregate;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Document\Metadata\IccProfileStream;
use Kalle\Pdf\Internal\Document\Metadata\XmpMetadata;
use Kalle\Pdf\Internal\Font\StandardFont;
use Kalle\Pdf\Internal\Font\UnicodeFont;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;
use Traversable;

/**
 * @internal Collects the indirect objects that make up a rendered PDF document.
 * @implements IteratorAggregate<int, IndirectObject>
 */
class DocumentObjectCollector implements IteratorAggregate
{
    /**
     * @param list<StructElem> $structElems
     */
    public function __construct(
        private Document $document,
        private array $structElems,
    ) {
    }

    /**
     * @return list<IndirectObject>
     */
    public function collect(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * @return Traversable<int, IndirectObject>
     */
    public function getIterator(): Traversable
    {
        $xmpMetadata = $this->document->getXmpMetadata();
        $pdfaOutputIntentProfile = $this->document->getPdfAOutputIntentProfile();

        yield $this->document->catalog;
        yield $this->document->pages;
        yield from $this->outlineObjects();
        yield from $this->acroFormObjects();
        yield from $this->structureObjects();
        yield from $this->encryptionObjects($pdfaOutputIntentProfile);
        yield from $this->optionalContentGroupObjects();
        yield from $this->attachmentObjects();
        yield from $this->infoDictionaryObjects();
        yield from $this->fontObjects();
        yield from $this->pageObjects();
        yield from $this->metadataObjects($xmpMetadata);
        yield from $this->pageContentLengthObjects();
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function outlineObjects(): iterable
    {
        if ($this->document->outlineRoot === null) {
            return;
        }

        yield $this->document->outlineRoot;

        foreach ($this->document->outlineRoot->getItems() as $outlineItem) {
            yield $outlineItem;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function acroFormObjects(): iterable
    {
        if ($this->document->acroForm === null) {
            return;
        }

        yield $this->document->acroForm;

        foreach ($this->document->acroForm->getFieldObjectsForRender() as $fieldObject) {
            yield $fieldObject;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function structureObjects(): iterable
    {
        if ($this->document->structTreeRoot !== null) {
            yield $this->document->structTreeRoot;
        }

        if ($this->document->parentTree !== null) {
            yield $this->document->parentTree;
        }

        foreach ($this->structElems as $structElem) {
            yield $structElem;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function encryptionObjects(?IccProfileStream $pdfaOutputIntentProfile): iterable
    {
        if ($this->document->encryptDictionary !== null) {
            yield $this->document->encryptDictionary;
        }

        if ($pdfaOutputIntentProfile !== null) {
            yield $pdfaOutputIntentProfile;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function optionalContentGroupObjects(): iterable
    {
        foreach ($this->document->getOptionalContentGroups() as $optionalContentGroup) {
            yield $optionalContentGroup;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function attachmentObjects(): iterable
    {
        foreach ($this->document->getAttachments() as $attachment) {
            yield $attachment;
            yield $attachment->getEmbeddedFile();
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function infoDictionaryObjects(): iterable
    {
        if ($this->document->shouldWriteInfoDictionary()) {
            yield $this->document->info;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function fontObjects(): iterable
    {
        foreach ($this->document->getFonts() as $font) {
            if ($font instanceof UnicodeFont) {
                if ($font->descendantFont->fontDescriptor !== null) {
                    yield $font->descendantFont->fontDescriptor->fontFile;
                    yield $font->descendantFont->fontDescriptor;
                }

                if ($font->descendantFont->cidToGidMap !== null) {
                    yield $font->descendantFont->cidToGidMap;
                }

                yield $font->descendantFont;
                yield $font->toUnicode;
            }

            if ($font instanceof StandardFont && $font->encodingDictionary !== null) {
                yield $font->encodingDictionary;
            }

            yield $font;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function pageObjects(): iterable
    {
        foreach ($this->document->pages->pages as $page) {
            $page->prepareContentsLengthObject();
            yield $page;

            foreach ($page->getAnnotations() as $annotation) {
                yield $annotation;

                foreach ($annotation->getRelatedObjects() as $relatedObject) {
                    yield $relatedObject;
                }
            }

            foreach ($page->getResources()->getImages() as $image) {
                yield $image;
            }

            yield $page->getResources();
            yield $page->getContents();
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function metadataObjects(?XmpMetadata $xmpMetadata): iterable
    {
        if ($xmpMetadata !== null) {
            yield $xmpMetadata;
        }
    }

    /**
     * @return iterable<int, IndirectObject>
     */
    private function pageContentLengthObjects(): iterable
    {
        foreach ($this->document->pages->pages as $page) {
            yield $page->prepareContentsLengthObject();
        }
    }
}
