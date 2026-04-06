<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use Kalle\Pdf\Document\Action\SubmitFormAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubmitFormActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_without_flags_by_default(): void
    {
        $action = new SubmitFormAction('https://example.com/submit');

        self::assertSame(
            '<< /S /SubmitForm /F (https://example.com/submit) >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_includes_flags_when_they_are_greater_than_zero(): void
    {
        $action = new SubmitFormAction('https://example.com/submit', 4);

        self::assertSame(
            '<< /S /SubmitForm /F (https://example.com/submit) /Flags 4 >>',
            $action->toPdfDictionary()->render(),
        );
    }
}
