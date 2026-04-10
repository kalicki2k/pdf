<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Page\Content\PageMarkedContentIds;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageMarkedContentIdsTest extends TestCase
{
    #[Test]
    public function it_allocates_marked_content_ids_sequentially(): void
    {
        $markedContentIds = new PageMarkedContentIds();

        self::assertSame(0, $markedContentIds->next());
        self::assertSame(1, $markedContentIds->next());
        self::assertSame(2, $markedContentIds->next());
    }

    #[Test]
    public function it_tracks_whether_any_marked_content_id_was_allocated(): void
    {
        $markedContentIds = new PageMarkedContentIds();

        self::assertFalse($markedContentIds->hasAllocatedIds());

        $markedContentIds->next();

        self::assertTrue($markedContentIds->hasAllocatedIds());
    }
}
