<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\TextField;

final class PdfA4FormFieldPolicy
{
    public function supports(FormField $field): bool
    {
        return $field instanceof TextField
            || $field instanceof CheckboxField
            || $field instanceof RadioButtonGroup
            || $field instanceof ComboBoxField
            || $field instanceof ListBoxField
            || ($field instanceof PushButtonField && $field->optionalContentStateAction !== null);
    }

    public function violationMessage(Profile $profile): string
    {
        $allowedFields = $profile->pdfaConformance() === 'E'
            ? 'text fields, checkboxes, radio buttons, choice fields and optional-content state push buttons'
            : 'text fields, checkboxes, radio buttons and choice fields';

        return sprintf(
            'Profile %s only allows %s in the %s.',
            $profile->name(),
            $allowedFields,
            $profile->pdfaConformance() === 'E'
                ? 'current constrained PDF/A-4e form policy'
                : 'current PDF/A-4 form policy',
        );
    }
}
