<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

class PdfRenderer
{
    public function __construct(
        private readonly PdfSerializationPlanWriter $planWriter = new PdfSerializationPlanWriter(),
    ) {
    }

    public function render(PdfSerializationPlan $plan): string
    {
        $output = new StringPdfOutput();
        $this->write($plan, $output);

        return $output->contents();
    }

    public function write(PdfSerializationPlan $plan, PdfOutput $output): void
    {
        $this->planWriter->write($plan, $output);
    }
}
