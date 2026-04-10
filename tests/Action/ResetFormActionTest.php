<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Action;

use Kalle\Pdf\Action\ResetFormAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResetFormActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_reset_form_action(): void
    {
        $action = new ResetFormAction();

        self::assertSame('<< /S /ResetForm >>', $action->toPdfDictionary()->render());
    }
}
