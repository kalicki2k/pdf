<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

interface HasDeferredStreamLengthObject
{
    public function prepareLengthObject(int $id): StreamLengthObject;

    public function getLengthObject(): ?StreamLengthObject;
}
