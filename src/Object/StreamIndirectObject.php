<?php

declare(strict_types=1);

namespace Kalle\Pdf\Object;

use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Render\CountingPdfOutput;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

abstract class StreamIndirectObject extends IndirectObject implements EncryptableIndirectObject
{
    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->streamDictionary($this->streamLength())->render() . PHP_EOL);
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
        $encryptedLength = $objectEncryptor->encryptedByteLength($this->streamLength());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->streamDictionary($encryptedLength)->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeStreamContents($encryptedOutput);
        $encryptedOutput->finish();
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    protected function streamLength(): int
    {
        $counter = new CountingPdfOutput();
        $this->writeStreamContents($counter);

        return $counter->offset();
    }
    /**
     * @param list<string> $lines
     */
    protected function writeLines(PdfOutput $output, array $lines): void
    {
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $output->write(PHP_EOL);
            }

            $output->write($line);
        }
    }

    abstract protected function streamDictionary(int $length): DictionaryType;

    abstract protected function writeStreamContents(PdfOutput $output): void;
}
