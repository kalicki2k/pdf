<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanObjectIdAllocator;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\DocumentTaggedPdfObjectBuilder;
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
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function preg_match_all;

final class DocumentTaggedPdfObjectBuilderTest extends TestCase
{
    public function testItGroupsTaggedLinkAltTextWithWhitespaceBetweenWordParts(): void
    {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: 'Accessible',
            language: 'de-DE',
            pages: [
                new Page(
                    PageSize::A4(),
                    annotations: [
                        new LinkAnnotation(
                            LinkTarget::externalUrl('https://example.com/docs'),
                            10,
                            10,
                            50,
                            10,
                            accessibleLabel: 'Read',
                            taggedGroupKey: 'docs-link',
                        ),
                        new LinkAnnotation(
                            LinkTarget::externalUrl('https://example.com/docs'),
                            10,
                            30,
                            50,
                            10,
                            accessibleLabel: 'Docs',
                            taggedGroupKey: 'docs-link',
                        ),
                    ],
                ),
            ],
        );

        $structure = (new DocumentTaggedPdfObjectBuilder())->collectTaggedLinkStructure($document, 0);

        self::assertCount(1, $structure['linkEntries']);
        self::assertSame('Read Docs', $structure['linkEntries'][0]['altText']);
        self::assertSame([0, 1], $structure['linkEntries'][0]['annotationIndices']);
    }

    public function testItBuildsNoTaggedObjectsForPlainDocuments(): void
    {
        $document = new Document();
        $state = (new DocumentSerializationPlanObjectIdAllocator())->allocate(
            $document,
            static fn (int $nextStructParentId): array => [
                'linkEntries' => [],
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

        $objects = (new DocumentTaggedPdfObjectBuilder())->buildObjects($document, $state);

        self::assertSame([], $objects);
    }

    public function testItBuildsDocumentChildrenInReadingOrderForMixedTaggedPdfA1aDocuments(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->profile(Profile::pdfA1a())
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
                ->image(
                    ImageSource::flate('rgb', 1, 1, ImageColorSpace::RGB),
                    ImagePlacement::at(72, 440, 32, 32),
                )
                ->build(),
        );

        self::assertSame(
            ['H1', 'P', 'L', 'Table', 'Figure'],
            $this->documentChildTags(iterator_to_array($plan->objects)),
        );
    }

    public function testItBuildsDocumentChildrenInReadingOrderAcrossPages(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->profile(Profile::pdfA1a())
                ->title('Archive Copy')
                ->language('de-DE')
                ->heading('Page one heading Привет', 1, new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->paragraph('Page one paragraph Привет', new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->newPage()
                ->table(
                    Table::define(TableColumn::fixed(120.0))
                        ->withPlacement(TablePlacement::at(72.0, 700.0, 120.0))
                        ->withRows(TableRow::fromCells(TableCell::text('Second page table Привет')))
                        ->withTextOptions(new TextOptions(
                            fontSize: 12,
                            lineHeight: 15,
                            embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                        )),
                )
                ->image(
                    ImageSource::flate('rgb', 1, 1, ImageColorSpace::RGB),
                    ImagePlacement::at(72, 620, 32, 32),
                )
                ->build(),
        );

        self::assertSame(
            ['H1', 'P', 'Table', 'Figure'],
            $this->documentChildTags(iterator_to_array($plan->objects)),
        );
    }

    public function testItBuildsDocumentChildrenInReadingOrderWithLinksBetweenContentBlocks(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->profile(Profile::pdfUa1())
                ->title('Accessible Copy')
                ->language('de-DE')
                ->heading('Heading', 1, new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->paragraph('Paragraph', new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->text('Read more', new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                    link: LinkTarget::externalUrl('https://example.com/docs'),
                ))
                ->list(['Item'], text: new TextOptions(
                    width: 220,
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->table(
                    Table::define(TableColumn::fixed(120.0))
                        ->withPlacement(TablePlacement::at(72.0, 540.0, 120.0))
                        ->withCaption(TableCaption::text('Table caption'))
                        ->withRows(TableRow::fromCells(TableCell::text('Value')))
                        ->withTextOptions(new TextOptions(
                            embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                        )),
                )
                ->image(
                    ImageSource::flate('rgb', 1, 1, ImageColorSpace::RGB),
                    ImagePlacement::at(72, 460, 32, 32),
                    ImageAccessibility::alternativeText('Decorative figure'),
                )
                ->build(),
        );

        self::assertSame(
            ['H1', 'P', 'P', 'Link', 'L', 'Table', 'Figure'],
            $this->documentChildTags(iterator_to_array($plan->objects)),
        );
    }

    public function testItDoesNotMergeTaggedLinkStructureAcrossPages(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->profile(Profile::pdfUa1())
                ->title('Accessible Copy')
                ->language('de-DE')
                ->textSegments([
                    new \Kalle\Pdf\Text\TextSegment(
                        'Read docs',
                        new \Kalle\Pdf\Text\TextLink(
                            target: LinkTarget::externalUrl('https://example.com/docs'),
                            groupKey: 'docs-link',
                        ),
                    ),
                ], new TextOptions(
                    width: 45,
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->newPage()
                ->textSegments([
                    new \Kalle\Pdf\Text\TextSegment(
                        'Read docs',
                        new \Kalle\Pdf\Text\TextLink(
                            target: LinkTarget::externalUrl('https://example.com/docs'),
                            groupKey: 'docs-link',
                        ),
                    ),
                ], new TextOptions(
                    width: 45,
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->build(),
        );

        self::assertSame(
            ['Link', 'Link'],
            array_values(array_filter(
                $this->documentChildTags(iterator_to_array($plan->objects)),
                static fn (string $tag): bool => $tag === 'Link',
            )),
        );
    }

    public function testItPlacesTaggedFormStructureAfterMarkedPageContentOnTheSamePage(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->profile(Profile::pdfUa1())
                ->title('Accessible Form')
                ->language('de-DE')
                ->paragraph('Intro text', new TextOptions(
                    embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
                ))
                ->textField('customer_name', 40, 500, 160, 18, 'Ada', 'Customer name')
                ->build(),
        );

        self::assertSame(
            ['P', 'Form'],
            $this->documentChildTags(iterator_to_array($plan->objects)),
        );
    }

    /**
     * @param list<object> $objects
     * @return list<string>
     */
    private function documentChildTags(array $objects): array
    {
        $contentsByObjectId = [];

        foreach ($objects as $object) {
            $contentsByObjectId[$object->objectId] = $object->contents;
        }

        $documentContents = null;

        foreach ($contentsByObjectId as $contents) {
            if (str_contains($contents, '/Type /StructElem /S /Document ')) {
                $documentContents = $contents;
                break;
            }
        }

        self::assertIsString($documentContents);
        self::assertSame(1, preg_match('/\/K \[(.*)\]/', $documentContents, $kidMatch));
        self::assertGreaterThan(0, preg_match_all('/(\d+) 0 R/', $kidMatch[1], $referenceMatches));

        return array_map(
            function (string $objectId) use ($contentsByObjectId): string {
                self::assertArrayHasKey((int) $objectId, $contentsByObjectId);
                self::assertSame(1, preg_match('/\/S \/([A-Za-z0-9]+)/', $contentsByObjectId[(int) $objectId], $tagMatch));

                return $tagMatch[1];
            },
            $referenceMatches[1],
        );
    }

    private function fontPath(): string
    {
        return dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';
    }
}
