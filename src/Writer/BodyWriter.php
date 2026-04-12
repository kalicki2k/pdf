<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

/**
 * Writes prepared PDF body objects and returns their byte offsets.
 */
final class BodyWriter
{
    /**
     * @return array<int, int>
     */
    public function write(DocumentSerializationPlan $plan, Output $output): array
    {
        $offsets = [];

        foreach ($plan->objects as $object) {
            $offsets[$object->objectId] = $output->offset();

            $output->write($object->objectId . " 0 obj\n");

            if ($object->streamContents === null || $object->streamDictionaryContents === null) {
                $contents = $object->contents;

                if ($plan->objectEncryptor !== null && $object->encryptable) {
                    $contents = $plan->objectEncryptor->encryptLiteralStrings($contents, $object->objectId);
                }

                $output->write($contents);

                if (!str_ends_with($contents, "\n")) {
                    $output->write("\n");
                }

                $output->write("endobj\n");

                continue;
            }

            $dictionaryContents = $object->streamDictionaryContents;
            $streamContents = $object->streamContents;

            if ($plan->objectEncryptor !== null && $object->encryptable) {
                $dictionaryContents = $plan->objectEncryptor->encryptLiteralStrings($dictionaryContents, $object->objectId);
                $streamContents = $plan->objectEncryptor->encryptStreamContents($streamContents, $object->objectId);
            }

            $output->write($this->replaceStreamLength($dictionaryContents, strlen($streamContents)));
            $output->write("\nstream\n");
            $output->write($streamContents);
            if (!str_ends_with($streamContents, "\n")) {
                $output->write("\n");
            }
            $output->write("endstream\n");
            $output->write("endobj\n");
        }

        return $offsets;
    }

    private function replaceStreamLength(string $dictionaryContents, int $streamLength): string
    {
        $updatedContents = preg_replace(
            '/\/Length\s+\d+/',
            '/Length ' . $streamLength,
            $dictionaryContents,
            1,
        );

        return is_string($updatedContents) ? $updatedContents : $dictionaryContents;
    }
}
