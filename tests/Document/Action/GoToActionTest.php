<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Action\GoToAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GoToActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_named_destination(): void
    {
        $action = new GoToAction('table-demo');

        self::assertSame('<< /S /GoTo /D /table-demo >>', $action->toPdfDictionary()->render());
    }

    #[Test]
    public function it_rejects_an_empty_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GoTo action destination must not be empty.');

        new GoToAction('');
    }
}
