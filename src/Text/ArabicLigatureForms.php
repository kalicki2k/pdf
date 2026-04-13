<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ArabicLigatureForms
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array LAM_ALEF_MAP = [
        'لا' => [
            'isolated' => 'ﻻ',
            'final' => 'ﻼ',
        ],
        'لأ' => [
            'isolated' => 'ﻷ',
            'final' => 'ﻸ',
        ],
        'لإ' => [
            'isolated' => 'ﻹ',
            'final' => 'ﻺ',
        ],
        'لآ' => [
            'isolated' => 'ﻵ',
            'final' => 'ﻶ',
        ],
    ];

    public function lamAlefLigature(string $pair, ArabicJoiningForm $form): ?string
    {
        if ($form !== ArabicJoiningForm::ISOLATED && $form !== ArabicJoiningForm::FINAL) {
            return null;
        }

        return self::LAM_ALEF_MAP[$pair][$form->value] ?? null;
    }
}
