<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Action;

use InvalidArgumentException;
use Kalle\Pdf\Feature\Action\UriAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UriActionTest extends TestCase
{
    #[Test]
    public function it_returns_a_pdf_dictionary_for_a_uri_action(): void
    {
        $action = new UriAction('https://example.com');

        self::assertSame(
            '<< /S /URI /URI (https://example.com) >>',
            $action->toPdfDictionary()->render(),
        );
    }

    #[Test]
    public function it_rejects_an_empty_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URI action URL must not be empty.');

        new UriAction('');
    }
}
