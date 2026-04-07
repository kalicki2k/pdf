<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\PublicApi;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\PageRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_the_internal_page_for_a_public_page(): void
    {
        $document = new Document(profile: \Kalle\Pdf\Profile::standard(1.4));
        $page = $document->addPage();

        $property = new \ReflectionProperty($page, 'page');

        self::assertSame($property->getValue($page), PageRegistry::resolve($page));
    }

    #[Test]
    public function it_rejects_pages_that_do_not_belong_to_the_public_api_registry(): void
    {
        $page = new \ReflectionClass(\Kalle\Pdf\Page::class)->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided page does not belong to this library API.');

        PageRegistry::resolve($page);
    }
}
