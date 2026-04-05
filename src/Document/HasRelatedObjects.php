<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;

interface HasRelatedObjects
{
    /**
     * @return list<IndirectObject>
     */
    public function getRelatedObjects(): array;
}
