<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Internal\Object\IndirectObject;

interface HasRelatedObjects
{
    /**
     * @return list<IndirectObject>
     */
    public function getRelatedObjects(): array;
}
