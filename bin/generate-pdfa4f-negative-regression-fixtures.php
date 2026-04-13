#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Renderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa4f-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$renderer = new Renderer();
$baseDocument = new Document(
    profile: Profile::pdfA4f(),
    title: 'PDF/A-4f Negative Regression',
    author: 'kalle/pdf2',
    subject: 'Negative PDF/A-4f regression fixtures',
    language: 'de-DE',
    creator: 'Regression Fixture',
    creatorTool: 'bin/generate-pdfa4f-negative-regression-fixtures.php',
    attachments: [
        new FileAttachment(
            'engineering-data.xml',
            new EmbeddedFile('<assembly id="base"/>', 'application/xml'),
            'Engineering source data',
            AssociatedFileRelationship::SOURCE,
        ),
    ],
);
$plan = (new DocumentSerializationPlanBuilder())->build($baseDocument);
$objects = iterator_to_array($plan->objects);
$baseOutputPath = rtrim($outputDir, '/\\');

$variants = [
    'pdf-a-4f-invalid-missing-pdfaid-conformance.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->streamContents !== null
                && str_contains($object->streamContents, '<pdfaid:conformance>F</pdfaid:conformance>'),
            static function (IndirectObject $object): IndirectObject {
                return IndirectObject::stream(
                    $object->objectId,
                    $object->streamDictionaryContents ?? '',
                    str_replace('<pdfaid:conformance>F</pdfaid:conformance>', '', $object->streamContents ?? ''),
                    $object->encryptable,
                );
            },
            'pdfaid:conformance metadata',
        );
    },
];

$paths = [];

foreach ($variants as $filename => $mutator) {
    $path = $baseOutputPath . DIRECTORY_SEPARATOR . $filename;
    $output = new FileOutput($path);
    $renderer->write(new DocumentSerializationPlan($mutator($objects), $plan->fileStructure, $plan->objectEncryptor), $output);
    $output->close();
    $paths[] = $path;
}

echo implode(PHP_EOL, $paths) . PHP_EOL;

/**
 * @param list<IndirectObject> $objects
 * @param callable(IndirectObject): bool $matcher
 * @param callable(IndirectObject): IndirectObject $mutator
 * @return list<IndirectObject>
 */
function replaceFirstMatchingObject(array $objects, callable $matcher, callable $mutator, string $label): array
{
    $didMutate = false;

    $mutatedObjects = array_map(
        static function (IndirectObject $object) use ($matcher, $mutator, &$didMutate): IndirectObject {
            if ($didMutate || !$matcher($object)) {
                return $object;
            }

            $didMutate = true;

            return $mutator($object);
        },
        $objects,
    );

    if (!$didMutate) {
        throw new RuntimeException(sprintf('Failed to mutate negative PDF/A-4f regression fixture for %s.', $label));
    }

    return $mutatedObjects;
}
