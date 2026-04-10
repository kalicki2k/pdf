<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\SetOcgStateAction;
use Kalle\Pdf\Internal\Document\OptionalContent\OptionalContentGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SetOcgStateActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_mixed_state_entries(): void
    {
        $layer = new OptionalContentGroup(7, 'LayerA');
        $action = new SetOcgStateAction(['Toggle', $layer], false);

        self::assertSame(
            '<< /S /SetOCGState /State [/Toggle 7 0 R] /PreserveRB false >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_omits_preserve_rb_when_it_is_enabled(): void
    {
        $layer = new OptionalContentGroup(7, 'LayerA');
        $action = new SetOcgStateAction(['ON', $layer]);

        self::assertSame(
            '<< /S /SetOCGState /State [/ON 7 0 R] >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_rejects_an_empty_state_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Set OCG state action requires at least one state entry.');

        new SetOcgStateAction([]);
    }

    #[Test]
    public function it_rejects_unsupported_state_operators(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Set OCG state action accepts only ON, OFF, Toggle or OptionalContentGroup entries.');

        new SetOcgStateAction(['INVALID']);
    }
}
