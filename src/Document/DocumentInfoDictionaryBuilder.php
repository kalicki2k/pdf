<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTimeImmutable;

use function implode;
use function substr;

final readonly class DocumentInfoDictionaryBuilder
{
    public function __construct(
        private PdfTextStringEncoder $textStringEncoder = new PdfTextStringEncoder(),
    ) {
    }

    public function build(Document $document, DateTimeImmutable $serializedAt): string
    {
        $entries = [];

        if ($document->title !== null) {
            $entries[] = '/Title ' . $this->pdfString($document->title);
        }

        if ($document->author !== null) {
            $entries[] = '/Author ' . $this->pdfString($document->author);
        }

        if ($document->subject !== null) {
            $entries[] = '/Subject ' . $this->pdfString($document->subject);
        }

        if ($document->keywords !== null) {
            $entries[] = '/Keywords ' . $this->pdfString($document->keywords);
        }

        if ($document->creator !== null) {
            $entries[] = '/Creator ' . $this->pdfString($document->creator);
        }

        if ($document->creatorTool !== null) {
            $entries[] = '/Producer ' . $this->pdfString($document->creatorTool);
        }

        $pdfDate = $this->pdfDate($serializedAt);
        $entries[] = '/CreationDate ' . $this->pdfString($pdfDate);
        $entries[] = '/ModDate ' . $this->pdfString($pdfDate);

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function pdfDate(DateTimeImmutable $timestamp): string
    {
        $offset = $timestamp->format('O');

        if ($offset === '+0000') {
            return 'D:' . $timestamp->format('YmdHis') . 'Z';
        }

        return 'D:' . $timestamp->format('YmdHis')
            . substr($offset, 0, 3)
            . "'"
            . substr($offset, 3, 2)
            . "'";
    }

    private function pdfString(string $value): string
    {
        return $this->textStringEncoder->encodeLiteral($value);
    }
}
