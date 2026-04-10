<?php

declare(strict_types=1);

namespace Kalle\Pdf\Action;

use InvalidArgumentException;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\StringType;

final readonly class ImportDataAction implements ButtonAction
{
    public function __construct(
        private string $file,
    ) {
        if ($this->file === '') {
            throw new InvalidArgumentException('Import data action file must not be empty.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('ImportData'),
            'F' => new StringType($this->file),
        ]);
    }
}
