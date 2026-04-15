#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Renderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa3b-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$renderer = new Renderer();
$baseOutputPath = rtrim($outputDir, '/\\');
$baseDocument = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA3b())
    ->title('PDF/A-3b Negative Regression')
    ->author('kalle/pdf2')
    ->subject('Negative PDF/A-3b regression fixtures')
    ->attachment(
        'data.xml',
        '<root/>',
        'Source data',
        'application/xml',
        AssociatedFileRelationship::SOURCE,
    )
    ->build();
$plan = (new DocumentSerializationPlanBuilder())->build($baseDocument);
$objects = iterator_to_array($plan->objects);

$variants = [
    'pdf-a-3b-invalid-missing-catalog-af-array.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/AF '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s*\/AF\s+\[[^\]]+\]/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
            'catalog AF array',
        );
    },
    'pdf-a-3b-invalid-missing-afrelationship.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => str_contains($object->contents, '/Type /Filespec') && str_contains($object->contents, '/AFRelationship /Source'),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s*\/AFRelationship\s+\/Source/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
            'AFRelationship entry',
        );
    },
];

$paths = [];

foreach ($variants as $filename => $mutator) {
    $path = $baseOutputPath . DIRECTORY_SEPARATOR . $filename;
    $output = new FileOutput($path);
    $renderer->write(
        new DocumentSerializationPlan($mutator($objects), $plan->fileStructure, $plan->objectEncryptor),
        $output,
    );
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
        throw new RuntimeException(sprintf('Failed to mutate negative PDF/A-3b regression fixture for %s.', $label));
    }

    return $mutatedObjects;
}
