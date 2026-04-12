<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;

use DateTimeImmutable;

use function implode;

use InvalidArgumentException;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\Metadata\IccProfile;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\Metadata\XmpMetadata;
use Kalle\Pdf\Document\TaggedPdf\ParentTree;
use Kalle\Pdf\Document\TaggedPdf\StructElem;
use Kalle\Pdf\Document\TaggedPdf\StructTreeRoot;
use Kalle\Pdf\Document\TaggedPdf\TaggedTable;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableContentReference;
use Kalle\Pdf\Document\TaggedPdf\TaggedTableRow;
use Kalle\Pdf\Encryption\EncryptDictionaryBuilder;
use Kalle\Pdf\Encryption\EncryptionProfileResolver;
use Kalle\Pdf\Encryption\ObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandler;
use Kalle\Pdf\Font\OpenTypeOutlineType;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileStructure;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Trailer;
use Random\RandomException;

use function sprintf;
use function str_replace;

/**
 * Builds a minimal serialization plan from a prepared document.
 */
final class DocumentSerializationPlanBuilder
{
    public function __construct(
        private readonly EncryptionProfileResolver $encryptionProfileResolver = new EncryptionProfileResolver(),
        private readonly StandardSecurityHandler $standardSecurityHandler = new StandardSecurityHandler(),
        private readonly EncryptDictionaryBuilder $encryptDictionaryBuilder = new EncryptDictionaryBuilder(),
    ) {
    }

    public function build(Document $document): DocumentSerializationPlan
    {
        $this->assertProfileRequirements($document);
        $this->assertImageAccessibilityRequirements($document);
        $this->assertAnnotationRequirements($document);
        $this->assertNamedDestinationRequirements($document);
        $this->assertPdfARequirements($document);
        $serializedAt = new DateTimeImmutable('now');

        $pageObjectIds = [];
        $contentObjectIds = [];
        $nextObjectId = 3;
        /** @var array<string, int> $fontObjectIds */
        $fontObjectIds = [];
        /** @var array<string, int> $fontDescriptorObjectIds */
        $fontDescriptorObjectIds = [];
        /** @var array<string, int> $fontFileObjectIds */
        $fontFileObjectIds = [];
        /** @var array<string, int> $cidFontObjectIds */
        $cidFontObjectIds = [];
        /** @var array<string, int> $toUnicodeObjectIds */
        $toUnicodeObjectIds = [];
        /** @var array<string, int> $cidToGidMapObjectIds */
        $cidToGidMapObjectIds = [];
        /** @var array<string, int> $cidSetObjectIds */
        $cidSetObjectIds = [];
        /** @var array<string, int> $imageObjectIds */
        $imageObjectIds = [];
        /** @var array<int, list<int>> $pageAnnotationObjectIds */
        $pageAnnotationObjectIds = [];
        /** @var array<int, list<?int>> $pageAnnotationAppearanceObjectIds */
        $pageAnnotationAppearanceObjectIds = [];

        foreach ($document->pages as $page) {
            $pageObjectIds[] = $nextObjectId;
            $nextObjectId++;
            $contentObjectIds[] = $nextObjectId;
            $nextObjectId++;
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $pageFont) {
                $fontKey = $this->fontObjectKey($pageFont);

                if (!isset($fontObjectIds[$fontKey])) {
                    $fontObjectIds[$fontKey] = $nextObjectId;
                    $nextObjectId++;

                    if ($pageFont->isEmbedded()) {
                        if ($pageFont->usesUnicodeCids()) {
                            $embeddedFont = $pageFont->embeddedDefinition();
                            $cidFontObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $toUnicodeObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $cidSetObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;

                            if ($embeddedFont->metadata->outlineType === OpenTypeOutlineType::TRUE_TYPE) {
                                $cidToGidMapObjectIds[$fontKey] = $nextObjectId;
                                $nextObjectId++;
                            }
                        } else {
                            $fontDescriptorObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                            $fontFileObjectIds[$fontKey] = $nextObjectId;
                            $nextObjectId++;
                        }
                    }
                }
            }

            foreach ($page->imageResources as $imageSource) {
                $nextObjectId = $this->reserveImageObjectIds($imageSource, $imageObjectIds, $nextObjectId);
            }

            $pageAnnotationObjectIds[$pageIndex] = [];
            $pageAnnotationAppearanceObjectIds[$pageIndex] = [];

            foreach ($page->annotations as $annotation) {
                $pageAnnotationObjectIds[$pageIndex][] = $nextObjectId;
                $nextObjectId++;

                $pageAnnotationAppearanceObjectIds[$pageIndex][] = $this->annotationNeedsAppearanceStream($document, $annotation)
                    ? $nextObjectId++
                    : null;
            }
        }

        $taggedImageStructure = $this->collectTaggedFigureStructure($document);
        $taggedTableStructure = $this->collectTaggedTableStructure($document);
        $taggedPageContentKeys = $this->mergeTaggedPageContentKeys(
            $taggedImageStructure['pageMarkedContentKeys'],
            $taggedTableStructure['pageMarkedContentKeys'],
        );
        $pageStructParentIds = $this->assignPageStructParentIds($taggedPageContentKeys);
        $taggedLinkStructure = $this->collectTaggedLinkStructure($document, count($pageStructParentIds));
        $namedDestinations = $this->collectNamedDestinations($document);
        $structTreeRootObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $documentStructElemObjectId = $document->profile->requiresTaggedPdf() ? $nextObjectId++ : null;
        $parentTreeObjectId = ($taggedPageContentKeys !== [] || $taggedLinkStructure['parentTreeEntries'] !== [])
            ? $nextObjectId++
            : null;
        /** @var array<string, int> $figureStructElemObjectIds */
        $figureStructElemObjectIds = [];
        /** @var array<string, int> $tableStructElemObjectIds */
        $tableStructElemObjectIds = [];
        /** @var array<string, int> $captionStructElemObjectIds */
        $captionStructElemObjectIds = [];
        /** @var array<string, int> $rowStructElemObjectIds */
        $rowStructElemObjectIds = [];
        /** @var array<string, int> $cellStructElemObjectIds */
        $cellStructElemObjectIds = [];
        /** @var array<string, int> $linkStructElemObjectIds */
        $linkStructElemObjectIds = [];

        foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
            $figureStructElemObjectIds[$figureEntry['key']] = $nextObjectId++;
        }

        foreach ($document->taggedTables as $taggedTable) {
            $tableStructElemObjectIds[$this->taggedTableKey($taggedTable->tableId)] = $nextObjectId++;

            if ($taggedTable->hasCaption()) {
                $captionStructElemObjectIds[$this->taggedTableCaptionKey($taggedTable->tableId)] = $nextObjectId++;
            }

            foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                foreach ($rows as $row) {
                    $rowStructElemObjectIds[$this->taggedTableRowKey($taggedTable->tableId, $section, $row->rowIndex)] = $nextObjectId++;

                    foreach ($row->cells as $cell) {
                        $cellStructElemObjectIds[$this->taggedTableCellKey(
                            $taggedTable->tableId,
                            $section,
                            $row->rowIndex,
                            $cell->columnIndex,
                        )] = $nextObjectId++;
                    }
                }
            }
        }

        foreach ($taggedLinkStructure['linkEntries'] as $linkEntry) {
            $linkStructElemObjectIds[$linkEntry['key']] = $nextObjectId++;
        }

        $metadataObjectId = $this->usesMetadataStream($document) ? $nextObjectId++ : null;
        $iccProfileObjectId = $document->profile->usesPdfAOutputIntent() ? $nextObjectId++ : null;
        $infoObjectId = null;
        $encryptObjectId = null;
        $objectEncryptor = null;
        $encryptObjectContents = '';
        $documentId = null;

        if ($document->profile->writesInfoDictionary() && $this->hasInfoMetadata($document)) {
            $infoObjectId = $nextObjectId++;
        }

        if ($document->encryption !== null) {
            $encryptObjectId = $nextObjectId++;
            $documentId = $this->generateDocumentId();
            $encryptionProfile = $this->encryptionProfileResolver->resolve($document->profile, $document->encryption);
            $securityHandlerData = $this->standardSecurityHandler->build(
                $document->encryption,
                $encryptionProfile,
                $documentId,
            );
            $objectEncryptor = new ObjectEncryptor($encryptionProfile, $securityHandlerData);
            $encryptObjectContents = $this->encryptDictionaryBuilder->build($encryptionProfile, $securityHandlerData);
        } elseif ($document->profile->isPdfA()) {
            $documentId = $this->generateDocumentId();
        }

        $objects = [
            IndirectObject::plain(
                1,
                $this->buildCatalogDictionary(
                    $document,
                    $metadataObjectId,
                    $iccProfileObjectId,
                    $structTreeRootObjectId,
                    $namedDestinations,
                ),
            ),
            IndirectObject::plain(
                2,
                '<< /Type /Pages /Count ' . count($pageObjectIds) . ' /Kids [' . $this->buildKidsReferences($pageObjectIds) . '] >>',
            ),
        ];

        foreach ($document->pages as $index => $page) {
            $pageObjectId = $pageObjectIds[$index];
            $contentObjectId = $contentObjectIds[$index];
            $annotationObjectIds = $pageAnnotationObjectIds[$index] ?? [];

            $objects[] = IndirectObject::plain(
                $pageObjectId,
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->formatNumber($page->size->width()) . ' '
                . $this->formatNumber($page->size->height()) . '] /Resources '
                . $this->buildPageResources($page->fontResources, $page->imageResources, $fontObjectIds, $imageObjectIds) . ' /Contents '
                . $contentObjectId . ' 0 R'
                . $this->buildPageAnnotationsEntry($annotationObjectIds)
                . $this->buildAnnotationTabOrderEntry($document, $annotationObjectIds)
                . $this->buildStructParentsEntry($pageStructParentIds[$index] ?? null)
                . ' >>',
            );
            $objects[] = IndirectObject::stream(
                $contentObjectId,
                $this->buildContentStreamDictionary($this->buildPageContents($page)),
                $this->buildContentStreamContents($this->buildPageContents($page)),
            );

            foreach ($page->annotations as $annotationIndex => $annotation) {
                $annotationKey = $index . ':' . $annotationIndex;
                $objects[] = IndirectObject::plain(
                    $annotationObjectIds[$annotationIndex],
                    $annotation->pdfObjectContents(
                        new PageAnnotationRenderContext(
                            pageObjectId: $pageObjectId,
                            printable: $document->profile->requiresPrintableAnnotations(),
                            pageObjectIdsByPageNumber: $this->pageObjectIdsByPageNumber($pageObjectIds),
                            namedDestinations: $namedDestinations,
                            structParentId: $taggedLinkStructure['structParentIds'][$annotationKey] ?? null,
                            appearanceObjectId: $pageAnnotationAppearanceObjectIds[$index][$annotationIndex] ?? null,
                        ),
                    ),
                );

                $appearanceObjectId = $pageAnnotationAppearanceObjectIds[$index][$annotationIndex] ?? null;

                if ($appearanceObjectId !== null && $annotation instanceof LinkAnnotation) {
                    $objects[] = IndirectObject::stream(
                        $appearanceObjectId,
                        $annotation->appearanceStreamDictionaryContents(),
                        $annotation->appearanceStreamContents(),
                    );
                }
            }
        }

        foreach ($this->collectFonts($document->pages) as $fontKey => $pageFont) {
            $fontObjectId = $fontObjectIds[$fontKey];

            if ($pageFont->isEmbedded()) {
                $embeddedFont = $pageFont->embeddedDefinition();
                $fontDescriptorObjectId = $fontDescriptorObjectIds[$fontKey];
                $fontFileObjectId = $fontFileObjectIds[$fontKey];

                if ($pageFont->usesUnicodeCids()) {
                    /** @var list<EmbeddedGlyph> $embeddedGlyphs */
                    $embeddedGlyphs = $pageFont->embeddedGlyphs;
                    $cidFontObjectId = $cidFontObjectIds[$fontKey];
                    $toUnicodeObjectId = $toUnicodeObjectIds[$fontKey];
                    $cidToGidMapObjectId = $cidToGidMapObjectIds[$fontKey] ?? null;
                    $cidSetObjectId = $cidSetObjectIds[$fontKey] ?? null;
                    $subsetFontName = $embeddedFont->unicodeBaseFontNameForGlyphs($embeddedGlyphs);

                    $objects[] = new IndirectObject(
                        $fontObjectId,
                        $embeddedFont->unicodeType0FontObjectContentsForGlyphs($cidFontObjectId, $toUnicodeObjectId, $embeddedGlyphs),
                    );
                    $objects[] = new IndirectObject(
                        $cidFontObjectId,
                        $embeddedFont->unicodeCidFontObjectContentsForGlyphs(
                            $fontDescriptorObjectId,
                            $cidToGidMapObjectId,
                            $embeddedGlyphs,
                        ),
                    );
                    $objects[] = new IndirectObject(
                        $fontDescriptorObjectId,
                        $embeddedFont->fontDescriptorContentsWithCidSet($fontFileObjectId, $subsetFontName, $cidSetObjectId),
                    );
                    $objects[] = IndirectObject::stream(
                        $fontFileObjectId,
                        $embeddedFont->unicodeSubsetFontFileStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                        $embeddedFont->unicodeSubsetFontFileStreamDataForGlyphs($embeddedGlyphs),
                    );
                    $objects[] = IndirectObject::stream(
                        $toUnicodeObjectId,
                        $embeddedFont->unicodeToUnicodeStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                        $embeddedFont->unicodeToUnicodeStreamDataForGlyphs($embeddedGlyphs),
                    );
                    if ($cidToGidMapObjectId !== null) {
                        $objects[] = IndirectObject::stream(
                            $cidToGidMapObjectId,
                            $embeddedFont->unicodeCidToGidMapStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                            $embeddedFont->unicodeCidToGidMapStreamDataForGlyphs($embeddedGlyphs),
                        );
                    }

                    if ($cidSetObjectId !== null) {
                        $objects[] = IndirectObject::stream(
                            $cidSetObjectId,
                            $embeddedFont->unicodeCidSetStreamDictionaryContentsForGlyphs($embeddedGlyphs),
                            $embeddedFont->unicodeCidSetStreamDataForGlyphs($embeddedGlyphs),
                        );
                    }

                    continue;
                }

                $objects[] = new IndirectObject($fontObjectId, $embeddedFont->fontObjectContents($fontDescriptorObjectId));
                $objects[] = new IndirectObject($fontDescriptorObjectId, $embeddedFont->fontDescriptorContents($fontFileObjectId));
                $objects[] = IndirectObject::stream(
                    $fontFileObjectId,
                    $embeddedFont->fontFileStreamDictionaryContents(),
                    $embeddedFont->fontFileStreamData(),
                );

                continue;
            }

            $objects[] = new IndirectObject($fontObjectId, $pageFont->pdfObjectContents());
        }

        foreach ($this->collectImages($document->pages) as $imageKey => $imageSource) {
            $objects[] = IndirectObject::stream(
                $imageObjectIds[$imageKey],
                $imageSource->pdfObjectDictionaryContents(
                    $imageSource->softMask !== null ? $imageObjectIds[$imageSource->softMask->key()] : null,
                ),
                $imageSource->pdfObjectStreamContents(),
            );
        }

        if ($structTreeRootObjectId !== null && $documentStructElemObjectId !== null) {
            $documentKidObjectIds = [];

            foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
                $documentKidObjectIds[] = $figureStructElemObjectIds[$figureEntry['key']];
            }

            foreach ($document->taggedTables as $taggedTable) {
                $documentKidObjectIds[] = $tableStructElemObjectIds[$this->taggedTableKey($taggedTable->tableId)];
            }

            foreach ($taggedLinkStructure['linkEntries'] as $linkEntry) {
                $documentKidObjectIds[] = $linkStructElemObjectIds[$linkEntry['key']];
            }

            $objects[] = new IndirectObject(
                $structTreeRootObjectId,
                (new StructTreeRoot([$documentStructElemObjectId], $parentTreeObjectId))->objectContents(),
            );
            $objects[] = new IndirectObject(
                $documentStructElemObjectId,
                (new StructElem('Document', $structTreeRootObjectId, $documentKidObjectIds))->objectContents(),
            );

            if ($parentTreeObjectId !== null) {
                $parentTreeEntries = [];

                foreach ($pageStructParentIds as $pageIndex => $structParentId) {
                    $pageKeys = $taggedPageContentKeys[$pageIndex] ?? [];

                    if ($pageKeys === []) {
                        continue;
                    }

                    ksort($pageKeys);
                    $parentTreeEntries[$structParentId] = array_map(
                        fn (string $key): int => $this->taggedPageContentObjectId(
                            $key,
                            $figureStructElemObjectIds,
                            $captionStructElemObjectIds,
                            $cellStructElemObjectIds,
                        ),
                        array_values($pageKeys),
                    );
                }

                foreach ($taggedLinkStructure['parentTreeEntries'] as $structParentId => $linkKeys) {
                    $parentTreeEntries[$structParentId] = array_map(
                        static fn (string $key): int => $linkStructElemObjectIds[$key],
                        $linkKeys,
                    );
                }

                $objects[] = new IndirectObject($parentTreeObjectId, (new ParentTree($parentTreeEntries))->objectContents());
            }

            foreach ($taggedImageStructure['figureEntries'] as $figureEntry) {
                $objects[] = new IndirectObject(
                    $figureStructElemObjectIds[$figureEntry['key']],
                    (new StructElem(
                        'Figure',
                        $documentStructElemObjectId,
                        pageObjectId: $pageObjectIds[$figureEntry['pageIndex']],
                        altText: $figureEntry['altText'],
                        markedContentId: $figureEntry['markedContentId'],
                    ))->objectContents(),
                );
            }

            foreach ($document->taggedTables as $taggedTable) {
                $tableStructKey = $this->taggedTableKey($taggedTable->tableId);
                $tableKidObjectIds = [];

                if ($taggedTable->hasCaption()) {
                    $tableKidObjectIds[] = $captionStructElemObjectIds[$this->taggedTableCaptionKey($taggedTable->tableId)];
                }

                foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                    foreach ($rows as $row) {
                        $tableKidObjectIds[] = $rowStructElemObjectIds[$this->taggedTableRowKey(
                            $taggedTable->tableId,
                            $section,
                            $row->rowIndex,
                        )];
                    }
                }

                $objects[] = new IndirectObject(
                    $tableStructElemObjectIds[$tableStructKey],
                    (new StructElem('Table', $documentStructElemObjectId, $tableKidObjectIds))->objectContents(),
                );

                if ($taggedTable->hasCaption()) {
                    $captionKey = $this->taggedTableCaptionKey($taggedTable->tableId);
                    $objects[] = new IndirectObject(
                        $captionStructElemObjectIds[$captionKey],
                        (new StructElem(
                            'Caption',
                            $tableStructElemObjectIds[$tableStructKey],
                            kidEntries: $this->taggedMarkedContentKidEntries($taggedTable->captionReferences, $pageObjectIds),
                        ))->objectContents(),
                    );
                }

                foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                    foreach ($rows as $row) {
                        $rowKey = $this->taggedTableRowKey($taggedTable->tableId, $section, $row->rowIndex);
                        $rowKidObjectIds = [];

                        foreach ($row->cells as $cell) {
                            $rowKidObjectIds[] = $cellStructElemObjectIds[$this->taggedTableCellKey(
                                $taggedTable->tableId,
                                $section,
                                $row->rowIndex,
                                $cell->columnIndex,
                            )];
                        }

                        $objects[] = new IndirectObject(
                            $rowStructElemObjectIds[$rowKey],
                            (new StructElem('TR', $tableStructElemObjectIds[$tableStructKey], $rowKidObjectIds))->objectContents(),
                        );

                        foreach ($row->cells as $cell) {
                            $cellKey = $this->taggedTableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);
                            $objects[] = new IndirectObject(
                                $cellStructElemObjectIds[$cellKey],
                                (new StructElem(
                                    $cell->header ? 'TH' : 'TD',
                                    $rowStructElemObjectIds[$rowKey],
                                    kidEntries: $this->taggedMarkedContentKidEntries($cell->contentReferences, $pageObjectIds),
                                    scope: $cell->headerScope?->value,
                                ))->objectContents(),
                            );
                        }
                    }
                }
            }

            foreach ($taggedLinkStructure['linkEntries'] as $linkEntry) {
                $pageObjectId = $pageObjectIds[$linkEntry['pageIndex']];
                $kidEntries = [];

                foreach ($linkEntry['markedContentIds'] as $markedContentId) {
                    $kidEntries[] = (string) $markedContentId;
                }

                foreach ($linkEntry['annotationIndices'] as $annotationIndex) {
                    $annotationObjectId = $pageAnnotationObjectIds[$linkEntry['pageIndex']][$annotationIndex];
                    $kidEntries[] = '<< /Type /OBJR /Obj ' . $annotationObjectId . ' 0 R /Pg ' . $pageObjectId . ' 0 R >>';
                }

                $objects[] = IndirectObject::plain(
                    $linkStructElemObjectIds[$linkEntry['key']],
                    (new StructElem(
                        'Link',
                        $documentStructElemObjectId,
                        pageObjectId: $pageObjectId,
                        altText: $linkEntry['altText'],
                        kidEntries: $kidEntries,
                    ))->objectContents(),
                );
            }
        }

        if ($metadataObjectId !== null) {
            $xmpMetadata = new XmpMetadata();
            $objects[] = IndirectObject::stream(
                $metadataObjectId,
                $xmpMetadata->streamDictionaryContents($document, $serializedAt),
                $xmpMetadata->streamContents($document, $serializedAt),
            );
        }

        if ($iccProfileObjectId !== null) {
            $outputIntent = $this->resolvePdfAOutputIntent($document);
            $iccProfile = IccProfile::fromPath($outputIntent->iccProfilePath, $outputIntent->colorComponents);
            $objects[] = IndirectObject::stream(
                $iccProfileObjectId,
                $iccProfile->streamDictionaryContents(),
                $iccProfile->streamContents(),
            );
        }

        if ($infoObjectId !== null) {
            $objects[] = IndirectObject::plain($infoObjectId, $this->buildInfoDictionary($document, $serializedAt));
        }

        if ($encryptObjectId !== null) {
            $objects[] = IndirectObject::plain(
                $encryptObjectId,
                $encryptObjectContents,
                false,
            );
        }

        return new DocumentSerializationPlan(
            objects: $objects,
            fileStructure: new FileStructure(
                version: $document->version(),
                trailer: new Trailer(
                    size: count($objects) + 1,
                    rootObjectId: 1,
                    infoObjectId: $infoObjectId,
                    encryptObjectId: $encryptObjectId,
                    documentId: $documentId,
                ),
            ),
            objectEncryptor: $objectEncryptor,
        );
    }

    private function assertProfileRequirements(Document $document): void
    {
        if ($document->profile->requiresDocumentLanguage() && $document->language === null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a document language.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->requiresDocumentTitle() && $document->title === null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a document title.',
                $document->profile->name(),
            ));
        }
    }

    private function assertImageAccessibilityRequirements(Document $document): void
    {
        if (!$document->profile->requiresTaggedImages()) {
            return;
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->images as $imageIndex => $pageImage) {
                $accessibility = $pageImage->accessibility;

                if ($accessibility === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Tagged PDF profiles require accessibility metadata for image %d on page %d.',
                        $imageIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                if (
                    $document->profile->requiresFigureAltText()
                    && $accessibility->requiresFigureTag()
                    && $accessibility->altText === null
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Tagged PDF profiles require alternative text for image %d on page %d.',
                        $imageIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function assertAnnotationRequirements(Document $document): void
    {
        if ($document->profile->requiresAnnotationAppearanceStreams()) {
            foreach ($document->pages as $pageIndex => $page) {
                foreach ($page->annotations as $annotation) {
                    if ($this->annotationNeedsAppearanceStream($document, $annotation)) {
                        continue;
                    }

                    throw new InvalidArgumentException(sprintf(
                        'Profile %s does not allow the current page annotation implementation because annotation appearance streams are required on page %d.',
                        $document->profile->name(),
                        $pageIndex + 1,
                    ));
                }
            }
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                $supportsCurrentAnnotation = $document->profile->supportsCurrentPageAnnotationsImplementation()
                    || ($annotation instanceof LinkAnnotation && $document->profile->requiresTaggedLinkAnnotations());

                if (!$supportsCurrentAnnotation) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s does not support the current page annotation implementation on page %d.',
                        $document->profile->name(),
                        $pageIndex + 1,
                    ));
                }

                if (
                    $annotation instanceof LinkAnnotation
                    && (
                        $document->profile->requiresLinkAnnotationAlternativeDescriptions()
                        || $document->profile->requiresPageAnnotationAlternativeDescriptions()
                    )
                    && ($annotation->contents === null || $annotation->contents === '')
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s requires alternative text for link annotation %d on page %d.',
                        $document->profile->name(),
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function annotationNeedsAppearanceStream(Document $document, object $annotation): bool
    {
        return $document->profile->requiresAnnotationAppearanceStreams()
            && !$document->profile->isPdfA1()
            && $annotation instanceof LinkAnnotation;
    }

    private function assertNamedDestinationRequirements(Document $document): void
    {
        $destinations = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->namedDestinations as $destination) {
                if (isset($destinations[$destination->name])) {
                    throw new InvalidArgumentException(sprintf(
                        'Named destination "%s" is defined more than once. Duplicate found on page %d.',
                        $destination->name,
                        $pageIndex + 1,
                    ));
                }

                $destinations[$destination->name] = true;
            }
        }
    }

    private function hasInfoMetadata(Document $document): bool
    {
        return $document->title !== null
            || $document->author !== null
            || $document->subject !== null
            || $document->creator !== null
            || $document->creatorTool !== null;
    }

    private function usesMetadataStream(Document $document): bool
    {
        if (!$document->profile->supportsXmpMetadata()) {
            return false;
        }

        return $this->hasInfoMetadata($document)
            || $document->language !== null
            || $document->profile->requiresTaggedPdf()
            || $document->profile->writesPdfAIdentificationMetadata()
            || $document->profile->writesPdfUaIdentificationMetadata();
    }

    private function buildInfoDictionary(Document $document, DateTimeImmutable $serializedAt): string
    {
        $entries = [];

        if ($document->title !== null) {
            $entries[] = '/Title ' . $this->pdfString($document->title);
        }

        if ($document->author !== null) {
            $entries[] = '/Author ' . $this->pdfString($document->author);
        }

        if ($document->subject !== null) {
            $entries[] = '/Subject ' . $this->pdfString($document->subject);
        }

        if ($document->creator !== null) {
            $entries[] = '/Creator ' . $this->pdfString($document->creator);
        }

        if ($document->creatorTool !== null) {
            $entries[] = '/Producer ' . $this->pdfString($document->creatorTool);
        }

        $pdfDate = $this->pdfDate($serializedAt);
        $entries[] = '/CreationDate ' . $this->pdfString($pdfDate);
        $entries[] = '/ModDate ' . $this->pdfString($pdfDate);

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @param array<string, string> $namedDestinations
     */
    private function buildCatalogDictionary(
        Document $document,
        ?int $metadataObjectId,
        ?int $iccProfileObjectId,
        ?int $structTreeRootObjectId,
        array $namedDestinations,
    ): string {
        $entries = [
            '/Type /Catalog',
            '/Pages 2 0 R',
        ];

        if ($metadataObjectId !== null) {
            $entries[] = '/Metadata ' . $metadataObjectId . ' 0 R';
        }

        if ($document->language !== null) {
            $entries[] = '/Lang ' . $this->pdfString($document->language);
        }

        if ($document->profile->requiresTaggedPdf()) {
            $entries[] = '/MarkInfo << /Marked true >>';
        }

        if ($iccProfileObjectId !== null) {
            $outputIntent = $this->resolvePdfAOutputIntent($document);
            $entries[] = '/OutputIntents [<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier '
                . $this->pdfString($outputIntent->outputConditionIdentifier)
                . ($outputIntent->info !== null ? ' /Info ' . $this->pdfString($outputIntent->info) : '')
                . ' /DestOutputProfile '
                . $iccProfileObjectId
                . ' 0 R >>]';
        }

        if ($structTreeRootObjectId !== null) {
            $entries[] = '/StructTreeRoot ' . $structTreeRootObjectId . ' 0 R';
        }

        if ($namedDestinations !== []) {
            $destEntries = [];

            foreach ($namedDestinations as $name => $destination) {
                $destEntries[] = '/' . $name . ' ' . $destination;
            }

            $entries[] = '/Dests << ' . implode(' ', $destEntries) . ' >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function buildStructParentsEntry(?int $structParentId): string
    {
        if ($structParentId === null) {
            return '';
        }

        return ' /StructParents ' . $structParentId;
    }

    /**
     * @param list<int> $annotationObjectIds
     */
    private function buildPageAnnotationsEntry(array $annotationObjectIds): string
    {
        if ($annotationObjectIds === []) {
            return '';
        }

        return ' /Annots [' . implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $annotationObjectIds,
        )) . ']';
    }

    /**
     * @param list<int> $annotationObjectIds
     */
    private function buildAnnotationTabOrderEntry(Document $document, array $annotationObjectIds): string
    {
        if ($annotationObjectIds === [] || !$document->profile->requiresPageAnnotationTabOrder()) {
            return '';
        }

        return ' /Tabs /S';
    }

    /**
     * @return array{
     *   figureEntries: list<array{key: string, pageIndex: int, markedContentId: int, altText: ?string}>,
     *   pageMarkedContentKeys: array<int, array<int, string>>
     * }
     */
    private function collectTaggedFigureStructure(Document $document): array
    {
        $figureEntries = [];
        $pageMarkedContentKeys = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->images as $imageIndex => $pageImage) {
                if ($pageImage->markedContentId === null) {
                    continue;
                }

                $key = 'figure:' . $pageIndex . ':' . $imageIndex;
                $pageMarkedContentKeys[$pageIndex][$pageImage->markedContentId] = $key;
                $figureEntries[] = [
                    'key' => $key,
                    'pageIndex' => $pageIndex,
                    'markedContentId' => $pageImage->markedContentId,
                    'altText' => $pageImage->accessibility?->altText,
                ];
            }
        }

        return [
            'figureEntries' => $figureEntries,
            'pageMarkedContentKeys' => $pageMarkedContentKeys,
        ];
    }

    /**
     * @return array{
     *   pageMarkedContentKeys: array<int, array<int, string>>
     * }
     */
    private function collectTaggedTableStructure(Document $document): array
    {
        $pageMarkedContentKeys = [];

        foreach ($document->taggedTables as $taggedTable) {
            foreach ($taggedTable->captionReferences as $reference) {
                $pageMarkedContentKeys[$reference->pageIndex][$reference->markedContentId] = $this->taggedTableCaptionKey($taggedTable->tableId);
            }

            foreach ($this->taggedTableSections($taggedTable) as $section => $rows) {
                foreach ($rows as $row) {
                    foreach ($row->cells as $cell) {
                        $cellKey = $this->taggedTableCellKey($taggedTable->tableId, $section, $row->rowIndex, $cell->columnIndex);

                        foreach ($cell->contentReferences as $reference) {
                            $pageMarkedContentKeys[$reference->pageIndex][$reference->markedContentId] = $cellKey;
                        }
                    }
                }
            }
        }

        return [
            'pageMarkedContentKeys' => $pageMarkedContentKeys,
        ];
    }

    /**
     * @param array<int, array<int, string>> $left
     * @param array<int, array<int, string>> $right
     * @return array<int, array<int, string>>
     */
    private function mergeTaggedPageContentKeys(array $left, array $right): array
    {
        $merged = $left;

        foreach ($right as $pageIndex => $pageKeys) {
            foreach ($pageKeys as $markedContentId => $key) {
                if (isset($merged[$pageIndex][$markedContentId])) {
                    throw new InvalidArgumentException(sprintf(
                        'Duplicate marked-content id %d on page %d.',
                        $markedContentId,
                        $pageIndex + 1,
                    ));
                }

                $merged[$pageIndex][$markedContentId] = $key;
            }
        }

        return $merged;
    }

    /**
     * @param array<int, array<int, string>> $pageMarkedContentKeys
     * @return array<int, int>
     */
    private function assignPageStructParentIds(array $pageMarkedContentKeys): array
    {
        $pageStructParentIds = [];
        $nextStructParentId = 0;
        ksort($pageMarkedContentKeys);

        foreach ($pageMarkedContentKeys as $pageIndex => $pageKeys) {
            if ($pageKeys === []) {
                continue;
            }

            $pageStructParentIds[$pageIndex] = $nextStructParentId;
            $nextStructParentId++;
        }

        return $pageStructParentIds;
    }

    /**
     * @return array{
     *   linkEntries: list<array{
     *     key: string,
     *     pageIndex: int,
     *     annotationIndices: list<int>,
     *     altText: string,
     *     markedContentIds: list<int>
     *   }>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<string, int>
     * }
     */
    private function collectTaggedLinkStructure(Document $document, int $nextStructParentId): array
    {
        if (!$document->profile->requiresTaggedLinkAnnotations()) {
            return [
                'linkEntries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
            ];
        }

        /** @var array<string, array{
         *   key: string,
         *   pageIndex: int,
         *   annotationIndices: list<int>,
         *   altTextParts: list<string>,
         *   markedContentIds: list<int>
         * }> $groupedLinkEntries
         */
        $groupedLinkEntries = [];
        $parentTreeEntries = [];
        $structParentIds = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                if (!$annotation instanceof LinkAnnotation) {
                    continue;
                }

                $annotationKey = $pageIndex . ':' . $annotationIndex;
                $groupKey = $annotation->taggedGroupKey ?? $annotationKey;

                if (!isset($groupedLinkEntries[$groupKey])) {
                    $groupedLinkEntries[$groupKey] = [
                        'key' => $groupKey,
                        'pageIndex' => $pageIndex,
                        'annotationIndices' => [],
                        'altTextParts' => [],
                        'markedContentIds' => [],
                    ];
                }

                $groupedLinkEntries[$groupKey]['annotationIndices'][] = $annotationIndex;

                if ($annotation->contents !== null && $annotation->contents !== '') {
                    $groupedLinkEntries[$groupKey]['altTextParts'][] = $annotation->contents;
                }

                if ($annotation->markedContentId() !== null) {
                    $groupedLinkEntries[$groupKey]['markedContentIds'][] = $annotation->markedContentId();
                }

                $structParentIds[$annotationKey] = $nextStructParentId;
                $parentTreeEntries[$nextStructParentId] = [$groupKey];
                $nextStructParentId++;
            }
        }

        $linkEntries = array_map(
            fn (array $entry): array => [
                'key' => $entry['key'],
                'pageIndex' => $entry['pageIndex'],
                'annotationIndices' => $entry['annotationIndices'],
                'altText' => $this->joinTaggedLinkAltText($entry['altTextParts']),
                'markedContentIds' => $entry['markedContentIds'],
            ],
            array_values($groupedLinkEntries),
        );

        return [
            'linkEntries' => $linkEntries,
            'parentTreeEntries' => $parentTreeEntries,
            'structParentIds' => $structParentIds,
        ];
    }

    /**
     * @param list<string> $parts
     */
    private function joinTaggedLinkAltText(array $parts): string
    {
        $altText = '';

        foreach ($parts as $part) {
            if ($altText !== '' && $this->shouldInsertWhitespaceBetweenLinkAltTextParts($altText, $part)) {
                $altText .= ' ';
            }

            $altText .= $part;
        }

        return $altText;
    }

    private function shouldInsertWhitespaceBetweenLinkAltTextParts(string $left, string $right): bool
    {
        return preg_match('/[\pL\pN]$/u', $left) === 1
            && preg_match('/^[\pL\pN]/u', $right) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function collectNamedDestinations(Document $document): array
    {
        $destinations = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->namedDestinations as $destination) {
                $pageObjectId = 3 + ($pageIndex * 2);

                $destinations[$this->pdfName($destination->name)] = $destination->isFit()
                    ? '[' . $pageObjectId . ' 0 R /Fit]'
                    : '[' . $pageObjectId . ' 0 R /XYZ '
                        . $this->formatNumber($destination->x ?? 0.0)
                        . ' '
                        . $this->formatNumber($destination->y ?? 0.0)
                        . ' null]';
            }
        }

        return $destinations;
    }

    /**
     * @return array<string, list<TaggedTableRow>>
     */
    private function taggedTableSections(TaggedTable $taggedTable): array
    {
        return [
            'header' => $taggedTable->headerRows,
            'body' => $taggedTable->bodyRows,
            'footer' => $taggedTable->footerRows,
        ];
    }

    private function taggedTableKey(int $tableId): string
    {
        return 'table:' . $tableId;
    }

    private function taggedTableCaptionKey(int $tableId): string
    {
        return 'table:' . $tableId . ':caption';
    }

    private function taggedTableRowKey(int $tableId, string $section, int $rowIndex): string
    {
        return 'table:' . $tableId . ':' . $section . ':row:' . $rowIndex;
    }

    private function taggedTableCellKey(int $tableId, string $section, int $rowIndex, int $columnIndex): string
    {
        return 'table:' . $tableId . ':' . $section . ':cell:' . $rowIndex . ':' . $columnIndex;
    }

    /**
     * @param array<string, int> $figureStructElemObjectIds
     * @param array<string, int> $captionStructElemObjectIds
     * @param array<string, int> $cellStructElemObjectIds
     */
    private function taggedPageContentObjectId(
        string $key,
        array $figureStructElemObjectIds,
        array $captionStructElemObjectIds,
        array $cellStructElemObjectIds,
    ): int {
        return $figureStructElemObjectIds[$key]
            ?? $captionStructElemObjectIds[$key]
            ?? $cellStructElemObjectIds[$key]
            ?? throw new InvalidArgumentException("Unknown tagged page content key '$key'.");
    }

    /**
     * @param list<TaggedTableContentReference> $references
     * @param list<int> $pageObjectIds
     * @return list<string>
     */
    private function taggedMarkedContentKidEntries(array $references, array $pageObjectIds): array
    {
        return array_map(
            static fn (TaggedTableContentReference $reference): string => '<< /Type /MCR /Pg '
                . $pageObjectIds[$reference->pageIndex]
                . ' 0 R /MCID '
                . $reference->markedContentId
                . ' >>',
            $references,
        );
    }

    /**
     * @param list<int> $pageObjectIds
     * @return array<int, int>
     */
    private function pageObjectIdsByPageNumber(array $pageObjectIds): array
    {
        $mapping = [];

        foreach ($pageObjectIds as $index => $pageObjectId) {
            $mapping[$index + 1] = $pageObjectId;
        }

        return $mapping;
    }

    private function resolvePdfAOutputIntent(Document $document): PdfAOutputIntent
    {
        return $document->pdfaOutputIntent ?? PdfAOutputIntent::defaultSrgb();
    }

    private function assertPdfARequirements(Document $document): void
    {
        if (!$document->profile->isPdfA()) {
            return;
        }

        if ($document->encryption !== null) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow encryption.',
                $document->profile->name(),
            ));
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $fontIndex => $pageFont) {
                if (!$pageFont->isEmbedded()) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s requires embedded fonts. Found standard font "%s" on page %d.',
                        $document->profile->name(),
                        $pageFont->name,
                        $pageIndex + 1,
                    ));
                }

                if (!$document->profile->requiresEmbeddedUnicodeFonts() || $pageFont->usesUnicodeCids()) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires embedded Unicode fonts. Found simple embedded font "%s" on page %d.',
                    $document->profile->name(),
                    $pageFont->name,
                    $pageIndex + 1,
                ));
            }

            $imageResourceIndex = 0;

            foreach ($page->imageResources as $imageSource) {
                $imageResourceIndex++;

                if ($document->profile->isPdfA1() && $imageSource->colorSpaceDefinition !== null) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s does not allow custom image color space definitions in the current implementation for image resource %d on page %d.',
                        $document->profile->name(),
                        $imageResourceIndex,
                        $pageIndex + 1,
                    ));
                }

                if ($imageSource->softMask === null || $document->profile->supportsCurrentTransparencyImplementation()) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow soft-mask image transparency for image resource %d on page %d.',
                    $document->profile->name(),
                    $imageResourceIndex,
                    $pageIndex + 1,
                ));
            }
        }
    }

    private function pdfDate(DateTimeImmutable $timestamp): string
    {
        $offset = $timestamp->format('O');

        if ($offset === '+0000') {
            return 'D:' . $timestamp->format('YmdHis') . 'Z';
        }

        return 'D:' . $timestamp->format('YmdHis')
            . substr($offset, 0, 3)
            . "'"
            . substr($offset, 3, 2)
            . "'";
    }

    private function generateDocumentId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (RandomException) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }

    /**
     * @param list<int> $pageObjectIds
     */
    private function buildKidsReferences(array $pageObjectIds): string
    {
        if ($pageObjectIds === []) {
            return '';
        }

        return implode(' ', array_map(
            static fn (int $objectId): string => $objectId . ' 0 R',
            $pageObjectIds,
        ));
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }

    private function buildContentStreamDictionary(string $contents): string
    {
        $normalizedContents = $contents;

        if ($normalizedContents !== '' && !str_ends_with($normalizedContents, "\n")) {
            $normalizedContents .= "\n";
        }

        return '<< /Length ' . strlen($normalizedContents) . ' >>';
    }

    private function buildContentStreamContents(string $contents): string
    {
        if ($contents !== '' && !str_ends_with($contents, "\n")) {
            return $contents . "\n";
        }

        return $contents;
    }

    private function buildPageContents(Page $page): string
    {
        $contents = $page->contents;

        if ($page->backgroundColor === null) {
            return $contents;
        }

        $backgroundContents = $this->buildBackgroundContents($page);

        if ($contents === '') {
            return $backgroundContents;
        }

        return $backgroundContents . "\n" . $contents;
    }

    private function buildBackgroundContents(Page $page): string
    {
        $color = $page->backgroundColor;

        if ($color === null) {
            return '';
        }

        return implode("\n", [
            'q',
            $this->buildFillColorOperator($color),
            '0 0 ' . $this->formatNumber($page->size->width()) . ' ' . $this->formatNumber($page->size->height()) . ' re',
            'f',
            'Q',
        ]);
    }

    private function buildFillColorOperator(Color $color): string
    {
        $components = array_map(
            fn (float $value): string => $this->formatNumber($value),
            $color->components(),
        );

        return match ($color->space) {
            ColorSpace::GRAY => implode(' ', $components) . ' g',
            ColorSpace::RGB => implode(' ', $components) . ' rg',
            ColorSpace::CMYK => implode(' ', $components) . ' k',
        };
    }

    /**
     * @param array<string, PageFont> $fontResources
     * @param array<string, ImageSource> $imageResources
     * @param array<string, int> $fontObjectIds
     * @param array<string, int> $imageObjectIds
     */
    private function buildPageResources(array $fontResources, array $imageResources, array $fontObjectIds, array $imageObjectIds): string
    {
        if ($fontResources === [] && $imageResources === []) {
            return '<< >>';
        }

        $entries = [];

        foreach ($fontResources as $fontAlias => $pageFont) {
            $entries[] = '/' . $fontAlias . ' ' . $fontObjectIds[$this->fontObjectKey($pageFont)] . ' 0 R';
        }

        $resourceEntries = [];

        if ($entries !== []) {
            $resourceEntries[] = '/Font << ' . implode(' ', $entries) . ' >>';
        }

        $imageEntries = [];

        foreach ($imageResources as $imageAlias => $imageSource) {
            $imageEntries[] = '/' . $imageAlias . ' ' . $imageObjectIds[$imageSource->key()] . ' 0 R';
        }

        if ($imageEntries !== []) {
            $resourceEntries[] = '/XObject << ' . implode(' ', $imageEntries) . ' >>';
        }

        return '<< ' . implode(' ', $resourceEntries) . ' >>';
    }

    private function fontObjectKey(PageFont $pageFont): string
    {
        return $pageFont->key();
    }

    /**
     * @param list<Page> $pages
     * @return array<string, PageFont>
     */
    private function collectFonts(array $pages): array
    {
        $fonts = [];

        foreach ($pages as $page) {
            foreach ($page->fontResources as $pageFont) {
                $fontKey = $this->fontObjectKey($pageFont);

                if (!isset($fonts[$fontKey])) {
                    $fonts[$fontKey] = $pageFont;

                    continue;
                }

                if ($pageFont->isEmbedded() && $pageFont->usesUnicodeCids()) {
                    /** @var list<EmbeddedGlyph> $embeddedGlyphs */
                    $embeddedGlyphs = $pageFont->embeddedGlyphs;
                    $fonts[$fontKey] = $fonts[$fontKey]->withAdditionalEmbeddedGlyphs($embeddedGlyphs);
                }
            }
        }

        return $fonts;
    }

    /**
     * @param array<string, int> $imageObjectIds
     */
    private function reserveImageObjectIds(ImageSource $imageSource, array &$imageObjectIds, int $nextObjectId): int
    {
        $imageKey = $imageSource->key();

        if (!isset($imageObjectIds[$imageKey])) {
            $imageObjectIds[$imageKey] = $nextObjectId;
            $nextObjectId++;
        }

        if ($imageSource->softMask !== null) {
            $nextObjectId = $this->reserveImageObjectIds($imageSource->softMask, $imageObjectIds, $nextObjectId);
        }

        return $nextObjectId;
    }

    /**
     * @param list<Page> $pages
     * @return array<string, ImageSource>
     */
    private function collectImages(array $pages): array
    {
        $images = [];

        foreach ($pages as $page) {
            foreach ($page->imageResources as $imageSource) {
                $this->collectImageSource($imageSource, $images);
            }
        }

        return $images;
    }

    /**
     * @param array<string, ImageSource> $images
     */
    private function collectImageSource(ImageSource $imageSource, array &$images): void
    {
        $imageKey = $imageSource->key();

        if (!isset($images[$imageKey])) {
            $images[$imageKey] = $imageSource;
        }

        if ($imageSource->softMask !== null) {
            $this->collectImageSource($imageSource->softMask, $images);
        }
    }
}
