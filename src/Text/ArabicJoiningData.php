<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ArabicJoiningData
{
    /**
     * @var array<string, ArabicJoiningType>
     */
    private const array MAP = [
        'ا' => ArabicJoiningType::RIGHT,
        'أ' => ArabicJoiningType::RIGHT,
        'إ' => ArabicJoiningType::RIGHT,
        'آ' => ArabicJoiningType::RIGHT,
        'د' => ArabicJoiningType::RIGHT,
        'ذ' => ArabicJoiningType::RIGHT,
        'ر' => ArabicJoiningType::RIGHT,
        'ز' => ArabicJoiningType::RIGHT,
        'و' => ArabicJoiningType::RIGHT,
        'ؤ' => ArabicJoiningType::RIGHT,
        'ة' => ArabicJoiningType::RIGHT,
        'ى' => ArabicJoiningType::RIGHT,
        'ً' => ArabicJoiningType::TRANSPARENT,
        'ٌ' => ArabicJoiningType::TRANSPARENT,
        'ٍ' => ArabicJoiningType::TRANSPARENT,
        'َ' => ArabicJoiningType::TRANSPARENT,
        'ُ' => ArabicJoiningType::TRANSPARENT,
        'ِ' => ArabicJoiningType::TRANSPARENT,
        'ّ' => ArabicJoiningType::TRANSPARENT,
        'ْ' => ArabicJoiningType::TRANSPARENT,
        'ب' => ArabicJoiningType::DUAL,
        'ت' => ArabicJoiningType::DUAL,
        'ث' => ArabicJoiningType::DUAL,
        'ج' => ArabicJoiningType::DUAL,
        'ح' => ArabicJoiningType::DUAL,
        'خ' => ArabicJoiningType::DUAL,
        'س' => ArabicJoiningType::DUAL,
        'ش' => ArabicJoiningType::DUAL,
        'ص' => ArabicJoiningType::DUAL,
        'ض' => ArabicJoiningType::DUAL,
        'ط' => ArabicJoiningType::DUAL,
        'ظ' => ArabicJoiningType::DUAL,
        'ع' => ArabicJoiningType::DUAL,
        'غ' => ArabicJoiningType::DUAL,
        'ف' => ArabicJoiningType::DUAL,
        'ق' => ArabicJoiningType::DUAL,
        'ك' => ArabicJoiningType::DUAL,
        'ل' => ArabicJoiningType::DUAL,
        'م' => ArabicJoiningType::DUAL,
        'ن' => ArabicJoiningType::DUAL,
        'ه' => ArabicJoiningType::DUAL,
        'ي' => ArabicJoiningType::DUAL,
        'ئ' => ArabicJoiningType::DUAL,
        'ـ' => ArabicJoiningType::DUAL,
    ];

    public function typeForCharacter(string $character): ArabicJoiningType
    {
        return self::MAP[$character] ?? ArabicJoiningType::NON_JOINING;
    }

    public function canJoinToPrevious(string $character): bool
    {
        return match ($this->typeForCharacter($character)) {
            ArabicJoiningType::DUAL,
            ArabicJoiningType::RIGHT => true,
            default => false,
        };
    }

    public function canJoinToNext(string $character): bool
    {
        return $this->typeForCharacter($character) === ArabicJoiningType::DUAL;
    }

    public function isTransparent(string $character): bool
    {
        return $this->typeForCharacter($character) === ArabicJoiningType::TRANSPARENT;
    }
}
