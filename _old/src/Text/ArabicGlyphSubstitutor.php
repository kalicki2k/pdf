<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

final readonly class ArabicGlyphSubstitutor
{
    /**
     * @var array<string, array<string, ArabicGlyphSubstitution>>
     */
    private array $ligatureForms;

    /**
     * @var array<string, array<string, string>>
     */
    private array $presentationGlyphNames;

    public function __construct(
        private ArabicPresentationForms $presentationForms = new ArabicPresentationForms(),
        private ArabicLigatureForms $ligatureFormsData = new ArabicLigatureForms(),
    ) {
        $this->presentationGlyphNames = [
            'ا' => [
                'isolated' => 'arabic.alef.isolated',
                'final' => 'arabic.alef.final',
            ],
            'ب' => [
                'isolated' => 'arabic.beh.isolated',
                'final' => 'arabic.beh.final',
                'initial' => 'arabic.beh.initial',
                'medial' => 'arabic.beh.medial',
            ],
            'ت' => [
                'isolated' => 'arabic.teh.isolated',
                'final' => 'arabic.teh.final',
                'initial' => 'arabic.teh.initial',
                'medial' => 'arabic.teh.medial',
            ],
            'ث' => [
                'isolated' => 'arabic.theh.isolated',
                'final' => 'arabic.theh.final',
                'initial' => 'arabic.theh.initial',
                'medial' => 'arabic.theh.medial',
            ],
            'ج' => [
                'isolated' => 'arabic.jeem.isolated',
                'final' => 'arabic.jeem.final',
                'initial' => 'arabic.jeem.initial',
                'medial' => 'arabic.jeem.medial',
            ],
            'ح' => [
                'isolated' => 'arabic.hah.isolated',
                'final' => 'arabic.hah.final',
                'initial' => 'arabic.hah.initial',
                'medial' => 'arabic.hah.medial',
            ],
            'خ' => [
                'isolated' => 'arabic.khah.isolated',
                'final' => 'arabic.khah.final',
                'initial' => 'arabic.khah.initial',
                'medial' => 'arabic.khah.medial',
            ],
            'د' => [
                'isolated' => 'arabic.dal.isolated',
                'final' => 'arabic.dal.final',
            ],
            'ر' => [
                'isolated' => 'arabic.reh.isolated',
                'final' => 'arabic.reh.final',
            ],
            'س' => [
                'isolated' => 'arabic.seen.isolated',
                'final' => 'arabic.seen.final',
                'initial' => 'arabic.seen.initial',
                'medial' => 'arabic.seen.medial',
            ],
            'ش' => [
                'isolated' => 'arabic.sheen.isolated',
                'final' => 'arabic.sheen.final',
                'initial' => 'arabic.sheen.initial',
                'medial' => 'arabic.sheen.medial',
            ],
            'ص' => [
                'isolated' => 'arabic.sad.isolated',
                'final' => 'arabic.sad.final',
                'initial' => 'arabic.sad.initial',
                'medial' => 'arabic.sad.medial',
            ],
            'ض' => [
                'isolated' => 'arabic.dad.isolated',
                'final' => 'arabic.dad.final',
                'initial' => 'arabic.dad.initial',
                'medial' => 'arabic.dad.medial',
            ],
            'ط' => [
                'isolated' => 'arabic.tah.isolated',
                'final' => 'arabic.tah.final',
                'initial' => 'arabic.tah.initial',
                'medial' => 'arabic.tah.medial',
            ],
            'ظ' => [
                'isolated' => 'arabic.zah.isolated',
                'final' => 'arabic.zah.final',
                'initial' => 'arabic.zah.initial',
                'medial' => 'arabic.zah.medial',
            ],
            'ع' => [
                'isolated' => 'arabic.ain.isolated',
                'final' => 'arabic.ain.final',
                'initial' => 'arabic.ain.initial',
                'medial' => 'arabic.ain.medial',
            ],
            'غ' => [
                'isolated' => 'arabic.ghain.isolated',
                'final' => 'arabic.ghain.final',
                'initial' => 'arabic.ghain.initial',
                'medial' => 'arabic.ghain.medial',
            ],
            'ف' => [
                'isolated' => 'arabic.feh.isolated',
                'final' => 'arabic.feh.final',
                'initial' => 'arabic.feh.initial',
                'medial' => 'arabic.feh.medial',
            ],
            'ق' => [
                'isolated' => 'arabic.qaf.isolated',
                'final' => 'arabic.qaf.final',
                'initial' => 'arabic.qaf.initial',
                'medial' => 'arabic.qaf.medial',
            ],
            'ك' => [
                'isolated' => 'arabic.kaf.isolated',
                'final' => 'arabic.kaf.final',
                'initial' => 'arabic.kaf.initial',
                'medial' => 'arabic.kaf.medial',
            ],
            'ل' => [
                'isolated' => 'arabic.lam.isolated',
                'final' => 'arabic.lam.final',
                'initial' => 'arabic.lam.initial',
                'medial' => 'arabic.lam.medial',
            ],
            'م' => [
                'isolated' => 'arabic.meem.isolated',
                'final' => 'arabic.meem.final',
                'initial' => 'arabic.meem.initial',
                'medial' => 'arabic.meem.medial',
            ],
            'ن' => [
                'isolated' => 'arabic.noon.isolated',
                'final' => 'arabic.noon.final',
                'initial' => 'arabic.noon.initial',
                'medial' => 'arabic.noon.medial',
            ],
            'ه' => [
                'isolated' => 'arabic.heh.isolated',
                'final' => 'arabic.heh.final',
                'initial' => 'arabic.heh.initial',
                'medial' => 'arabic.heh.medial',
            ],
            'و' => [
                'isolated' => 'arabic.waw.isolated',
                'final' => 'arabic.waw.final',
            ],
            'ي' => [
                'isolated' => 'arabic.yeh.isolated',
                'final' => 'arabic.yeh.final',
                'initial' => 'arabic.yeh.initial',
                'medial' => 'arabic.yeh.medial',
            ],
        ];

        $this->ligatureForms = [
            'لا' => [
                'isolated' => new ArabicGlyphSubstitution('ﻻ', 'arabic.lam_alef.isolated'),
                'final' => new ArabicGlyphSubstitution('ﻼ', 'arabic.lam_alef.final'),
            ],
            'لأ' => [
                'isolated' => new ArabicGlyphSubstitution('ﻷ', 'arabic.lam_alef_hamza_above.isolated'),
                'final' => new ArabicGlyphSubstitution('ﻸ', 'arabic.lam_alef_hamza_above.final'),
            ],
            'لإ' => [
                'isolated' => new ArabicGlyphSubstitution('ﻹ', 'arabic.lam_alef_hamza_below.isolated'),
                'final' => new ArabicGlyphSubstitution('ﻺ', 'arabic.lam_alef_hamza_below.final'),
            ],
            'لآ' => [
                'isolated' => new ArabicGlyphSubstitution('ﻵ', 'arabic.lam_alef_madda_above.isolated'),
                'final' => new ArabicGlyphSubstitution('ﻶ', 'arabic.lam_alef_madda_above.final'),
            ],
        ];
    }

    public function presentationForm(string $character, ArabicJoiningForm $form): ArabicGlyphSubstitution
    {
        return new ArabicGlyphSubstitution(
            $this->presentationForms->glyphCharacter($character, $form),
            $this->presentationGlyphNames[$character][$form->value] ?? 'unicode.' . $character,
        );
    }

    public function lamAlefLigature(string $pair, ArabicJoiningForm $form): ?ArabicGlyphSubstitution
    {
        if ($form !== ArabicJoiningForm::ISOLATED && $form !== ArabicJoiningForm::FINAL) {
            return null;
        }

        $ligatureCharacter = $this->ligatureFormsData->lamAlefLigature($pair, $form);

        if ($ligatureCharacter === null) {
            return null;
        }

        return $this->ligatureForms[$pair][$form->value] ?? new ArabicGlyphSubstitution(
            $ligatureCharacter,
            'arabic.lam_alef',
        );
    }
}
