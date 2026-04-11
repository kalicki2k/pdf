<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Pdf;

$document = Pdf::document()
    ->title('Invoice 2026-0015')
    ->author('Company Name')
    ->subject('Invoice')
    ->language('en-US')
    ->creator('Invoice service')
    ->creatorTool('Backoffice Export');

$document->writeToFile('../var/examples/invoices.pdf');
