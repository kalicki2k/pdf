<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Render;

final class PdfBodySerializer
{
    public function __construct(
        private readonly PdfObjectEncryptorFactory $objectEncryptorFactory = new PdfObjectEncryptorFactory(),
    ) {
    }

    public function write(PdfSerializationPlan $plan, PdfOutput $output): PdfObjectOffsets
    {
        $objectSerializer = new PdfObjectSerializer($this->objectEncryptorFactory->create($plan));

        return $objectSerializer->writeObjects($plan->objects, $output);
    }
}
