<?php

declare(strict_types=1);

namespace Kalle\Pdf\Feature\Annotation;

use Kalle\Pdf\Object\IndirectObject;

interface HasRelatedObjects
{
    /**
     * @return list<IndirectObject>
     */
    public function getRelatedObjects(): array;
}
