<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Action\HideAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HideActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_that_hides_the_target_by_default(): void
    {
        $action = new HideAction('notes_panel');

        self::assertSame('<< /S /Hide /T (notes_panel) >>', $action->toPdfDictionary()->render());
    }

    #[Test]
    public function it_returns_a_pdf_dictionary_that_shows_the_target_when_hide_is_false(): void
    {
        $action = new HideAction('notes_panel', false);

        self::assertSame('<< /S /Hide /T (notes_panel) /H false >>', $action->toPdfDictionary()->render());
    }

    #[Test]
    public function it_rejects_an_empty_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hide action target must not be empty.');

        new HideAction('');
    }
}
