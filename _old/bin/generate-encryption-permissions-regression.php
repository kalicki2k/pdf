#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;

require dirname(__DIR__) . '/vendor/autoload.php';

$outputDirectory = $argv[1] ?? dirname(__DIR__) . '/var/encryption-regression';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDirectory));
    exit(1);
}

$documents = [
    'aes128-readonly.pdf' => DefaultDocumentBuilder::make()
        ->profile(Profile::pdf16())
        ->title('AES-128 Read Only')
        ->encryption(Encryption::aes128('user', 'owner')->withPermissions(Permissions::readOnly()))
        ->text('Read only fixture')
        ->contents(),
    'aes128-selected.pdf' => DefaultDocumentBuilder::make()
        ->profile(Profile::pdf16())
        ->title('AES-128 Selected Permissions')
        ->encryption(Encryption::aes128('user', 'owner')->withPermissions(
            new Permissions(print: false, modify: true, copy: false, annotate: true),
        ))
        ->text('Selected permissions fixture')
        ->contents(),
    'aes256-selected.pdf' => DefaultDocumentBuilder::make()
        ->profile(Profile::pdf17())
        ->title('AES-256 Selected Permissions')
        ->encryption(Encryption::aes256('user', 'owner')->withPermissions(
            new Permissions(print: false, modify: true, copy: false, annotate: true),
        ))
        ->text('Selected permissions fixture')
        ->contents(),
];

foreach ($documents as $fileName => $contents) {
    $path = rtrim($outputDirectory, '/\\') . '/' . $fileName;

    if (file_put_contents($path, $contents) === false) {
        fwrite(STDERR, sprintf("Could not write regression PDF: %s\n", $path));
        exit(1);
    }

    fwrite(STDOUT, $path . PHP_EOL);
}
