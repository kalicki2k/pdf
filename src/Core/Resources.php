<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Reference;

final class Resources extends IndirectObject
{
    /** @var FontDefinition[]  */
    private array $fonts = [];

    public function __construct(int $id)
    {
        parent::__construct($id);
    }

    public function addFont(FontDefinition $font): string
    {
        foreach ($this->fonts as $index => $registeredFont) {
            if ($registeredFont->getId() === $font->getId()) {
                return 'F' . ($index + 1);
            }
        }

        $this->fonts[] = $font;

        return 'F' . count($this->fonts);
    }

    public function render(): string
    {
        $fontReferences = [];

        foreach ($this->fonts as $index => $registeredFont) {
            $fontReferences['F' . ($index + 1)] = new Reference($registeredFont);
        }

        $dictionary = new Dictionary([
            'Font' => new Dictionary($fontReferences),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
