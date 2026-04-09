<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Action\JavaScriptAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JavaScriptActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_javascript_action(): void
    {
        $action = new JavaScriptAction("app.alert('Hallo');");

        self::assertSame(
            "<< /S /JavaScript /JS (app.alert\\('Hallo'\\);) >>",
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_rejects_an_empty_script(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JavaScript action script must not be empty.');

        new JavaScriptAction('');
    }
}
