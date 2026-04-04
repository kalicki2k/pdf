<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTime;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\StringValue;

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
        $dictionary = new Dictionary([
            'Title' => new StringValue($this->document->title ?? ''),
            'Author' => new StringValue($this->document->author ?? ''),
            'Creator' => new StringValue($this->producer),
            'Producer' => new StringValue($this->producer),
            'CreationDate' => new StringValue('D:' . $this->creationDate),
        ]);

        if (!empty($this->document->subject)) {
            $dictionary->add('Subject', new StringValue($this->document->subject));
        }

        if (!empty($this->document->keywords)) {
            $dictionary->add('Keywords', new StringValue(implode(', ', $this->document->keywords)));
        }

        if ($this->document->version >= 1.4) {
            $dictionary->add('Lang', new StringValue($this->document->language ?? ''));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
