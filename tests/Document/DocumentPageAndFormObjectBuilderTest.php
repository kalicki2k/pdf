<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentPageAndFormObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Form\TextField;
use PHPUnit\Framework\TestCase;

final class DocumentPageAndFormObjectBuilderTest extends TestCase
{
    public function testItRaisesACodedBuildStateErrorWhenAcroFormObjectIdIsMissing(): void
    {
        $document = new Document(
            acroForm: new AcroForm()->withField(
                new TextField('customer_name', 1, 10.0, 20.0, 80.0, 12.0, 'Ada', 'Customer name'),
            ),
        );
        $state = new DocumentSerializationPlanObjectIdAllocator()->allocate(
            $document,
            static fn (int $nextStructParentId): array => [
                'linkEntries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ],
            static fn (int $nextStructParentId): array => [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
                'nextStructParentId' => $nextStructParentId,
            ],
            static fn (array $fieldObjectIds, array $relatedObjectIds, int $nextStructParentId): array => [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
            ],
            static fn (): array => [],
        );

        try {
            new DocumentPageAndFormObjectBuilder()->buildAcroFormObjects(
                $document,
                $this->cloneStateWithAcroFormObjectId($state, null),
            );
            self::fail('Expected coded build-state validation error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::BUILD_STATE_INVALID, $exception->error);
            self::assertSame('AcroForm object ID allocation is missing.', $exception->getMessage());
        }
    }

    private function cloneStateWithAcroFormObjectId(
        DocumentSerializationPlanBuildState $state,
        ?int $acroFormObjectId,
    ): DocumentSerializationPlanBuildState {
        return new DocumentSerializationPlanBuildState(
            pageObjectIds: $state->pageObjectIds,
            contentObjectIds: $state->contentObjectIds,
            fontObjectIds: $state->fontObjectIds,
            fontDescriptorObjectIds: $state->fontDescriptorObjectIds,
            fontFileObjectIds: $state->fontFileObjectIds,
            cidFontObjectIds: $state->cidFontObjectIds,
            toUnicodeObjectIds: $state->toUnicodeObjectIds,
            cidToGidMapObjectIds: $state->cidToGidMapObjectIds,
            cidSetObjectIds: $state->cidSetObjectIds,
            imageObjectIds: $state->imageObjectIds,
            pageAnnotationObjectIds: $state->pageAnnotationObjectIds,
            pageAnnotationAppearanceObjectIds: $state->pageAnnotationAppearanceObjectIds,
            pageAnnotationRelatedObjectIds: $state->pageAnnotationRelatedObjectIds,
            attachmentObjectIds: $state->attachmentObjectIds,
            embeddedFileObjectIds: $state->embeddedFileObjectIds,
            acroFormObjectId: $acroFormObjectId,
            acroFormFieldObjectIds: $state->acroFormFieldObjectIds,
            acroFormFieldRelatedObjectIds: $state->acroFormFieldRelatedObjectIds,
            pageFormWidgetObjectIds: $state->pageFormWidgetObjectIds,
            taggedStructure: $state->taggedStructure,
            pageStructParentIds: $state->pageStructParentIds,
            taggedLinkStructure: $state->taggedLinkStructure,
            taggedPageAnnotationStructure: $state->taggedPageAnnotationStructure,
            taggedFormStructure: $state->taggedFormStructure,
            namedDestinations: $state->namedDestinations,
            outlineRootObjectId: $state->outlineRootObjectId,
            outlineItemObjectIds: $state->outlineItemObjectIds,
            structTreeRootObjectId: $state->structTreeRootObjectId,
            documentStructElemObjectId: $state->documentStructElemObjectId,
            parentTreeObjectId: $state->parentTreeObjectId,
            taggedStructureObjectIds: $state->taggedStructureObjectIds,
            taggedFormStructElemObjectIds: $state->taggedFormStructElemObjectIds,
            metadataObjectId: $state->metadataObjectId,
            iccProfileObjectId: $state->iccProfileObjectId,
            infoObjectId: $state->infoObjectId,
            encryptObjectId: $state->encryptObjectId,
            acroFormDefaultFont: $state->acroFormDefaultFont,
            acroFormDefaultFontKey: $state->acroFormDefaultFontKey,
        );
    }
}
