<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Page\LinkTarget;

final readonly class TextLink
{
    public function __construct(
        public LinkTarget $target,
        public ?string $contents = null,
        public ?string $accessibleLabel = null,
        public ?string $groupKey = null,
    ) {
    }

    public static function externalUrl(
        string $url,
        ?string $contents = null,
        ?string $accessibleLabel = null,
        ?string $groupKey = null,
    ): self {
        return new self(LinkTarget::externalUrl($url), $contents, $accessibleLabel, $groupKey);
    }

    public static function namedDestination(
        string $name,
        ?string $contents = null,
        ?string $accessibleLabel = null,
        ?string $groupKey = null,
    ): self {
        return new self(LinkTarget::namedDestination($name), $contents, $accessibleLabel, $groupKey);
    }

    public static function page(
        int $pageNumber,
        ?string $contents = null,
        ?string $accessibleLabel = null,
        ?string $groupKey = null,
    ): self {
        return new self(LinkTarget::page($pageNumber), $contents, $accessibleLabel, $groupKey);
    }

    public static function position(
        int $pageNumber,
        float $x,
        float $y,
        ?string $contents = null,
        ?string $accessibleLabel = null,
        ?string $groupKey = null,
    ): self {
        return new self(LinkTarget::position($pageNumber, $x, $y), $contents, $accessibleLabel, $groupKey);
    }
}
