<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Page;

use InvalidArgumentException;
use Kalle\Pdf\Page\LinkTarget;
use PHPUnit\Framework\TestCase;

final class LinkTargetTest extends TestCase
{
    public function testItBuildsExternalUrlTargets(): void
    {
        $target = LinkTarget::externalUrl('https://example.com');

        self::assertTrue($target->isExternalUrl());
        self::assertSame('https://example.com', $target->externalUrlValue());
    }

    public function testItBuildsInternalPageTargets(): void
    {
        $pageTarget = LinkTarget::page(2);
        $positionTarget = LinkTarget::position(3, 40, 500);

        self::assertTrue($pageTarget->isPage());
        self::assertSame(2, $pageTarget->pageNumberValue());
        self::assertTrue($positionTarget->isPosition());
        self::assertSame(3, $positionTarget->pageNumberValue());
        self::assertSame(40.0, $positionTarget->xValue());
        self::assertSame(500.0, $positionTarget->yValue());
    }

    public function testItRejectsInvalidPageNumbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target page number must be greater than zero.');

        LinkTarget::page(0);
    }
}
