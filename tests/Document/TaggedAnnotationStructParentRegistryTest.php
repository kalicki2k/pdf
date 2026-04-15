<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TaggedAnnotationStructParentRegistry;
use PHPUnit\Framework\TestCase;

final class TaggedAnnotationStructParentRegistryTest extends TestCase
{
    public function testItAssignsSequentialStructParents(): void
    {
        $registry = new TaggedAnnotationStructParentRegistry(7);

        $registry->register('0:0', 'link:0');
        $registry->register(12, 'form:field');

        self::assertSame(['0:0' => 7, 12 => 8], $registry->structParentIds());
        self::assertSame([7 => ['link:0'], 8 => ['form:field']], $registry->parentTreeEntries());
        self::assertSame(9, $registry->nextStructParentId());
    }
}
