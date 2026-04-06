<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\StringType;

final class Info extends IndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Title' => new StringType($this->document->getTitle() ?? ''),
            'Author' => new StringType($this->document->getAuthor() ?? ''),
            'Creator' => new StringType($this->document->getCreator()),
            'Producer' => new StringType($this->document->getProducer()),
            'CreationDate' => new StringType('D:' . $this->document->getCreationDate()->format('YmdHisO')),
            'ModDate' => new StringType('D:' . $this->document->getModificationDate()->format('YmdHisO')),
        ]);

        if (!empty($this->document->getSubject())) {
            $dictionary->add('Subject', new StringType($this->document->getSubject()));
        }

        $keywords = $this->document->getKeywords();

        if ($keywords !== []) {
            $dictionary->add('Keywords', new StringType(implode(', ', $keywords)));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
