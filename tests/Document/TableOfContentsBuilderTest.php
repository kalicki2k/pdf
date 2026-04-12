<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsLeaderStyle;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsPlacement;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsStyle;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

use function array_map;
use function bin2hex;
use function count;
use function hex2bin;
use function implode;
use function iterator_to_array;
use function preg_match_all;
use function str_contains;
use function str_replace;

final class TableOfContentsBuilderTest extends TestCase
{
    public function testItBuildsATableOfContentsAtTheStartFromOutlines(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Chapter 1')
            ->outline('Chapter 1')
            ->newPage()
            ->text('Chapter 2')
            ->outlineAt('Chapter 2', 2)
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
            ))
            ->build();

        self::assertCount(3, $document->pages);
        self::assertPageContainsText($document->pages[0]->contents, 'Contents');
        self::assertPageContainsText($document->pages[0]->contents, 'Chapter 1');
        self::assertPageContainsText($document->pages[1]->contents, 'Chapter 1');
        self::assertSame(2, $document->outlines[0]->pageNumber);
        self::assertSame(3, $document->outlines[1]->pageNumber);
    }

    public function testItBuildsATableOfContentsAtTheEnd(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Chapter 1')
            ->outline('Chapter 1')
            ->newPage()
            ->text('Chapter 2')
            ->outlineAt('Chapter 2', 2)
            ->tableOfContents()
            ->build();

        self::assertCount(3, $document->pages);
        self::assertPageContainsText($document->pages[2]->contents, 'Contents');
        self::assertSame(1, $document->outlines[0]->pageNumber);
        self::assertSame(2, $document->outlines[1]->pageNumber);
    }

    public function testItBuildsATableOfContentsAfterASpecificPage(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Cover')
            ->newPage()
            ->text('Chapter 1')
            ->outlineAt('Chapter 1', 2)
            ->newPage()
            ->text('Chapter 2')
            ->outlineAt('Chapter 2', 3)
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::afterPage(1),
            ))
            ->build();

        self::assertCount(4, $document->pages);
        self::assertPageContainsText($document->pages[0]->contents, 'Cover');
        self::assertPageContainsText($document->pages[1]->contents, 'Contents');
        self::assertPageContainsText($document->pages[2]->contents, 'Chapter 1');
        self::assertSame(3, $document->outlines[0]->pageNumber);
        self::assertSame(4, $document->outlines[1]->pageNumber);
    }

    public function testItBuildsATableOfContentsFromExplicitEntriesWhenNoOutlinesExist(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Alpha')
            ->tableOfContentsEntry('Alpha')
            ->newPage()
            ->text('Beta')
            ->tableOfContentsEntryAt('Beta', 2)
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
            ))
            ->build();

        self::assertCount(3, $document->pages);
        self::assertPageContainsText($document->pages[0]->contents, 'Alpha');
        self::assertPageContainsText($document->pages[0]->contents, 'Beta');
    }

    public function testItBreaksMultiPageTablesOfContentsDeterministically(): void
    {
        $builder = DefaultDocumentBuilder::make();

        for ($index = 1; $index <= 18; $index++) {
            $builder = $builder
                ->text('Chapter ' . $index)
                ->outline('Chapter ' . $index);

            if ($index < 18) {
                $builder = $builder->newPage();
            }
        }

        $document = $builder
            ->tableOfContents(new TableOfContentsOptions(
                pageSize: PageSize::A7(),
                margin: Margin::all(Units::mm(8)),
                titleSize: 14.0,
                entrySize: 11.0,
                placement: TableOfContentsPlacement::start(),
            ))
            ->build();

        self::assertGreaterThan(1, count($document->pages) - 18);
        self::assertPageContainsText($document->pages[0]->contents, 'Contents');
        self::assertPageContainsText($document->pages[1]->contents, 'Chapter');
        self::assertSame(count($document->pages), $document->outlines[17]->pageNumber);
    }

    public function testItRendersDeterministicLeaderStyles(): void
    {
        $dots = DefaultDocumentBuilder::make()
            ->text('Alpha')
            ->outline('Alpha')
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
                style: new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::DOTS),
            ))
            ->build();
        $dashes = DefaultDocumentBuilder::make()
            ->text('Alpha')
            ->outline('Alpha')
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
                style: new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::DASHES),
            ))
            ->build();
        $none = DefaultDocumentBuilder::make()
            ->text('Alpha')
            ->outline('Alpha')
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
                style: new TableOfContentsStyle(leaderStyle: TableOfContentsLeaderStyle::NONE),
            ))
            ->build();

        self::assertStringContainsString(bin2hex('...'), bin2hex($dots->pages[0]->contents));
        self::assertStringContainsString(bin2hex('---'), bin2hex($dashes->pages[0]->contents));
        self::assertStringNotContainsString(bin2hex('...'), bin2hex($none->pages[0]->contents));
        self::assertStringNotContainsString(bin2hex('---'), bin2hex($none->pages[0]->contents));
    }

    public function testItShiftsExistingInternalPageLinksWhenTocPagesAreInserted(): void
    {
        $document = DefaultDocumentBuilder::make()
            ->text('Cover')
            ->newPage()
            ->text('Target')
            ->newPage()
            ->text('Jump', new TextOptions(
                link: LinkTarget::page(2),
            ))
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::start(),
            ))
            ->tableOfContentsEntryAt('Target', 2)
            ->build();

        self::assertTrue($document->pages[3]->annotations[0]->target->isPage());
        self::assertSame(3, $document->pages[3]->annotations[0]->target->pageNumberValue());
    }

    public function testItRejectsInvalidAfterPagePlacement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table of contents insertion page 3 is out of bounds for a document with 2 pages.');

        DefaultDocumentBuilder::make()
            ->text('One')
            ->newPage()
            ->text('Two')
            ->outlineAt('Two', 2)
            ->tableOfContents(new TableOfContentsOptions(
                placement: TableOfContentsPlacement::afterPage(3),
            ))
            ->build();
    }

    public function testItAddsNamedDestinationsAndLinksForTocEntriesToTheSerializationPlan(): void
    {
        $plan = (new DocumentSerializationPlanBuilder())->build(
            DefaultDocumentBuilder::make()
                ->text('Chapter 1')
                ->outline('Chapter 1')
                ->tableOfContents(new TableOfContentsOptions(
                    placement: TableOfContentsPlacement::start(),
                ))
                ->build(),
        );

        $serialized = implode("\n", array_map(
            static fn ($object): string => $object->contents,
            iterator_to_array($plan->objects),
        ));

        self::assertStringContainsString('/__pdf2_toc_entry_1 [', $serialized);
        self::assertStringContainsString('/Dest /__pdf2_toc_entry_1', $serialized);
    }

    private static function assertPageContainsText(string $contents, string $text): void
    {
        self::assertStringContainsString($text, self::decodedPageText($contents));
    }

    private static function decodedPageText(string $contents): string
    {
        $decoded = '';

        preg_match_all('/<([0-9A-Fa-f]+)>|\(([^)]*)\)/', $contents, $matches, \PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $chunk = hex2bin($match[1]);
                $decoded .= $chunk === false ? '' : $chunk;
                continue;
            }

            $decoded .= str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $match[2] ?? '');
        }

        return $decoded;
    }
}
