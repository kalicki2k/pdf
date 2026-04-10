<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Table\Layout\PreparedTableRow;
use Kalle\Pdf\Feature\Table\Layout\RowGroupPreparer;
use Kalle\Pdf\Feature\Table\Layout\RowPreparer;
use Kalle\Pdf\Feature\Table\Style\HeaderStyle;
use Kalle\Pdf\Feature\Table\Style\RowStyle;
use Kalle\Pdf\Feature\Table\Style\TableStyle;
use Kalle\Pdf\Feature\Table\Support\TableStyleResolver;
use Kalle\Pdf\Feature\Table\Support\TableTextMetrics;
use Kalle\Pdf\Feature\Table\TableCell;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RowGroupPreparerTest extends TestCase
{
    #[Test]
    public function it_prepares_a_standalone_row_group_with_fresh_rowspan_state(): void
    {
        $preparer = new RowGroupPreparer($this->createRowPreparer([80, 80]), 2);

        $rows = $preparer->prepareGroup([
            [new TableCell('A', rowspan: 2), 'One'],
            ['Two'],
        ], false, true, 'Footer rowspans must be completed within the footer rows.');

        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(PreparedTableRow::class, $rows);
        self::assertTrue($rows[0]->footer);
        self::assertTrue($rows[1]->footer);
    }

    #[Test]
    public function it_rejects_unfinished_rowspans_in_a_standalone_row_group(): void
    {
        $preparer = new RowGroupPreparer($this->createRowPreparer([80, 80]), 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header rowspans must be completed within the repeated header rows.');

        $preparer->prepareGroup([
            [new TableCell('A', rowspan: 2), 'One'],
        ], true, false, 'Header rowspans must be completed within the repeated header rows.');
    }

    /**
     * @param list<float|int> $columnWidths
     */
    private function createRowPreparer(
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
