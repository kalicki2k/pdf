<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Annotation;

use Kalle\Pdf\Annotation\PageAnnotation as PublicPageAnnotation;

interface PageAnnotation extends PublicPageAnnotation, HasRelatedObjects
{
}
