<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function sprintf;

use InvalidArgumentException;

final readonly class AnnotationAppearanceRenderContext
{
    /**
     * @param array<string, int> $fontObjectIdsByAlias
     */
    public function __construct(
        public array $fontObjectIdsByAlias = [],
    ) {
    }

    public function fontObjectId(string $fontAlias): int
    {
        $fontObjectId = $this->fontObjectIdsByAlias[$fontAlias] ?? null;

        if ($fontObjectId === null) {
            throw new InvalidArgumentException(sprintf(
                'Appearance stream font alias "%s" does not exist.',
                $fontAlias,
            ));
        }

        return $fontObjectId;
    }
}
