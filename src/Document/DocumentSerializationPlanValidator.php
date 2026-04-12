<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Document\Form\WidgetFormField;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureCollector;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\Page;

use function preg_match;
use function sprintf;

final class DocumentSerializationPlanValidator
{
    public function __construct(
        private readonly TaggedStructureCollector $taggedStructureCollector = new TaggedStructureCollector(),
        private readonly DocumentAttachmentRelationshipResolver $attachmentRelationshipResolver = new DocumentAttachmentRelationshipResolver(),
        private readonly PdfAColorPolicyValidator $pdfAColorPolicyValidator = new PdfAColorPolicyValidator(),
        private readonly PdfALowLevelPolicyValidator $pdfALowLevelPolicyValidator = new PdfALowLevelPolicyValidator(),
    ) {
    }

    public function assertBuildable(Document $document): void
    {
        $this->assertProfileRequirements($document);
        $this->assertTaggedStructureRequirements($document);
        $this->assertAttachmentRequirements($document);
        $this->assertAcroFormRequirements($document);
        $this->assertImageAccessibilityRequirements($document);
        $this->assertAnnotationRequirements($document);
        $this->assertNamedDestinationRequirements($document);
        $this->assertPdfARequirements($document);
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
                    && (($annotation->accessibleLabelOrContents() ?? '') === '')
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
            && (!$document->profile->isPdfA1() || $annotation instanceof LinkAnnotation)
            && $annotation instanceof AppearanceStreamAnnotation;
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

    private function assertAttachmentRequirements(Document $document): void
    {
        if ($document->attachments === []) {
            return;
        }

        if (!$document->profile->supportsDocumentEmbeddedFileAttachments()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow embedded file attachments.',
                $document->profile->name(),
            ));
        }

        foreach ($document->attachments as $attachmentIndex => $attachment) {
            if (
                $this->attachmentRelationshipResolver->resolve($document, $attachment) !== null
                && !$document->profile->supportsDocumentAssociatedFiles()
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow document-level associated files for attachment %d.',
                    $document->profile->name(),
                    $attachmentIndex + 1,
                ));
            }
        }
    }

    private function assertTaggedStructureRequirements(Document $document): void
    {
        if (!$document->profile->isPdfA1() || $document->profile->pdfaConformance() !== 'A') {
            return;
        }

        $taggedStructure = $this->taggedStructureCollector->collect($document);

        if (!$taggedStructure->hasStructuredContent()) {
            throw new InvalidArgumentException(sprintf(
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

            throw new InvalidArgumentException(sprintf(
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

    private function assertAcroFormRequirements(Document $document): void
    {
        if ($document->acroForm === null) {
            return;
        }

        if (!$document->profile->supportsAcroForms() && !$document->profile->isPdfUa()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $document->profile->name(),
            ));
        }

        if ($document->profile->requiresFormFieldAlternativeDescriptions()) {
            foreach ($document->acroForm->fields as $field) {
                if (($field->alternativeName ?? '') !== '') {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires an alternative description for form field "%s".',
                    $document->profile->name(),
                    $field->name,
                ));
            }
        }

        if ($document->profile->requiresTaggedFormFields()) {
            foreach ($document->acroForm->fields as $field) {
                if ($field instanceof RadioButtonGroup) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s does not allow radio buttons in the current tagged form implementation.',
                        $document->profile->name(),
                    ));
                }

                if ($field instanceof WidgetFormField) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Profile %s requires tagged form fields in the current implementation.',
                    $document->profile->name(),
                ));
            }
        }

        foreach ($document->acroForm->fields as $field) {
            if ($field instanceof TextField && !$document->profile->supportsCurrentTextFieldImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow text fields in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof CheckboxField && !$document->profile->supportsCurrentCheckboxImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow checkboxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof RadioButtonGroup && !$document->profile->supportsCurrentRadioButtonImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow radio buttons in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof ComboBoxField && !$document->profile->supportsCurrentComboBoxImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow combo boxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof ListBoxField && !$document->profile->supportsCurrentListBoxImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow list boxes in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof PushButtonField && !$document->profile->supportsCurrentPushButtonImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow push buttons in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if ($field instanceof SignatureField && !$document->profile->supportsCurrentSignatureFieldImplementation()) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow signature fields in the current implementation.',
                    $document->profile->name(),
                ));
            }

            if (!$field instanceof WidgetFormField) {
                if ($field instanceof RadioButtonGroup) {
                    foreach ($field->choices as $choice) {
                        if (!isset($document->pages[$choice->pageNumber - 1])) {
                            throw new InvalidArgumentException(sprintf(
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
                throw new InvalidArgumentException(sprintf(
                    'Form field "%s" targets page %d which does not exist.',
                    $field->name,
                    $field->pageNumber,
                ));
            }
        }
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

        $this->pdfALowLevelPolicyValidator->assertDocumentLowLevelSafety($document);
        $this->pdfAColorPolicyValidator->assertDocumentColors($document);

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->fontResources as $pageFont) {
                if (!$pageFont->isEmbedded()) {
                    throw new InvalidArgumentException(sprintf(
                        'Profile %s requires embedded fonts. Found standard font "%s" on page %d.',
                        $document->profile->name(),
                        $pageFont->name,
                        $pageIndex + 1,
                    ));
                }

                if (!$document->profile->requiresExtractableEmbeddedUnicodeFonts() || $pageFont->usesUnicodeCids()) {
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
}
