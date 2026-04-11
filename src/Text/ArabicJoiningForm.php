<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

enum ArabicJoiningForm: string
{
    case ISOLATED = 'isolated';
    case INITIAL = 'initial';
    case MEDIAL = 'medial';
    case FINAL = 'final';
}
