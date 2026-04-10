<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\ImportDataAction;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class ImportDataActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_an_import_data_action(): void
    {
        $action = new ImportDataAction('form-data.fdf');

        self::assertSame(
            '<< /S /ImportData /F (form-data.fdf) >>',
            writePdfTypeToString($action->toPdfDictionary()),
        );
    }

    #[Test]
    public function it_rejects_an_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Import data action file must not be empty.');

        new ImportDataAction('');
    }
}
