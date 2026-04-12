<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

interface AppearanceStreamAnnotation
{
    public function appearanceStreamDictionaryContents(?AnnotationAppearanceRenderContext $context = null): string;

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string;
}
