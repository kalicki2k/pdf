<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function array_map;
use function dirname;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentPageAndFormObjectBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuildState;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
use Kalle\Pdf\Document\PdfA1aTaggedStructureValidator;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Table;
use Kalle\Pdf\Document\TableCaption;
use Kalle\Pdf\Document\TableCell;
use Kalle\Pdf\Document\TableColumn;
use Kalle\Pdf\Document\TablePlacement;
use Kalle\Pdf\Document\TableRow;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Text\TextOptions;

use Kalle\Pdf\Writer\IndirectObject;
use PHPUnit\Framework\TestCase;

use function str_replace;

final class PdfA1aTaggedStructureValidatorTest extends TestCase
{
    public function testItAcceptsConsistentPdfA1aTaggedStructure(): void
    {
        $document = $this->pdfA1aDocument();
        [$state, $objects] = $this->buildTaggedObjects($document);

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);

        self::assertTrue(true);
    }

    public function testItAcceptsConsistentPdfA2aTaggedStructure(): void
    {
        $document = $this->pdfATaggedDocument(Profile::pdfA2a());
        [$state, $objects] = $this->buildTaggedObjects($document);

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);

        self::assertTrue(true);
    }

    public function testItAcceptsConsistentPdfA3aTaggedStructureWithAssociatedFiles(): void
    {
        $document = $this->pdfATaggedDocument(Profile::pdfA3a(), true);
        [$state, $objects] = $this->buildTaggedObjects($document);

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);

        self::assertTrue(true);
    }

    public function testItAcceptsPdfA1aRectLinkStructureWithoutMarkedContentKids(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->profile(Profile::pdfA1a())
            ->title('Archive Copy')
            ->language('de-DE')
            ->paragraph('Lead in text Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
            ))
            ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
            ->build();
        [$state, $objects] = $this->buildTaggedObjects($document);

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);

        self::assertTrue(true);
    }

    public function testItRejectsStructTreeRootWithoutDocumentKid(): void
    {
        $document = $this->pdfA1aDocument();
        [$state, $objects] = $this->buildTaggedObjects($document);
        $objects = $this->replaceObjectContents(
            $objects,
            $state->structTreeRootObjectId,
            '<< /Type /StructTreeRoot /K [] /ParentTree ' . $state->parentTreeObjectId . ' 0 R >>',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('StructTreeRoot must reference exactly the document structure element');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA2aLinkStructElementWithoutAltText(): void
    {
        $document = $this->pdfATaggedDocument(Profile::pdfA2a());
        [$state, $objects] = $this->buildTaggedObjects($document);

        $linkEntry = $state->taggedLinkStructure['linkEntries'][0];
        $linkObjectId = $state->taggedStructureObjectIds->linkStructElemObjectIds[$linkEntry['key']];
        $linkContents = $this->objectContents($objects, $linkObjectId);
        $objects = $this->replaceObjectContents(
            $objects,
            $linkObjectId,
            preg_replace('/\s*\/Alt\s*\([^)]+\)/', '', $linkContents, 1) ?? $linkContents,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('link StructElem');
        $this->expectExceptionMessage('must expose /Alt text');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsPdfA3aStructTreeRootWithoutParentTree(): void
    {
        $document = $this->pdfATaggedDocument(Profile::pdfA3a(), true);
        [$state, $objects] = $this->buildTaggedObjects($document);
        $structTreeRootContents = $this->objectContents($objects, $state->structTreeRootObjectId);
        $objects = $this->replaceObjectContents(
            $objects,
            $state->structTreeRootObjectId,
            preg_replace('/\s*\/ParentTree\s+\d+\s+0\s+R/', '', $structTreeRootContents, 1) ?? $structTreeRootContents,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('StructTreeRoot must reference ParentTree');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsPageWithoutStructParentsEntry(): void
    {
        $document = $this->pdfA1aDocument();
        [$state, $objects] = $this->buildTaggedObjects($document);
        $pageObjectId = $state->pageObjectIds[0];
        $pageContents = $this->objectContents($objects, $pageObjectId);
        $objects = $this->replaceObjectContents(
            $objects,
            $pageObjectId,
            str_replace(' /StructParents 0', '', $pageContents),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('page 1 must expose /StructParents 0');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsParentTreeWithWrongMarkedContentMapping(): void
    {
        $document = $this->pdfA1aDocument();
        [$state, $objects] = $this->buildTaggedObjects($document);
        $parentTreeContents = $this->objectContents($objects, $state->parentTreeObjectId);
        $objects = $this->replaceObjectContents(
            $objects,
            $state->parentTreeObjectId,
            preg_replace('/\[(\d+) 0 R/', '[999 0 R', $parentTreeContents, 1) ?? $parentTreeContents,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ParentTree entries must match the tagged content mapping');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    public function testItRejectsFigureWithWrongPageReference(): void
    {
        $document = $this->pdfA1aDocument();
        [$state, $objects] = $this->buildTaggedObjects($document);
        $figureEntry = $state->taggedStructure->figureEntries[0];
        $figureObjectId = $state->taggedStructureObjectIds->figureStructElemObjectIds[$figureEntry['key']];
        $figureContents = $this->objectContents($objects, $figureObjectId);
        $objects = $this->replaceObjectContents(
            $objects,
            $figureObjectId,
            str_replace('/Pg ' . $state->pageObjectIds[0] . ' 0 R', '/Pg 999 0 R', $figureContents),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('structure element must reference page object');

        (new PdfA1aTaggedStructureValidator())->assertValid($document, $state, $objects);
    }

    /**
     * @return array{DocumentSerializationPlanBuildState, list<IndirectObject>}
     */
    private function buildTaggedObjects(Document $document): array
    {
        $taggedPdfObjectBuilder = new DocumentTaggedPdfObjectBuilder();
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
            $document,
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedLinkStructure($document, $nextStructParentId),
            fn (int $nextStructParentId): array => $taggedPdfObjectBuilder->collectTaggedPageAnnotationStructure($document, $nextStructParentId),
            function (array $fieldObjectIds, array $relatedObjectIds, int $nextStructParentId) use ($document, $taggedPdfObjectBuilder): array {
                return $taggedPdfObjectBuilder->collectTaggedFormStructure(
                    $document,
                    $fieldObjectIds,
                    $relatedObjectIds,
                    $nextStructParentId,
                );
            },
            static fn (): array => [],
        );

        $pageObjects = (new DocumentPageAndFormObjectBuilder())->buildPageObjects($document, $state);
        $taggedObjects = $taggedPdfObjectBuilder->buildObjects($document, $state);

        return [$state, [...$pageObjects, ...$taggedObjects]];
    }

    private function pdfA1aDocument(): Document
    {
        return $this->pdfATaggedDocument(Profile::pdfA1a());
    }

    private function pdfATaggedDocument(Profile $profile, bool $withAttachment = false): Document
    {
        $builder = DefaultDocumentBuilder::make()
            ->profile($profile)
            ->title('Archive Copy')
            ->language('de-DE')
            ->heading('Heading Привет', 1, new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
            ))
            ->paragraph('Paragraph Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
            ))
            ->list(
                ['List item Привет'],
                text: new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                    width: 220,
                ),
            )
            ->table(
                Table::define(
                    TableColumn::fixed(120.0),
                    TableColumn::fixed(120.0),
                )
                    ->withPlacement(TablePlacement::at(72.0, 520.0, 240.0))
                    ->withCaption(TableCaption::text('Table caption Привет'))
                    ->withRows(TableRow::fromCells(
                        TableCell::text('Left'),
                        TableCell::text('Right'),
                    ))
                    ->withTextOptions(new TextOptions(
                        fontSize: 12,
                        lineHeight: 15,
                        embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                    )),
            )
            ->paragraph('Read more Привет', new TextOptions(
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                link: LinkTarget::externalUrl('https://example.com/docs'),
            ))
            ->image(
                ImageSource::flate('rgb', 1, 1, ImageColorSpace::RGB),
                ImagePlacement::at(72, 440, 32, 32),
                ImageAccessibility::alternativeText('Project figure'),
            );

        if ($withAttachment) {
            $builder = $builder->attachment('data.xml', '<root/>', 'Source data', 'application/xml');
        }

        return $builder->build();
    }

    private function fontPath(): string
    {
        return dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';
    }

    /**
     * @param list<IndirectObject> $objects
     */
    private function objectContents(array $objects, ?int $objectId): string
    {
        foreach ($objects as $object) {
            if ($object->objectId === $objectId) {
                return $object->contents;
            }
        }

        self::fail('Object not found: ' . $objectId);
    }

    /**
     * @param list<IndirectObject> $objects
     * @return list<IndirectObject>
     */
    private function replaceObjectContents(array $objects, ?int $objectId, string $contents): array
    {
        return array_map(
            static fn (IndirectObject $object): IndirectObject => $object->objectId === $objectId
                ? IndirectObject::plain($object->objectId, $contents, $object->encryptable)
                : $object,
            $objects,
        );
    }
}
