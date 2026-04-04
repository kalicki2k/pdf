<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTime;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\StringType;

final class Info extends IndirectObject
{
    private string $producer;
    private string $creationDate;

    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);

        $this->producer = 'kalle/pdf';
        $this->creationDate = new DateTime()->format('YmdHis');
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Title' => new StringType($this->document->title ?? ''),
            'Author' => new StringType($this->document->author ?? ''),
            'Creator' => new StringType($this->producer),
            'Producer' => new StringType($this->producer),
            'CreationDate' => new StringType('D:' . $this->creationDate),
        ]);

        if (!empty($this->document->subject)) {
            $dictionary->add('Subject', new StringType($this->document->subject));
        }

        if (!empty($this->document->keywords)) {
            $dictionary->add('Keywords', new StringType(implode(', ', $this->document->keywords)));
        }

        if ($this->document->version >= 1.4) {
            $dictionary->add('Lang', new StringType($this->document->language ?? ''));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
