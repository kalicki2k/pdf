<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\LaunchAction;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class LaunchActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_launch_action(): void
    {
        $action = new LaunchAction('guide.pdf');

        self::assertSame(
            '<< /S /Launch /F (guide.pdf) >>',
            writePdfTypeToString($action->toPdfDictionary()),
        );
    }

    #[Test]
    public function it_rejects_an_empty_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Launch action target must not be empty.');

        new LaunchAction('');
    }
}
