<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Table\TableGroupPageFit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableGroupPageFitTest extends TestCase
{
    #[Test]
    public function it_stores_group_page_fit_values(): void
    {
        $pageFit = new TableGroupPageFit(true, 3);

        self::assertTrue($pageFit->repeatHeaders);
        self::assertSame(3, $pageFit->fittingRowCountOnCurrentPage);
    }
}
