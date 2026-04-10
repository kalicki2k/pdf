<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Action\ThreadAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ThreadActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_without_a_file_by_default(): void
    {
        $action = new ThreadAction('article-1');

        self::assertSame(
            '<< /S /Thread /D (article-1) >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_returns_a_pdf_dictionary_with_a_file_when_provided(): void
    {
        $action = new ThreadAction('article-1', 'threads.pdf');

        self::assertSame(
            '<< /S /Thread /D (article-1) /F (threads.pdf) >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_rejects_an_empty_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thread action destination must not be empty.');

        new ThreadAction('');
    }
}
