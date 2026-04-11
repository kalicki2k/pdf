<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ArabicPresentationForms
{
    /**
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        'ا' => [
            'isolated' => 'ﺍ',
            'final' => 'ﺎ',
        ],
        'ب' => [
            'isolated' => 'ﺏ',
            'final' => 'ﺐ',
            'initial' => 'ﺑ',
            'medial' => 'ﺒ',
        ],
        'ت' => [
            'isolated' => 'ﺕ',
            'final' => 'ﺖ',
            'initial' => 'ﺗ',
            'medial' => 'ﺘ',
        ],
        'ث' => [
            'isolated' => 'ﺙ',
            'final' => 'ﺚ',
            'initial' => 'ﺛ',
            'medial' => 'ﺜ',
        ],
        'ج' => [
            'isolated' => 'ﺝ',
            'final' => 'ﺞ',
            'initial' => 'ﺟ',
            'medial' => 'ﺠ',
        ],
        'ح' => [
            'isolated' => 'ﺡ',
            'final' => 'ﺢ',
            'initial' => 'ﺣ',
            'medial' => 'ﺤ',
        ],
        'خ' => [
            'isolated' => 'ﺥ',
            'final' => 'ﺦ',
            'initial' => 'ﺧ',
            'medial' => 'ﺨ',
        ],
        'د' => [
            'isolated' => 'ﺩ',
            'final' => 'ﺪ',
        ],
        'ر' => [
            'isolated' => 'ﺭ',
            'final' => 'ﺮ',
        ],
        'س' => [
            'isolated' => 'ﺱ',
            'final' => 'ﺲ',
            'initial' => 'ﺳ',
            'medial' => 'ﺴ',
        ],
        'ش' => [
            'isolated' => 'ﺵ',
            'final' => 'ﺶ',
            'initial' => 'ﺷ',
            'medial' => 'ﺸ',
        ],
        'ص' => [
            'isolated' => 'ﺹ',
            'final' => 'ﺺ',
            'initial' => 'ﺻ',
            'medial' => 'ﺼ',
        ],
        'ض' => [
            'isolated' => 'ﺽ',
            'final' => 'ﺾ',
            'initial' => 'ﺿ',
            'medial' => 'ﻀ',
        ],
        'ط' => [
            'isolated' => 'ﻁ',
            'final' => 'ﻂ',
            'initial' => 'ﻃ',
            'medial' => 'ﻄ',
        ],
        'ظ' => [
            'isolated' => 'ﻅ',
            'final' => 'ﻆ',
            'initial' => 'ﻇ',
            'medial' => 'ﻈ',
        ],
        'ع' => [
            'isolated' => 'ﻉ',
            'final' => 'ﻊ',
            'initial' => 'ﻋ',
            'medial' => 'ﻌ',
        ],
        'غ' => [
            'isolated' => 'ﻍ',
            'final' => 'ﻎ',
            'initial' => 'ﻏ',
            'medial' => 'ﻐ',
        ],
        'ف' => [
            'isolated' => 'ﻑ',
            'final' => 'ﻒ',
            'initial' => 'ﻓ',
            'medial' => 'ﻔ',
        ],
        'ق' => [
            'isolated' => 'ﻕ',
            'final' => 'ﻖ',
            'initial' => 'ﻗ',
            'medial' => 'ﻘ',
        ],
        'ك' => [
            'isolated' => 'ﻙ',
            'final' => 'ﻚ',
            'initial' => 'ﻛ',
            'medial' => 'ﻜ',
        ],
        'ل' => [
            'isolated' => 'ﻝ',
            'final' => 'ﻞ',
            'initial' => 'ﻟ',
            'medial' => 'ﻠ',
        ],
        'م' => [
            'isolated' => 'ﻡ',
            'final' => 'ﻢ',
            'initial' => 'ﻣ',
            'medial' => 'ﻤ',
        ],
        'ن' => [
            'isolated' => 'ﻥ',
            'final' => 'ﻦ',
            'initial' => 'ﻧ',
            'medial' => 'ﻨ',
        ],
        'ه' => [
            'isolated' => 'ﻩ',
            'final' => 'ﻪ',
            'initial' => 'ﻫ',
            'medial' => 'ﻬ',
        ],
        'و' => [
            'isolated' => 'ﻭ',
            'final' => 'ﻮ',
        ],
        'ي' => [
            'isolated' => 'ﻱ',
            'final' => 'ﻲ',
            'initial' => 'ﻳ',
            'medial' => 'ﻴ',
        ],
    ];

    public function glyphCharacter(string $character, ArabicJoiningForm $form): string
    {
        return self::MAP[$character][$form->value] ?? $character;
    }
}
