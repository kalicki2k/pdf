<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

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
            $output->write($object->contents);

            if (!str_ends_with($object->contents, "\n")) {
                $output->write("\n");
            }

            $output->write("endobj\n");
        }

        return $offsets;
    }
}
