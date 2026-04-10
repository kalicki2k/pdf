<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Form;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Font\FontDefinition;
use Kalle\Pdf\Internal\Object\DictionaryIndirectObject;
use Kalle\Pdf\Internal\Object\IndirectObject;
use Kalle\Pdf\Internal\Page\Annotation\PageAnnotation;
use Kalle\Pdf\Internal\PdfType\ArrayType;
use Kalle\Pdf\Internal\PdfType\BooleanType;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\ReferenceType;

final class AcroForm extends DictionaryIndirectObject
{
    /** @var list<IndirectObject> */
    private array $fields = [];
    /** @var array<string, RadioButtonField> */
    private array $radioGroups = [];
    /** @var array<string, FontDefinition&IndirectObject> */
    private array $fonts = [];
    /** @var array<string, string> */
    private array $fontResourceNames = [];

    public function addField(IndirectObject $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    public function registerFont(FontDefinition $font): string
    {
        if (!$font instanceof IndirectObject) {
            throw new InvalidArgumentException('AcroForm fonts must be indirect objects.');
        }

        $baseFont = $font->getBaseFont();

        if (isset($this->fontResourceNames[$baseFont])) {
            return $this->fontResourceNames[$baseFont];
        }

        $resourceName = 'F' . (count($this->fonts) + 1);
        $this->fonts[$baseFont] = $font;
        $this->fontResourceNames[$baseFont] = $resourceName;

        return $resourceName;
    }

    /**
     * @return list<IndirectObject>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    protected function dictionary(): DictionaryType
    {
        $fonts = new DictionaryType([]);

        foreach ($this->fonts as $baseFont => $font) {
            $fonts->add($this->fontResourceNames[$baseFont], new ReferenceType($font));
        }

        $dictionary = new DictionaryType([
            'Fields' => new ArrayType(array_map(
                static fn (IndirectObject $field): ReferenceType => new ReferenceType($field),
                $this->fields,
            )),
            'NeedAppearances' => new BooleanType(true),
        ]);

        if ($this->fonts !== []) {
            $dictionary->add('DR', new DictionaryType([
                'Font' => $fonts,
            ]));
        }

        return $dictionary;
    }

    public function getOrCreateRadioGroup(string $name, int $id): RadioButtonField
    {
        if (isset($this->radioGroups[$name])) {
            return $this->radioGroups[$name];
        }

        $field = new RadioButtonField($id, $name);
        $this->radioGroups[$name] = $field;
        $this->fields[] = $field;

        return $field;
    }

    /**
     * @return list<IndirectObject>
     */
    public function getFieldObjectsForRender(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (IndirectObject $field): bool => !$field instanceof PageAnnotation,
        ));
    }
}
