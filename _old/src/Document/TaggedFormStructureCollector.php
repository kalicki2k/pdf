<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function count;
use function sprintf;

use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\WidgetFormField;

final class TaggedFormStructureCollector
{
    /**
     * @param list<int> $acroFormFieldObjectIds
     * @param array<int, list<int>> $acroFormFieldRelatedObjectIds
     * @return array{
     *   entries: list<array{key: string, pageIndex: int, annotationObjectId: int, altText: string}>,
     *   parentTreeEntries: array<int, list<string>>,
     *   structParentIds: array<int, int>
     * }
     */
    public function collect(
        Document $document,
        array $acroFormFieldObjectIds,
        array $acroFormFieldRelatedObjectIds,
        int $nextStructParentId,
    ): array {
        if (!$document->profile->requiresTaggedFormFields() || $document->acroForm === null) {
            return [
                'entries' => [],
                'parentTreeEntries' => [],
                'structParentIds' => [],
            ];
        }

        $entries = [];
        $structParentRegistry = new TaggedAnnotationStructParentRegistry($nextStructParentId);

        foreach ($document->acroForm->fields as $fieldIndex => $field) {
            if ($field instanceof RadioButtonGroup) {
                foreach ($field->choices as $choiceIndex => $choice) {
                    $annotationObjectId = $acroFormFieldRelatedObjectIds[$fieldIndex][$choiceIndex * 3] ?? null;

                    if ($annotationObjectId === null) {
                        throw new DocumentValidationException(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, sprintf(
                            'Tagged form structure requires a widget annotation object for radio button group "%s" choice %d.',
                            $field->name,
                            $choiceIndex + 1,
                        ));
                    }

                    $entryKey = 'form:' . $field->name . ':choice:' . $choiceIndex;
                    $entries[] = [
                        'key' => $entryKey,
                        'pageIndex' => $choice->pageNumber - 1,
                        'annotationObjectId' => $annotationObjectId,
                        'altText' => $choice->alternativeName ?? $field->alternativeName ?? $field->name,
                    ];
                    $structParentRegistry->register($annotationObjectId, $entryKey);
                }

                continue;
            }

            if (!$field instanceof WidgetFormField) {
                continue;
            }

            $annotationObjectIdsByPage = $field->pageAnnotationObjectIds(
                $acroFormFieldObjectIds[$fieldIndex],
                $acroFormFieldRelatedObjectIds[$fieldIndex] ?? [],
            );
            $annotationObjectIds = [];

            foreach ($annotationObjectIdsByPage as $pageAnnotationObjectIds) {
                $annotationObjectIds = [...$annotationObjectIds, ...$pageAnnotationObjectIds];
            }

            if (count($annotationObjectIds) !== 1) {
                throw new DocumentValidationException(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, sprintf(
                    'Tagged PDF/UA form support currently requires exactly one widget annotation for field "%s".',
                    $field->name,
                ));
            }

            $entryKey = 'form:' . $field->name;
            $annotationObjectId = $annotationObjectIds[0];
            $entries[] = [
                'key' => $entryKey,
                'pageIndex' => $field->pageNumber - 1,
                'annotationObjectId' => $annotationObjectId,
                'altText' => $field->alternativeName ?? $field->name,
            ];
            $structParentRegistry->register($annotationObjectId, $entryKey);
        }

        return [
            'entries' => $entries,
            'parentTreeEntries' => $structParentRegistry->parentTreeEntries(),
            'structParentIds' => $structParentRegistry->intStructParentIds(),
        ];
    }
}
