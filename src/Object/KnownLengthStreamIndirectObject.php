<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\PdfOutput;

abstract class KnownLengthStreamIndirectObject extends AbstractStreamIndirectObject
{
    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary($this->knownStreamLength())->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeStreamContents($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedOutput = new EncryptingPdfOutput(
            $output,
            $objectEncryptor->createStreamEncryptor($this->id),
        );
        $encryptedLength = $objectEncryptor->encryptedByteLength($this->knownStreamLength());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $this->streamDictionary($encryptedLength)->write($output);
        $output->write(PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeStreamContents($encryptedOutput);
        $encryptedOutput->finish();
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    abstract protected function streamDictionary(int | ReferenceType $length): DictionaryType;

    abstract protected function knownStreamLength(): int;

    abstract protected function writeStreamContents(PdfOutput $output): void;
}
