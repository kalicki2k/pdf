<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function preg_match;
use function sprintf;

use DateTimeImmutable;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureCollector;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;

final readonly class DocumentSerializationPlanValidator
{
    public function __construct(
        private TaggedStructureCollector $taggedStructureCollector = new TaggedStructureCollector(),
        private DocumentAttachmentRelationshipResolver $attachmentRelationshipResolver = new DocumentAttachmentRelationshipResolver(),
        private PdfAColorPolicyValidator $pdfAColorPolicyValidator = new PdfAColorPolicyValidator(),
        private PdfALowLevelPolicyValidator $pdfALowLevelPolicyValidator = new PdfALowLevelPolicyValidator(),
        private PdfA1aSupportedStructureValidator $pdfA1aSupportedStructureValidator = new PdfA1aSupportedStructureValidator(),
        private PdfA1aPageAnnotationPolicy $pdfA1aPageAnnotationPolicy = new PdfA1aPageAnnotationPolicy(),
        private PdfA1aFormFieldPolicy $pdfA1aFormFieldPolicy = new PdfA1aFormFieldPolicy(),
        private PdfA1AnnotationPolicy $pdfA1AnnotationPolicy = new PdfA1AnnotationPolicy(),
        private PdfA1PolicyEnforcer $pdfA1PolicyEnforcer = new PdfA1PolicyEnforcer(),
        private PdfAAnnotationAppearancePolicy $pdfAAnnotationAppearancePolicy = new PdfAAnnotationAppearancePolicy(),
        private PdfA23ScopePolicy $pdfA23ScopePolicy = new PdfA23ScopePolicy(),
        private PdfA4ScopePolicy $pdfA4ScopePolicy = new PdfA4ScopePolicy(),
    ) {
    }

    public function assertBuildable(Document $document, ?DateTimeImmutable $serializedAt = null): void
    {
        $this->assertProfileRequirements($document);
        $this->pdfA1aSupportedStructureValidator->assertSupported($document);
        $this->assertTaggedStructureRequirements($document);
        $this->assertAttachmentRequirements($document);
        $this->assertAcroFormRequirements($document);
        $this->assertImageAccessibilityRequirements($document);
        $this->assertAnnotationRequirements($document);
        $this->assertNamedDestinationRequirements($document);
        $this->assertOutlineRequirements($document);
        $this->assertPdfARequirements($document, $serializedAt);
    }

    private function assertProfileRequirements(Document $document): void
    {
        $this->pdfA4ScopePolicy->assertProfileSelectionAllowed($document);
        $document->profile->pdfaSupport()?->assertSupported();

        if ($document->profile->requiresDocumentLanguage() && $document->language === null) {
            throw new DocumentValidationException(DocumentBuildError::DOCUMENT_LANGUAGE_REQUIRED, sprintf(
                'Profile %s requires a document language.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->requiresDocumentTitle() && $document->title === null) {
            throw new DocumentValidationException(DocumentBuildError::DOCUMENT_TITLE_REQUIRED, sprintf(
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
                    throw new DocumentValidationException(DocumentBuildError::PDFA_IMAGE_ACCESSIBILITY_REQUIRED, sprintf(
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
                    throw new DocumentValidationException(DocumentBuildError::IMAGE_ALT_TEXT_REQUIRED, sprintf(
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
        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                $supportsCurrentAnnotation = (
                    !$document->profile->requiresTaggedPageAnnotations()
                        && $document->profile->supportsCurrentPageAnnotationsImplementation()
                )
                    || ($annotation instanceof LinkAnnotation && $document->profile->requiresTaggedLinkAnnotations())
                    || (
                        $document->profile->requiresTaggedPageAnnotations()
                        && (
                            ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A' && $this->pdfA1aPageAnnotationPolicy->supports($annotation))
                            || $annotation instanceof PdfUaTaggedPageAnnotation
                        )
                    );

                if (
                    !$supportsCurrentAnnotation
                    || ($document->profile->isPdfA1() && !$this->pdfA1AnnotationPolicy->supports($document, $annotation))
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ANNOTATION_NOT_ALLOWED, sprintf(
                        'Profile %s does not support the current page annotation implementation on page %d.',
                        $document->profile->name(),
                        $pageIndex + 1,
                    ));
                }

                $this->pdfA23ScopePolicy->assertPageAnnotationAllowed($document, $annotation, $pageIndex, $annotationIndex);

                if (
                    $document->profile->requiresAnnotationAppearanceStreams()
                    && !$this->pdfAAnnotationAppearancePolicy->requiresAppearanceStream($document, $annotation)
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ANNOTATION_APPEARANCE_REQUIRED, sprintf(
                        'Profile %s does not allow the current page annotation implementation because annotation appearance streams are required on page %d.',
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
                    && (($annotation->accessibleLabelOrContents() ?? '') === '')
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ANNOTATION_ALT_TEXT_REQUIRED, sprintf(
                        'Profile %s requires alternative text for link annotation %d on page %d.',
                        $document->profile->name(),
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }

                if (
                    !$annotation instanceof LinkAnnotation
                    && $document->profile->requiresPageAnnotationAlternativeDescriptions()
                    && (($this->pageAnnotationAltText($document, $annotation) ?? '') === '')
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ANNOTATION_ALT_TEXT_REQUIRED, sprintf(
                        'Profile %s requires alternative text for page annotation %d on page %d.',
                        $document->profile->name(),
                        $annotationIndex + 1,
                        $pageIndex + 1,
                    ));
                }
            }
        }
    }

    private function assertNamedDestinationRequirements(Document $document): void
    {
        $destinations = [];

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->namedDestinations as $destination) {
                if (isset($destinations[$destination->name])) {
                    throw new DocumentValidationException(DocumentBuildError::DUPLICATE_NAMED_DESTINATION, sprintf(
                        'Named destination "%s" is defined more than once. Duplicate found on page %d.',
                        $destination->name,
                        $pageIndex + 1,
                    ));
                }

                $destinations[$destination->name] = true;
            }
        }
    }

    private function assertAttachmentRequirements(Document $document): void
    {
        if ($document->attachments === []) {
            return;
        }

        $filenames = [];

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if (isset($filenames[$attachment->filename])) {
                throw new DocumentValidationException(DocumentBuildError::DUPLICATE_ATTACHMENT_FILENAME, sprintf(
                    'Attachment filename "%s" is used more than once. Duplicate found at attachment %d.',
                    $attachment->filename,
                    $attachmentIndex + 1,
                ));
            }

            $filenames[$attachment->filename] = true;

            $this->pdfA23ScopePolicy->assertDocumentAttachmentAllowed(
                $document,
                $attachmentIndex,
                $this->attachmentRelationshipResolver->resolve($document, $attachment) !== null,
            );
        }

        if (!$document->profile->supportsDocumentEmbeddedFileAttachments()) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_EMBEDDED_ATTACHMENTS_NOT_ALLOWED, sprintf(
                'Profile %s does not allow embedded file attachments.',
                $document->profile->name(),
            ));
        }

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if ($document->profile->isPdfA() && $attachment->embeddedFile->mimeType === null) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ATTACHMENT_MIME_TYPE_REQUIRED, sprintf(
                    'Profile %s requires an embedded file MIME type for attachment %d.',
                    $document->profile->name(),
                    $attachmentIndex + 1,
                ));
            }

            if (
                $this->attachmentRelationshipResolver->resolve($document, $attachment) !== null
                && !$document->profile->supportsDocumentAssociatedFiles()
            ) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ASSOCIATED_FILES_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow document-level associated files for attachment %d.',
                    $document->profile->name(),
                    $attachmentIndex + 1,
                ));
            }
        }
    }

    private function assertOutlineRequirements(Document $document): void
    {
        $pageCount = count($document->pages);
        $previousLevel = null;
        $namedDestinations = [];

        foreach ($document->pages as $page) {
            foreach ($page->namedDestinations as $destination) {
                $namedDestinations[$destination->name] = true;
            }
        }

        foreach ($document->outlines as $outlineIndex => $outline) {
            if ($outline->destination->isNamed() && !$outline->destination->isRemote()) {
                if (!isset($namedDestinations[$outline->destination->namedDestination ?? ''])) {
                    throw new DocumentValidationException(DocumentBuildError::OUTLINE_REFERENCE_INVALID, sprintf(
                        'Outline %d references unknown named destination "%s".',
                        $outlineIndex + 1,
                        $outline->destination->namedDestination ?? '',
                    ));
                }
            } elseif (!$outline->destination->isRemote() && $outline->pageNumber > $pageCount) {
                throw new DocumentValidationException(DocumentBuildError::OUTLINE_REFERENCE_INVALID, sprintf(
                    'Outline %d references page %d, but the document only has %d page(s).',
                    $outlineIndex + 1,
                    $outline->pageNumber,
                    $pageCount,
                ));
            }

            if ($outlineIndex === 0 && $outline->level !== 1) {
                throw new DocumentValidationException(
                    DocumentBuildError::OUTLINE_HIERARCHY_INVALID,
                    'The first outline must use level 1.',
                );
            }

            if ($previousLevel !== null && $outline->level > ($previousLevel + 1)) {
                throw new DocumentValidationException(DocumentBuildError::OUTLINE_HIERARCHY_INVALID, sprintf(
                    'Outline %d uses level %d, but outline nesting may only increase by one level at a time.',
                    $outlineIndex + 1,
                    $outline->level,
                ));
            }

            $previousLevel = $outline->level;
        }
    }

    private function assertTaggedStructureRequirements(Document $document): void
    {
        if (!$document->profile->isPdfA() || $document->profile->pdfaConformance() !== 'A') {
            return;
        }

        $taggedStructure = $this->taggedStructureCollector->collect($document);

        if (!$taggedStructure->hasStructuredContent() && !$this->documentHasTaggedPdfANonMarkedContent($document)) {
            throw new DocumentValidationException(DocumentBuildError::TAGGED_PDF_REQUIRED, sprintf(
                'Profile %s requires structured content in the current implementation.',
                $document->profile->name(),
            ));
        }

        foreach ($document->pages as $pageIndex => $page) {
            if (
                !$this->pageContainsRenderableText($page)
                || $taggedStructure->hasMarkedContentOnPage($pageIndex)
            ) {
                continue;
            }

            throw new DocumentValidationException(DocumentBuildError::TAGGED_PDF_REQUIRED, sprintf(
                'Profile %s requires structured marked content on page %d when text resources are present.',
                $document->profile->name(),
                $pageIndex + 1,
            ));
        }
    }

    private function pageContainsRenderableText(Page $page): bool
    {
        if ($page->fontResources !== []) {
            return true;
        }

        return preg_match('/(?:^|\\s)BT(?:\\s|$)/', $page->contents) === 1;
    }

    private function documentHasTaggedPdfANonMarkedContent(Document $document): bool
    {
        foreach ($document->pages as $page) {
            foreach ($page->annotations as $annotation) {
                if (
                    !$annotation instanceof LinkAnnotation
                    && $this->supportsTaggedPageAnnotation($document, $annotation)
                ) {
                    return true;
                }
            }
        }

        if ($document->acroForm === null || !$document->profile->requiresTaggedFormFields()) {
            return false;
        }

        return true;
    }

    private function supportsTaggedPageAnnotation(Document $document, object $annotation): bool
    {
        if (!$document->profile->requiresTaggedPageAnnotations()) {
            return false;
        }

        if ($annotation instanceof LinkAnnotation) {
            return false;
        }

        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A') {
            return $annotation instanceof PageAnnotation
                && $this->pdfA1aPageAnnotationPolicy->supports($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation;
    }

    private function pageAnnotationAltText(Document $document, object $annotation): ?string
    {
        if ($document->profile->isPdfA1() && $document->profile->pdfaConformance() === 'A' && $annotation instanceof PageAnnotation) {
            return $this->pdfA1aPageAnnotationPolicy->altText($annotation);
        }

        return $annotation instanceof PdfUaTaggedPageAnnotation
            ? $annotation->taggedAnnotationAltText()
            : null;
    }

    private function assertAcroFormRequirements(Document $document): void
    {
        $this->pdfA23ScopePolicy->assertAcroFormAllowed($document);

        if ($document->acroForm === null) {
            return;
        }

        if (!$document->profile->supportsAcroForms() && !$document->profile->requiresTaggedFormFields()) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->requiresFormFieldAlternativeDescriptions()) {
            foreach ($document->acroForm->fields as $field) {
                if ($field instanceof RadioButtonGroup) {
                    if (($field->alternativeName ?? '') === '') {
                        throw new DocumentValidationException(DocumentBuildError::PDFA_FORM_ALT_TEXT_REQUIRED, sprintf(
                            'Profile %s requires an alternative description for radio button group "%s".',
                            $document->profile->name(),
                            $field->name,
                        ));
                    }

                    foreach ($field->choices as $choiceIndex => $choice) {
                        if (($choice->alternativeName ?? '') !== '') {
                            continue;
                        }

                        throw new DocumentValidationException(DocumentBuildError::PDFA_FORM_ALT_TEXT_REQUIRED, sprintf(
                            'Profile %s requires an alternative description for radio button choice %d in group "%s".',
                            $document->profile->name(),
                            $choiceIndex + 1,
                            $field->name,
                        ));
                    }

                    continue;
                }

                if (($field->alternativeName ?? '') !== '') {
                    continue;
                }

                throw new DocumentValidationException(DocumentBuildError::PDFA_FORM_ALT_TEXT_REQUIRED, sprintf(
                    'Profile %s requires an alternative description for form field "%s".',
                    $document->profile->name(),
                    $field->name,
                ));
            }
        }

        if ($document->profile->requiresTaggedFormFields()) {
            foreach ($document->acroForm->fields as $field) {
                if ($field instanceof WidgetFormField) {
                    continue;
                }

                if ($field instanceof RadioButtonGroup) {
                    continue;
                }

                throw new DocumentValidationException(DocumentBuildError::PDFA_TAGGED_FORM_SUBSET_REQUIRED, sprintf(
                    'Profile %s requires tagged form fields in the current implementation.',
                    $document->profile->name(),
                ));
            }
        }

        foreach ($document->acroForm->fields as $field) {
            if (
                $document->profile->isPdfA1()
                && $document->profile->pdfaConformance() === 'A'
                && !$this->pdfA1aFormFieldPolicy->supports($field)
            ) {
                throw new DocumentValidationException(
                    DocumentBuildError::PDFA_TAGGED_FORM_SUBSET_REQUIRED,
                    $this->pdfA1aFormFieldPolicy->violationMessage($document->profile),
                );
            }

            if ($field instanceof TextField && !$document->profile->supportsCurrentTextFieldImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow text fields in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof CheckboxField && !$document->profile->supportsCurrentCheckboxImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow checkboxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof RadioButtonGroup && !$document->profile->supportsCurrentRadioButtonImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow radio buttons in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof ComboBoxField && !$document->profile->supportsCurrentComboBoxImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow combo boxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof ListBoxField && !$document->profile->supportsCurrentListBoxImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow list boxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof PushButtonField) {
                if (
                    $document->profile->isPdfA1()
                    && $document->profile->pdfaConformance() === 'A'
                    && $field->url !== null
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_PUSH_BUTTON_ACTION_NOT_ALLOWED, sprintf(
                        'Profile %s does not allow push button URI actions. Use an inert button without /A.',
                        $document->profile->name(),
                    ));
                }

                if (
                    !$document->profile->supportsCurrentPushButtonImplementation()
                    && !(
                        $document->profile->requiresTaggedFormFields()
                        && $field->url === null
                    )
                ) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                        'Profile %s does not allow push buttons in the current implementation.',
                        $document->profile->name(),
                    ));
                }
            }

            if ($field instanceof SignatureField && !$document->profile->supportsCurrentSignatureFieldImplementation()) {
                throw new DocumentValidationException(DocumentBuildError::PDFA_ACROFORM_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow signature fields in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if (!$field instanceof WidgetFormField) {
                if ($field instanceof RadioButtonGroup) {
                    foreach ($field->choices as $choice) {
                        if (!isset($document->pages[$choice->pageNumber - 1])) {
                            throw new DocumentValidationException(DocumentBuildError::FORM_FIELD_PAGE_INVALID, sprintf(
                                'Form field "%s" targets page %d which does not exist.',
                                $field->name,
                                $choice->pageNumber,
                            ));
                        }
                    }
                }

                continue;
            }

            if (!isset($document->pages[$field->pageNumber - 1])) {
                throw new DocumentValidationException(DocumentBuildError::FORM_FIELD_PAGE_INVALID, sprintf(
                    'Form field "%s" targets page %d which does not exist.',
                    $field->name,
                    $field->pageNumber,
                ));
            }
        }
    }

    private function assertPdfARequirements(Document $document, ?DateTimeImmutable $serializedAt = null): void
    {
        if (!$document->profile->isPdfA()) {
            return;
        }

        if ($document->encryption !== null) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_ENCRYPTION_NOT_ALLOWED, sprintf(
                'Profile %s does not allow encryption.',
                $document->profile->name(),
            ));
        }

        $this->pdfA1PolicyEnforcer->enforce($document, serializedAt: $serializedAt);
        $this->pdfALowLevelPolicyValidator->assertDocumentLowLevelSafety($document);
        $this->pdfAColorPolicyValidator->assertDocumentColors($document);

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $pageFont) {
                if (!$pageFont->isEmbedded()) {
                    throw new DocumentValidationException(DocumentBuildError::PDFA_EMBEDDED_FONTS_REQUIRED, sprintf(
                        'Profile %s requires embedded fonts. Found standard font "%s" on page %d.',
                        $document->profile->name(),
                        $pageFont->name,
                        $pageIndex + 1,
                    ));
                }

                if (!$document->profile->requiresExtractableEmbeddedUnicodeFonts() || $pageFont->usesUnicodeCids()) {
                    continue;
                }

                throw new DocumentValidationException(DocumentBuildError::PDFA_UNICODE_FONTS_REQUIRED, sprintf(
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
                    throw new DocumentValidationException(DocumentBuildError::PDFA_IMAGE_COLOR_SPACE_NOT_ALLOWED, sprintf(
                        'Profile %s does not allow custom image color space definitions in the current implementation for image resource %d on page %d.',
                        $document->profile->name(),
                        $imageResourceIndex,
                        $pageIndex + 1,
                    ));
                }

                if ($imageSource->softMask === null || $document->profile->supportsCurrentTransparencyImplementation()) {
                    continue;
                }

                throw new DocumentValidationException(DocumentBuildError::PDFA_TRANSPARENCY_NOT_ALLOWED, sprintf(
                    'Profile %s does not allow soft-mask image transparency for image resource %d on page %d.',
                    $document->profile->name(),
                    $imageResourceIndex,
                    $pageIndex + 1,
                ));
            }
        }
    }
}
