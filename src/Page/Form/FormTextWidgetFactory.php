<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Form;

use InvalidArgumentException;
use Kalle\Pdf\Action\ButtonAction;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Font\FontDefinition;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Layout\Geometry\Rect;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Page\Annotation\PushButtonAnnotation;
use Kalle\Pdf\Page\Annotation\TextFieldAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Style\Color;

final readonly class FormTextWidgetFactory
{
    public function __construct(
        private Page $page,
        private FormWidgetFactoryContext $context,
        private UnicodeFontWidthUpdater $unicodeFontWidthUpdater,
    ) {
    }

    public function createTextField(
        string $name,
        Rect $box,
        ?string $value,
        string $baseFont,
        int $size,
        bool $multiline,
        ?Color $textColor,
        ?FormFieldFlags $flags,
        ?string $defaultValue,
        ?string $accessibleName,
    ): TextFieldAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('Text field name must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Text field');

        if ($size <= 0) {
            throw new InvalidArgumentException('Text field font size must be greater than zero.');
        }

        [$font, $fontResourceName] = $this->prepareAcroFormFont($baseFont, $this->context->ensureTextFieldAcroForm());

        return new TextFieldAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $value,
            $fontResourceName,
            $size,
            $multiline,
            $flags,
            $textColor,
            $defaultValue,
            $accessibleName,
            new FormFieldTextAppearanceStream(
                $this->context->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                $this->resolveTextFieldAppearanceLines($value, $multiline),
                $textColor,
                HorizontalAlign::LEFT,
                $multiline ? VerticalAlign::TOP : VerticalAlign::MIDDLE,
            ),
        );
    }

    public function createPushButton(
        string $name,
        string $label,
        Rect $box,
        string $baseFont,
        int $size,
        ?Color $textColor,
        ?ButtonAction $action,
        ?string $accessibleName,
    ): PushButtonAnnotation {
        if ($name === '') {
            throw new InvalidArgumentException('Push button name must not be empty.');
        }

        if ($label === '') {
            throw new InvalidArgumentException('Push button label must not be empty.');
        }

        $this->assertRectHasPositiveDimensions($box, 'Push button');

        if ($size <= 0) {
            throw new InvalidArgumentException('Push button font size must be greater than zero.');
        }

        [$font, $fontResourceName] = $this->prepareAcroFormFont($baseFont, $this->context->ensurePushButtonAcroForm());

        return new PushButtonAnnotation(
            $this->context->nextObjectId(),
            $this->page,
            $box->x,
            $box->y,
            $box->width,
            $box->height,
            $name,
            $label,
            $fontResourceName,
            $size,
            $textColor,
            $action,
            $accessibleName,
            new FormFieldTextAppearanceStream(
                $this->context->nextObjectId(),
                $box->width,
                $box->height,
                $font,
                $this->unicodeFontWidthUpdater,
                $fontResourceName,
                $size,
                [$label],
                $textColor,
                HorizontalAlign::CENTER,
                VerticalAlign::MIDDLE,
            ),
        );
    }

    private function assertRectHasPositiveDimensions(Rect $box, string $subject): void
    {
        if ($box->width <= 0) {
            throw new InvalidArgumentException("$subject width must be greater than zero.");
        }

        if ($box->height <= 0) {
            throw new InvalidArgumentException("$subject height must be greater than zero.");
        }
    }

    /**
     * @return array{0: FontDefinition&IndirectObject, 1: string}
     */
    private function prepareAcroFormFont(string $baseFont, AcroForm $acroForm): array
    {
        $font = $this->context->resolveFont($baseFont);

        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        return [$font, $acroForm->registerFont($font)];
    }

    /**
     * @return list<string>
     */
    private function resolveTextFieldAppearanceLines(?string $value, bool $multiline): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!$multiline) {
            return [$value];
        }

        return preg_split('/\R/u', $value) ?: [];
    }
}
