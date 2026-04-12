<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

interface SupportsPopupAnnotation
{
    public function withPopup(PopupAnnotationDefinition $popup): self;
}
