<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Document\Action\NamedAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NamedActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_an_allowed_named_action(): void
    {
        $action = new NamedAction('PrevPage');

        self::assertSame(
            '<< /S /Named /N /PrevPage >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_rejects_an_unsupported_named_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Named action must be one of: NextPage, PrevPage, FirstPage, LastPage.');

        new NamedAction('Print');
    }
}
