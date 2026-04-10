<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Page\Page as InternalPage;
use Kalle\Pdf\Page;
use WeakMap;

final class PageRegistry
{
    /** @var WeakMap<Page, InternalPage>|null */
    private static ?WeakMap $pages = null;

    public static function register(Page $publicPage, InternalPage $internalPage): void
    {
        self::$pages ??= new WeakMap();
        self::$pages[$publicPage] = $internalPage;
    }

    public static function resolve(Page $publicPage): InternalPage
    {
        if (!isset(self::$pages[$publicPage])) {
            throw new InvalidArgumentException('The provided page does not belong to this library API.');
        }

        return self::$pages[$publicPage];
    }
}
