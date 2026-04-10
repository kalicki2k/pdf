<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document;

use InvalidArgumentException;
use Kalle\Pdf\Feature\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Internal\Document\Document;

/**
 * @internal Manages optional content groups (layers) for a document.
 */
class DocumentOptionalContentManager
{
    /** @var array<string, OptionalContentGroup> */
    private array $optionalContentGroups;

    /**
     * @param array<string, OptionalContentGroup> $optionalContentGroups
     */
    public function __construct(
        private readonly Document $document,
        array &$optionalContentGroups,
    ) {
        $this->optionalContentGroups = & $optionalContentGroups;
    }

    public function ensureOptionalContentGroup(string $name, bool $visibleByDefault = true): OptionalContentGroup
    {
        $this->document->assertAllowsOptionalContentGroups();

        if ($name === '') {
            throw new InvalidArgumentException('Optional content group name must not be empty.');
        }

        if (isset($this->optionalContentGroups[$name])) {
            return $this->optionalContentGroups[$name];
        }

        $group = new OptionalContentGroup($this->document->getUniqObjectId(), $name, $visibleByDefault);
        $this->optionalContentGroups[$name] = $group;

        return $group;
    }

    /**
     * @return list<OptionalContentGroup>
     */
    public function getOptionalContentGroups(): array
    {
        return array_values($this->optionalContentGroups);
    }
}
