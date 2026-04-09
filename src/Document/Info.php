<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
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
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
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

        return $this->renderDictionaryObject($dictionary, $encryptor);
    }

    private function formatPdfDate(\DateTimeImmutable $date): string
    {
        $offset = $date->format('O');
        $normalizedOffset = substr($offset, 0, 3) . "'" . substr($offset, 3, 2) . "'";

        return 'D:' . $date->format('YmdHis') . $normalizedOffset;
    }
}
