<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionOptions;
use Kalle\Pdf\Layout\PageSize;
use Kalle\Pdf\Layout\Units;

require 'vendor/autoload.php';

$password = 'secret';
$outputDir = __DIR__ . '/var/encryption-examples';

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    throw new RuntimeException(sprintf('Unable to create output directory "%s".', $outputDir));
}

$examples = [
    [
        'label' => 'PDF 1.3 / RC4 40-bit',
        'version' => 1.3,
        'algorithm' => EncryptionAlgorithm::RC4_40,
        'filename' => 'encrypted-pdf-1.3-rc4-40.pdf',
    ],
    [
        'label' => 'PDF 1.4 / RC4 128-bit',
        'version' => 1.4,
        'algorithm' => EncryptionAlgorithm::RC4_128,
        'filename' => 'encrypted-pdf-1.4-rc4-128.pdf',
    ],
    [
        'label' => 'PDF 1.6 / AES 128-bit',
        'version' => 1.6,
        'algorithm' => EncryptionAlgorithm::AES_128,
        'filename' => 'encrypted-pdf-1.6-aes-128.pdf',
    ],
    [
        'label' => 'PDF 1.7 / AES 256-bit',
        'version' => 1.7,
        'algorithm' => EncryptionAlgorithm::AES_256,
        'filename' => 'encrypted-pdf-1.7-aes-256.pdf',
    ],
];

$startedAt = microtime(true);

foreach ($examples as $example) {
    $document = new Document(
        version: $example['version'],
        title: $example['label'],
        author: 'Kalle',
        subject: 'Encryption test example',
        language: 'de-DE',
    );

    $document->registerFont('Helvetica');
    $document->encrypt(new EncryptionOptions(
        userPassword: $password,
        ownerPassword: $password,
        algorithm: $example['algorithm'],
    ));

    $page = $document->addPage(PageSize::A4());
    $page->addText($example['label'], Units::mm(20), Units::mm(260), 'Helvetica', 20);
    $page->addText(
        'Dieses PDF ist absichtlich verschluesselt und dient nur zum Testen der Reader-Kompatibilitaet.',
        Units::mm(20),
        Units::mm(240),
        'Helvetica',
        11,
    );
    $page->addText(
        'Passwort: secret',
        Units::mm(20),
        Units::mm(225),
        'Helvetica',
        12,
    );
    $page->addText(
        sprintf('Algorithmus: %s', $example['algorithm']->name),
        Units::mm(20),
        Units::mm(210),
        'Helvetica',
        12,
    );
    $page->addText(
        sprintf('PDF-Version: %.1f', $example['version']),
        Units::mm(20),
        Units::mm(195),
        'Helvetica',
        12,
    );
    $page->addText(
        'Klartext zum Entschluesseln: The quick brown fox jumps over the lazy dog.',
        Units::mm(20),
        Units::mm(170),
        'Helvetica',
        12,
    );

    $targetPath = $outputDir . '/' . $example['filename'];
    file_put_contents($targetPath, $document->render());

    echo $targetPath . PHP_EOL;
}

printf(
    'Erstellt %d Dateien in %.3f Sekunden.%s',
    count($examples),
    microtime(true) - $startedAt,
    PHP_EOL,
);
