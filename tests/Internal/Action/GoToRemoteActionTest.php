<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\GoToRemoteAction;

use function Kalle\Pdf\Tests\Support\writePdfTypeToString;

use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

final class GoToRemoteActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_remote_destination(): void
    {
        $action = new GoToRemoteAction('guide.pdf', 'chapter-1');

        self::assertSame(
            '<< /S /GoToR /F (guide.pdf) /D /chapter-1 >>',
            writePdfTypeToString($action->toPdfDictionary()),
        );
    }

    #[Test]
    public function it_rejects_an_empty_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GoTo remote action file must not be empty.');

        new GoToRemoteAction('', 'chapter-1');
    }

    #[Test]
    public function it_rejects_an_empty_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GoTo remote action destination must not be empty.');

        new GoToRemoteAction('guide.pdf', '');
    }
}
