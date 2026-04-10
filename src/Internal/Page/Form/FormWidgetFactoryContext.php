<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Form;

use Closure;
use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Page\Page;
use Kalle\Pdf\Internal\Page\Resources\PageFonts;
use Kalle\Pdf\Model\Document\Form\AcroForm;

/**
 * @internal Provides object ids, AcroForms and fonts for page form widget building.
 */
final class FormWidgetFactoryContext
{
    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(): AcroForm $ensureTextFieldAcroForm
     * @param Closure(): AcroForm $ensurePushButtonAcroForm
     * @param Closure(): AcroForm $ensureRadioButtonAcroForm
     * @param Closure(): AcroForm $ensureComboBoxAcroForm
     * @param Closure(): AcroForm $ensureListBoxAcroForm
     * @param Closure(string): FontDefinition $resolveFont
     */
    private function __construct(
        private readonly Closure $nextObjectId,
        private readonly Closure $ensureTextFieldAcroForm,
        private readonly Closure $ensurePushButtonAcroForm,
        private readonly Closure $ensureRadioButtonAcroForm,
        private readonly Closure $ensureComboBoxAcroForm,
        private readonly Closure $ensureListBoxAcroForm,
        private readonly Closure $resolveFont,
    ) {
    }

    public static function forPage(Page $page, PageFonts $pageFonts): self
    {
        return new self(
            fn (): int => $page->getDocument()->getUniqObjectId(),
            fn (): AcroForm => $page->getDocument()->ensureTextFieldAcroForm(),
            fn (): AcroForm => $page->getDocument()->ensurePushButtonAcroForm(),
            fn (): AcroForm => $page->getDocument()->ensureRadioButtonAcroForm(),
            fn (): AcroForm => $page->getDocument()->ensureComboBoxAcroForm(),
            fn (): AcroForm => $page->getDocument()->ensureListBoxAcroForm(),
            fn (string $baseFont): FontDefinition => $pageFonts->resolveFont($baseFont),
        );
    }

    /**
     * @param Closure(): int $nextObjectId
     * @param Closure(): AcroForm $ensureTextFieldAcroForm
     * @param Closure(): AcroForm $ensurePushButtonAcroForm
     * @param Closure(): AcroForm $ensureRadioButtonAcroForm
     * @param Closure(): AcroForm $ensureComboBoxAcroForm
     * @param Closure(): AcroForm $ensureListBoxAcroForm
     * @param Closure(string): FontDefinition $resolveFont
     */
    public static function fromCallables(
        Closure $nextObjectId,
        Closure $ensureTextFieldAcroForm,
        Closure $ensurePushButtonAcroForm,
        Closure $ensureRadioButtonAcroForm,
        Closure $ensureComboBoxAcroForm,
        Closure $ensureListBoxAcroForm,
        Closure $resolveFont,
    ): self {
        return new self(
            $nextObjectId,
            $ensureTextFieldAcroForm,
            $ensurePushButtonAcroForm,
            $ensureRadioButtonAcroForm,
            $ensureComboBoxAcroForm,
            $ensureListBoxAcroForm,
            $resolveFont,
        );
    }

    public function nextObjectId(): int
    {
        return ($this->nextObjectId)();
    }

    public function ensureTextFieldAcroForm(): AcroForm
    {
        return ($this->ensureTextFieldAcroForm)();
    }

    public function ensurePushButtonAcroForm(): AcroForm
    {
        return ($this->ensurePushButtonAcroForm)();
    }

    public function ensureRadioButtonAcroForm(): AcroForm
    {
        return ($this->ensureRadioButtonAcroForm)();
    }

    public function ensureComboBoxAcroForm(): AcroForm
    {
        return ($this->ensureComboBoxAcroForm)();
    }

    public function ensureListBoxAcroForm(): AcroForm
    {
        return ($this->ensureListBoxAcroForm)();
    }

    public function resolveFont(string $baseFont): FontDefinition
    {
        return ($this->resolveFont)($baseFont);
    }
}
