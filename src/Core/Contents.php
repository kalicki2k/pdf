<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use Kalle\Pdf\Types\Dictionary;

final class Contents extends IndirectObject
{
    /** @var Element[] */
    private array $elements = [];

    public function addElement(Element $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    public function render(): string
    {
        $contents = implode(
            PHP_EOL,
            array_map(static fn (Element $element): string => $element->render(), $this->elements),
        );

        $dictionary = new Dictionary([
            'Length' => strlen($contents),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $contents . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
