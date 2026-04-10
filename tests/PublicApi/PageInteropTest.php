<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Page;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PageInteropTest extends TestCase
{
    #[Test]
    public function it_exposes_the_internal_page_for_a_public_page(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $property = new ReflectionProperty($page, 'page');

        self::assertSame($property->getValue($page), $page->toInternalPage());
    }

    #[Test]
    public function it_rejects_pages_that_do_not_belong_to_this_library_api(): void
    {
        $page = new ReflectionClass(Page::class)->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided page does not belong to this library API.');

        $page->toInternalPage();
    }
}
