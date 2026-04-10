<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\GoToAction;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class GoToActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_named_destination(): void
    {
        $action = new GoToAction('table-demo');

        self::assertSame('<< /S /GoTo /D /table-demo >>', writePdfTypeToString($action->toPdfDictionary()));
    }

    #[Test]
    public function it_rejects_an_empty_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GoTo action destination must not be empty.');

        new GoToAction('');
    }
}
