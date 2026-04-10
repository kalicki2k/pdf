<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\PreparedTableCell;
use Kalle\Pdf\Internal\Layout\Table\Layout\RowPreparer;
use Kalle\Pdf\Internal\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Internal\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Internal\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Internal\Layout\Table\Support\TableStyleResolver;
use Kalle\Pdf\Internal\Layout\Table\Support\TableTextMetrics;
use Kalle\Pdf\Internal\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Internal\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RowPreparerTest extends TestCase
{
    #[Test]
    public function it_prepares_a_row_and_updates_following_rowspans(): void
    {
        $preparer = $this->createPreparer([50, 60, 70]);

        $result = $preparer->prepareRow([
            'Alpha',
            new TableCell('Beta', colspan: 2, rowspan: 2),
        ], [0, 0, 0], false);

        self::assertSame([0, 1, 1], $result['nextRowspans']);
        self::assertCount(2, $result['cells']);

        $firstCell = $result['cells'][0];
        $secondCell = $result['cells'][1];

        self::assertInstanceOf(PreparedTableCell::class, $firstCell);
        self::assertSame(50.0, $firstCell->width);
        self::assertSame(0, $firstCell->column);
        self::assertSame('Alpha', $firstCell->cell->text);

        self::assertSame(130.0, $secondCell->width);
        self::assertSame(1, $secondCell->column);
        self::assertSame('Beta', $secondCell->cell->text);
        self::assertSame(2, $secondCell->cell->colspan);
        self::assertSame(2, $secondCell->cell->rowspan);
    }

    #[Test]
    public function it_normalizes_header_cells_to_bold_text_segments(): void
    {
        $preparer = $this->createPreparer([80, 80]);

        $result = $preparer->prepareRow([
            'Header',
            new TableCell(
                [new TextSegment('Segment', italic: true, underline: true)],
                style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
            ),
        ], [0, 0], true);

        /** @var list<TextSegment> $firstText */
        $firstText = $result['cells'][0]->cell->text;
        /** @var list<TextSegment> $secondText */
        $secondText = $result['cells'][1]->cell->text;

        self::assertCount(1, $firstText);
        self::assertSame('Header', $firstText[0]->text);
        self::assertTrue($firstText[0]->bold);

        self::assertCount(1, $secondText);
        self::assertSame('Segment', $secondText[0]->text);
        self::assertTrue($secondText[0]->bold);
        self::assertTrue($secondText[0]->italic);
        self::assertTrue($secondText[0]->underline);
    }

    #[Test]
    public function it_handles_rows_that_are_fully_covered_by_active_rowspans(): void
    {
        $preparer = $this->createPreparer([50]);

        $result = $preparer->prepareRow([], [1], false);

        self::assertSame([], $result['cells']);
        self::assertSame([0], $result['nextRowspans']);
    }

    #[Test]
    public function it_rejects_invalid_row_preparation_inputs(): void
    {
        $defaultPreparer = $this->createPreparer([50, 50]);
        $tightPreparer = $this->createPreparer([40], new TableStyle(padding: TablePadding::all(25)));

        $cases = [
            ['Table cell rowspan must be greater than zero.', fn (): array => $defaultPreparer->prepareRow([new TableCell('A', rowspan: 0)], [0, 0], false)],
            ['Table cell colspan must be greater than zero.', fn (): array => $defaultPreparer->prepareRow([new TableCell('A', colspan: 0)], [0, 0], false)],
            ['Table cell colspan exceeds the configured table columns.', fn (): array => $defaultPreparer->prepareRow([new TableCell('A', colspan: 3)], [0, 0], false)],
            ['Table row spans must match the number of columns.', fn (): array => $defaultPreparer->prepareRow([], [0, 0], false)],
            ['Table row spans must match the number of columns.', fn (): array => $defaultPreparer->prepareRow([new TableCell('A', colspan: 2)], [0, 1], false)],
            ['Table row spans must match the number of columns.', fn (): array => $this->createPreparer([50])->prepareRow(['A'], [1], false)],
            ['Table column width must be greater than the horizontal cell padding.', fn (): array => $tightPreparer->prepareRow(['A'], [0], false)],
        ];

        foreach ($cases as [$expectedMessage, $callback]) {
            try {
                $callback();
                self::fail("Expected exception with message: $expectedMessage");
            } catch (InvalidArgumentException $exception) {
                self::assertSame($expectedMessage, $exception->getMessage());
            }
        }
    }

    /**
     * @param list<float|int> $columnWidths
     */
    private function createPreparer(
        array $columnWidths,
        ?TableStyle $tableStyle = null,
        ?RowStyle $rowStyle = null,
        ?HeaderStyle $headerStyle = null,
    ): RowPreparer {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        return new RowPreparer(
            $page,
            $columnWidths,
            'Helvetica',
            12,
            1.2,
            $tableStyle ?? new TableStyle(),
            $rowStyle,
            $headerStyle,
            new TableStyleResolver(),
            new TableTextMetrics(),
        );
    }
}
