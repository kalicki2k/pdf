<?php

declare(strict_types=1);

namespace Kalle\Pdf\Application\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Outline\OutlineItem;
use Kalle\Pdf\Document\Outline\OutlineRoot;
use Kalle\Pdf\Document\Page;

/**
 * @internal Manages document outlines and named destinations.
 */
class DocumentNavigationManager
{
    /** @var array<string, Page> */
    private array $destinations;

    /**
     * @param array<string, Page> $destinations
     */
    public function __construct(
        private readonly Document $document,
        array &$destinations,
    ) {
        $this->destinations = & $destinations;
    }

    public function addOutline(string $title, Page $page): void
    {
        if ($title === '') {
            throw new InvalidArgumentException('Outline title must not be empty.');
        }

        $this->document->outlineRoot ??= new OutlineRoot($this->document->getUniqObjectId());
        $this->document->outlineRoot->addItem(new OutlineItem(
            $this->document->getUniqObjectId(),
            $this->document->outlineRoot,
            $title,
            $page,
        ));
    }

    public function addDestination(string $name, Page $page): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Destination name must not be empty.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            throw new InvalidArgumentException('Destination name may contain only letters, numbers, dots, underscores and hyphens.');
        }

        $this->destinations[$name] = $page;
    }

    /**
     * @return array<string, Page>
     */
    public function getDestinations(): array
    {
        return $this->destinations;
    }
}
