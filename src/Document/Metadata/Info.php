<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use DateTimeImmutable;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\StringType;

class Info extends DictionaryIndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Title' => new StringType($this->document->getTitle() ?? ''),
            'Author' => new StringType($this->document->getAuthor() ?? ''),
            'Creator' => new StringType($this->document->getCreator()),
            'Producer' => new StringType($this->document->getProducer()),
            'CreationDate' => new StringType($this->formatPdfDate($this->document->getCreationDate())),
            'ModDate' => new StringType($this->formatPdfDate($this->document->getModificationDate())),
        ]);

        if (!empty($this->document->getSubject())) {
            $dictionary->add('Subject', new StringType($this->document->getSubject()));
        }

        $keywords = $this->document->getKeywords();

        if ($keywords !== []) {
            $dictionary->add('Keywords', new StringType(implode(', ', $keywords)));
        }

        return $dictionary;
    }

    private function formatPdfDate(DateTimeImmutable $date): string
    {
        $offset = $date->format('O');
        $normalizedOffset = substr($offset, 0, 3) . "'" . substr($offset, 3, 2) . "'";

        return 'D:' . $date->format('YmdHis') . $normalizedOffset;
    }
}
