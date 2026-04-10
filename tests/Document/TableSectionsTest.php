<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Internal\Layout\Table\TableSections;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableSectionsTest extends TestCase
{
    #[Test]
    public function it_tracks_configured_sections_and_render_progress(): void
    {
        $sections = new TableSections();

        self::assertTrue($sections->canConfigureCaption());
        self::assertTrue($sections->canAddHeaderRows());
        self::assertFalse($sections->hasRepeatingHeaderRows());
        self::assertFalse($sections->hasFooterRows());
        self::assertFalse($sections->isCaptionRendered());
        self::assertFalse($sections->areFootersRendered());

        $sections->addRepeatingHeaderRow(['Header']);
        $sections->markRowsAdded();
        $sections->markBodyRowsAdded();
        $sections->addFooterRow(['Footer']);
        $sections->markCaptionRendered();
        $sections->markFootersRendered();

        self::assertSame([['Header']], $sections->repeatingHeaderRows());
        self::assertSame([['Footer']], $sections->footerRows());
        self::assertTrue($sections->hasRepeatingHeaderRows());
        self::assertTrue($sections->hasFooterRows());
        self::assertFalse($sections->canConfigureCaption());
        self::assertFalse($sections->canAddHeaderRows());
        self::assertTrue($sections->isCaptionRendered());
        self::assertTrue($sections->areFootersRendered());
    }
}
