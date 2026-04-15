#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedPdf\TaggedTextBlock;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageFont;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Renderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa1a-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Failed to create output directory: %s\n", $outputDir));
    exit(1);
}

$fontPath = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

if (!is_file($fontPath)) {
    fwrite(STDERR, sprintf("Required regression font not found: %s\n", $fontPath));
    exit(1);
}

$embeddedFont = EmbeddedFontDefinition::fromSource(
    EmbeddedFontSource::fromPath($fontPath),
);
$text = 'PDF/A-1a Regression Привет';
$pageFont = PageFont::embeddedUnicode(
    $embeddedFont,
    $embeddedFont->embeddedGlyphsForCodePoints($embeddedFont->unicodeCodePointsForText($text)),
);
$document = new Document(
    profile: Profile::pdfA1a(),
    title: 'PDF/A-1a Negative Structure Regression',
    author: 'kalle/pdf2',
    subject: 'Negative PDF/A-1a tagged structure regression fixtures',
    language: 'de-DE',
    creator: 'Regression Fixture',
    creatorTool: 'bin/generate-pdfa1a-negative-regression-fixtures.php',
    pages: [
        new Page(
            PageSize::A4(),
            contents: implode("\n", [
                '/P << /MCID 0 >> BDC',
                'BT',
                '/F1 12 Tf',
                '72 720 Td',
                '<' . bin2hex($pageFont->encodeUnicodeText($text)) . '> Tj',
                'ET',
                'EMC',
            ]),
            fontResources: [
                'F1' => $pageFont,
            ],
        ),
    ],
    taggedTextBlocks: [
        new TaggedTextBlock('P', 0, 0),
    ],
);

$plan = (new DocumentSerializationPlanBuilder())->build($document);
$objects = iterator_to_array($plan->objects);
$renderer = new Renderer();

$variants = [
    'pdf-a-1a-invalid-missing-catalog-struct-tree-root.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/StructTreeRoot '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s\/StructTreeRoot \d+ 0 R/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
        );
    },
    'pdf-a-1a-invalid-broken-catalog-struct-tree-root-reference.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/StructTreeRoot '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\/StructTreeRoot \d+ 0 R/', '/StructTreeRoot 999 0 R', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
        );
    },
    'pdf-a-1a-invalid-missing-catalog-markinfo.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/MarkInfo << /Marked true >>'),
            static function (IndirectObject $object): IndirectObject {
                $contents = str_replace(' /MarkInfo << /Marked true >>', '', $object->contents);

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
        );
    },
];

$paths = [];

foreach ($variants as $filename => $mutator) {
    $variantObjects = $mutator($objects);
    $outputPath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $output = new FileOutput($outputPath);
    $renderer->write(
        new DocumentSerializationPlan($variantObjects, $plan->fileStructure, $plan->objectEncryptor),
        $output,
    );
    $output->close();
    $paths[] = $outputPath;
}

echo implode(PHP_EOL, $paths) . PHP_EOL;

/**
 * @param list<IndirectObject> $objects
 * @param callable(IndirectObject): bool $matcher
 * @param callable(IndirectObject): IndirectObject $mutator
 * @return list<IndirectObject>
 */
function replaceFirstMatchingObject(array $objects, callable $matcher, callable $mutator): array
{
    $mutated = false;

    $result = array_map(
        static function (IndirectObject $object) use ($matcher, $mutator, &$mutated): IndirectObject {
            if ($mutated || !$matcher($object)) {
                return $object;
            }

            $mutated = true;

            return $mutator($object);
        },
        $objects,
    );

    if (!$mutated) {
        throw new \RuntimeException('Failed to mutate negative PDF/A-1a regression fixture.');
    }

    return $result;
}
