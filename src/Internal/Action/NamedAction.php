<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Action;

use InvalidArgumentException;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;

final readonly class NamedAction implements ButtonAction
{
    private const ALLOWED_NAMES = [
        'NextPage',
        'PrevPage',
        'FirstPage',
        'LastPage',
    ];

    public function __construct(
        private string $name,
    ) {
        if (!in_array($this->name, self::ALLOWED_NAMES, true)) {
            throw new InvalidArgumentException('Named action must be one of: NextPage, PrevPage, FirstPage, LastPage.');
        }
    }

    public function toPdfDictionary(): DictionaryType
    {
        return new DictionaryType([
            'S' => new NameType('Named'),
            'N' => new NameType($this->name),
        ]);
    }
}
