<?php

declare(strict_types=1);

namespace Kalle\Pdf\Pages;
final readonly class Pages
{
    /**
     * @param list<int> $kidsObjectIds
     */
    public static function make(array $kidsObjectIds): self
    {
        return new self(
            kidsObjectIds: $kidsObjectIds,
            count: count($kidsObjectIds),
        );
    }

    /**
     * @param list<int> $kidsObjectIds
     */
    private function __construct(
        public array $kidsObjectIds,
        public int   $count,
    )
    {
    }
}