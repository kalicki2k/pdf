<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function array_map;
use function count;
use function implode;

use InvalidArgumentException;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;

final readonly class AcroForm
{
    /**
     * @param list<FormField> $fields
     */
    public function __construct(
        public array $fields = [],
        public bool $needAppearances = true,
    ) {
    }

    public function withField(FormField $field): self
    {
        foreach ($this->fields as $existingField) {
            if ($existingField->name === $field->name) {
                throw new InvalidArgumentException(sprintf(
                    'AcroForm field "%s" is already registered.',
                    $field->name,
                ));
            }
        }

        return new self(
            fields: [...$this->fields, $field],
            needAppearances: $this->needAppearances,
        );
    }

    public function withNeedAppearances(bool $needAppearances): self
    {
        return new self(
            fields: $this->fields,
            needAppearances: $needAppearances,
        );
    }

    public function field(string $name): ?FormField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function replacingField(FormField $field): self
    {
        $fields = [];
        $replaced = false;

        foreach ($this->fields as $existingField) {
            if ($existingField->name === $field->name) {
                $fields[] = $field;
                $replaced = true;
                continue;
            }

            $fields[] = $existingField;
        }

        if (!$replaced) {
            return $this->withField($field);
        }

        return new self(
            fields: $fields,
            needAppearances: $this->needAppearances,
        );
    }

    /**
     * @param list<int> $fieldObjectIds
     */
    public function pdfObjectContents(
        array $fieldObjectIds,
        ?int $defaultTextFontObjectId = null,
        string $defaultTextFontAlias = 'F0',
        bool $allowBuiltinDefaultTextFontFallback = true,
    ): string {
        if (count($fieldObjectIds) !== count($this->fields)) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'AcroForm field object IDs must match the registered field count.',
            );
        }

        $entries = [
            '/Fields [' . implode(' ', array_map(
                static fn (int $objectId): string => $objectId . ' 0 R',
                $fieldObjectIds,
            )) . ']',
        ];

        if ($this->needAppearances) {
            $entries[] = '/NeedAppearances true';
        }

        if ($this->hasSignatureFields()) {
            $entries[] = '/SigFlags 1';
        }

        if ($this->needsDefaultTextResources()) {
            if ($defaultTextFontObjectId !== null) {
                $entries[] = '/DR << /Font << /' . $defaultTextFontAlias . ' ' . $defaultTextFontObjectId . ' 0 R >> >>';
            } elseif (!$allowBuiltinDefaultTextFontFallback) {
                throw new DocumentValidationException(
                    DocumentBuildError::BUILD_STATE_INVALID,
                    'PDF/A form resources require an embedded default font. The built-in /Helv fallback is not allowed.',
                );
            } else {
                $entries[] = '/DR << /Font << /Helv << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >>';
            }
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function needsDefaultTextResources(): bool
    {
        return array_any($this->fields, fn ($field) => $field->needsDefaultTextResources());
    }

    private function hasSignatureFields(): bool
    {
        return array_any($this->fields, fn ($field) => $field instanceof SignatureField);
    }
}
