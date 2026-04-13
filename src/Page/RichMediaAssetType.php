<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

enum RichMediaAssetType: string
{
    case VIDEO = 'Video';
    case SOUND = 'Sound';
}
