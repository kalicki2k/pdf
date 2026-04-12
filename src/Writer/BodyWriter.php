<?php

declare(strict_types=1);

namespace Kalle\Pdf\Writer;

use Kalle\Pdf\Debug\Debugger;

/**
 * Writes prepared PDF body objects and returns their byte offsets.
 */
final class BodyWriter
{
    /**
     * @return array<int, int>
     */
    public function write(DocumentSerializationPlan $plan, Output $output, ?Debugger $debugger = null): array
    {
        $debugger ??= Debugger::disabled();
        $offsets = [];

        foreach ($plan->objects as $object) {
            $startOffset = $output->offset();
            $offsets[$object->objectId] = $startOffset;

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

                $debugger->pdf('object.serialized', [
                    'object_id' => $object->objectId,
                    'type' => $this->inferObjectType($object->contents),
                    'start_offset' => $startOffset,
                    'length' => $output->offset() - $startOffset,
                    'compressed' => false,
                ]);

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

            $compressed = str_contains($dictionaryContents, '/FlateDecode');

            $debugger->pdf('stream.serialized', [
                'object_id' => $object->objectId,
                'type' => $this->inferObjectType($dictionaryContents),
                'start_offset' => $startOffset,
                'length' => strlen($streamContents),
                'compressed' => $compressed,
            ]);
            $debugger->pdf('object.serialized', [
                'object_id' => $object->objectId,
                'type' => $this->inferObjectType($dictionaryContents),
                'start_offset' => $startOffset,
                'length' => $output->offset() - $startOffset,
                'compressed' => $compressed,
            ]);
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

    private function inferObjectType(string $contents): ?string
    {
        if (!preg_match('/\/Type\s*\/([A-Za-z0-9]+)/', $contents, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
