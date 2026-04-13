<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\TaggedPdf;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureCollector;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureElement;
use Kalle\Pdf\Document\TaggedPdf\TaggedStructureObjectIds;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use PHPUnit\Framework\TestCase;

final class TaggedStructureInvariantTest extends TestCase
{
    public function testItRaisesACodedErrorForDuplicateMarkedContentIdsOnTheSamePage(): void
    {
        $document = new Document(
            taggedTextBlocks: [
                new TaggedTextBlock('P', 0, 7, 'text:first'),
                new TaggedTextBlock('P', 0, 7, 'text:second'),
            ],
        );

        try {
            new TaggedStructureCollector()->collect($document);
            self::fail('Expected coded tagged-structure build error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, $exception->error);
            self::assertSame('Duplicate marked-content id 7 on page 1.', $exception->getMessage());
        }
    }

    public function testItRaisesACodedErrorForTaggedChildrenWithMultipleParents(): void
    {
        $document = new Document(
            taggedStructureElements: [
                new TaggedStructureElement('struct:first', 'Sect', ['text:intro']),
                new TaggedStructureElement('struct:second', 'Sect', ['text:intro']),
            ],
        );

        try {
            new TaggedStructureCollector()->collect($document);
            self::fail('Expected coded tagged-structure build error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, $exception->error);
            self::assertSame(
                'Tagged structure child "text:intro" is assigned to more than one parent container.',
                $exception->getMessage(),
            );
        }
    }

    public function testItRaisesACodedErrorForUnknownTaggedPageContentKeys(): void
    {
        $objectIds = new TaggedStructureObjectIds(
            figureStructElemObjectIds: [],
            textStructElemObjectIds: [],
            listStructElemObjectIds: [],
            listItemStructElemObjectIds: [],
            listLabelStructElemObjectIds: [],
            listBodyStructElemObjectIds: [],
            tableStructElemObjectIds: [],
            captionStructElemObjectIds: [],
            tableSectionStructElemObjectIds: [],
            rowStructElemObjectIds: [],
            cellStructElemObjectIds: [],
            genericStructElemObjectIds: [],
            linkStructElemObjectIds: [],
            annotationStructElemObjectIds: [],
            nextObjectId: 1,
        );

        try {
            $objectIds->resolvePageContentObjectId('missing:key');
            self::fail('Expected coded tagged-structure build error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, $exception->error);
            self::assertSame("Unknown tagged page content key 'missing:key'.", $exception->getMessage());
        }
    }

    public function testItRaisesACodedErrorForUnknownTaggedStructureKeys(): void
    {
        $objectIds = new TaggedStructureObjectIds(
            figureStructElemObjectIds: [],
            textStructElemObjectIds: [],
            listStructElemObjectIds: [],
            listItemStructElemObjectIds: [],
            listLabelStructElemObjectIds: [],
            listBodyStructElemObjectIds: [],
            tableStructElemObjectIds: [],
            captionStructElemObjectIds: [],
            tableSectionStructElemObjectIds: [],
            rowStructElemObjectIds: [],
            cellStructElemObjectIds: [],
            genericStructElemObjectIds: [],
            linkStructElemObjectIds: [],
            annotationStructElemObjectIds: [],
            nextObjectId: 1,
        );

        try {
            $objectIds->resolveStructElemObjectId('missing:struct');
            self::fail('Expected coded tagged-structure build error.');
        } catch (DocumentValidationException $exception) {
            self::assertSame(DocumentBuildError::TAGGED_STRUCTURE_BUILD_INVALID, $exception->error);
            self::assertSame("Unknown tagged structure key 'missing:struct'.", $exception->getMessage());
        }
    }
}
