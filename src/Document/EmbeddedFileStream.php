<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;

final class EmbeddedFileStream extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly string $contents,
        private readonly ?string $mimeType = null,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Type' => '/EmbeddedFile',
            'Length' => strlen($this->contents),
            'Params' => new DictionaryType([
                'Size' => strlen($this->contents),
            ]),
        ]);

        if ($this->mimeType !== null) {
            $dictionary->add('Subtype', '/' . str_replace('/', '#2F', $this->mimeType));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->contents . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
