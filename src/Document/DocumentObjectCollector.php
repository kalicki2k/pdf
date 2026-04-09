<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Structure\StructElem;

/**
 * @internal Collects the indirect objects that make up a rendered PDF document.
 */
final readonly class DocumentObjectCollector
{
    /**
     * @param list<StructElem> $structElems
     */
    public function __construct(
        private Document $document,
        private array    $structElems,
    ) {
    }

    /**
     * @return list<IndirectObject>
     */
    public function collect(): array
    {
        $xmpMetadata = $this->document->getXmpMetadata();
        $pdfaOutputIntentProfile = $this->document->getPdfAOutputIntentProfile();

        /** @var list<IndirectObject> $objects */
        $objects = [
            $this->document->catalog,
            $this->document->pages,
        ];

        $this->collectOutlineObjects($objects);
        $this->collectAcroFormObjects($objects);
        $this->collectStructureObjects($objects);
        $this->collectEncryptionObjects($objects, $pdfaOutputIntentProfile);
        $this->collectOptionalContentGroupObjects($objects);
        $this->collectAttachmentObjects($objects);
        $this->collectInfoDictionary($objects);
        $this->collectFontObjects($objects);
        $this->collectPageObjects($objects);
        $this->collectMetadataObjects($objects, $xmpMetadata);
        $this->collectPageContentLengthObjects($objects);

        return $objects;
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectOutlineObjects(array &$objects): void
    {
        if ($this->document->outlineRoot === null) {
            return;
        }

        $objects[] = $this->document->outlineRoot;

        foreach ($this->document->outlineRoot->getItems() as $outlineItem) {
            $objects[] = $outlineItem;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectAcroFormObjects(array &$objects): void
    {
        if ($this->document->acroForm === null) {
            return;
        }

        $objects[] = $this->document->acroForm;

        foreach ($this->document->acroForm->getFieldObjectsForRender() as $fieldObject) {
            $objects[] = $fieldObject;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectStructureObjects(array &$objects): void
    {
        if ($this->document->structTreeRoot !== null) {
            $objects[] = $this->document->structTreeRoot;
        }

        if ($this->document->parentTree !== null) {
            $objects[] = $this->document->parentTree;
        }

        foreach ($this->structElems as $structElem) {
            $objects[] = $structElem;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectEncryptionObjects(array &$objects, ?IccProfileStream $pdfaOutputIntentProfile): void
    {
        if ($this->document->encryptDictionary !== null) {
            $objects[] = $this->document->encryptDictionary;
        }

        if ($pdfaOutputIntentProfile !== null) {
            $objects[] = $pdfaOutputIntentProfile;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectOptionalContentGroupObjects(array &$objects): void
    {
        foreach ($this->document->getOptionalContentGroups() as $optionalContentGroup) {
            $objects[] = $optionalContentGroup;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectAttachmentObjects(array &$objects): void
    {
        foreach ($this->document->getAttachments() as $attachment) {
            $objects[] = $attachment;
            $objects[] = $attachment->getEmbeddedFile();
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectInfoDictionary(array &$objects): void
    {
        if ($this->document->shouldWriteInfoDictionary()) {
            $objects[] = $this->document->info;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectFontObjects(array &$objects): void
    {
        foreach ($this->document->getFonts() as $font) {
            if ($font instanceof UnicodeFont) {
                if ($font->descendantFont->fontDescriptor !== null) {
                    $objects[] = $font->descendantFont->fontDescriptor->fontFile;
                    $objects[] = $font->descendantFont->fontDescriptor;
                }

                if ($font->descendantFont->cidToGidMap !== null) {
                    $objects[] = $font->descendantFont->cidToGidMap;
                }

                $objects[] = $font->descendantFont;
                $objects[] = $font->toUnicode;
            }

            if ($font instanceof StandardFont && $font->encodingDictionary !== null) {
                $objects[] = $font->encodingDictionary;
            }

            $objects[] = $font;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectPageObjects(array &$objects): void
    {
        foreach ($this->document->pages->pages as $page) {
            $objects[] = $page;

            foreach ($page->getAnnotations() as $annotation) {
                $objects[] = $annotation;

                foreach ($annotation->getRelatedObjects() as $relatedObject) {
                    $objects[] = $relatedObject;
                }
            }

            foreach ($page->getResources()->getImages() as $image) {
                $objects[] = $image;
            }

            $objects[] = $page->getResources();
            $objects[] = $page->getContents();
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectMetadataObjects(array &$objects, ?XmpMetadata $xmpMetadata): void
    {
        if ($xmpMetadata !== null) {
            $objects[] = $xmpMetadata;
        }
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function collectPageContentLengthObjects(array &$objects): void
    {
        foreach ($this->document->pages->pages as $page) {
            $objects[] = $page->prepareContentsLengthObject();
        }
    }
}
