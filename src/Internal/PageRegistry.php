<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal;

use InvalidArgumentException;
use Kalle\Pdf\Page;
use WeakMap;

final class PageRegistry
{
    /** @var WeakMap<Page, \Kalle\Pdf\Document\Page>|null */
    private static ?WeakMap $pages = null;

    public static function register(Page $publicPage, \Kalle\Pdf\Document\Page $internalPage): void
    {
        self::$pages ??= new WeakMap();
        self::$pages[$publicPage] = $internalPage;
    }

    public static function resolve(Page $publicPage): \Kalle\Pdf\Document\Page
    {
        if (!isset(self::$pages[$publicPage])) {
            throw new InvalidArgumentException('The provided page does not belong to this library API.');
        }

        return self::$pages[$publicPage];
    }
}
