<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\TableOfContents\TableOfContentsOptions;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param array{
 *   filename: string,
 *   subject: string,
 *   cover_subtitle: string,
 *   cover_body: string,
 *   chapter_note: string,
 *   toc_options: TableOfContentsOptions,
 *   use_explicit_entries?: bool
 * } $config
 */
function writeTableOfContentsDemo(array $config): void
{
    $outputDirectory = __DIR__ . '/../var/examples';

    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException('Unable to create example output directory.');
    }

    $builder = DefaultDocumentBuilder::make()
        ->title('Project Handbook')
        ->author('Kalle PDF')
        ->subject($config['subject'])
        ->language('en-US')
        ->creator($config['filename'])
        ->creatorTool('pdf2')
        ->pageSize(PageSize::A4())
        ->margin(Margin::all(Units::mm(20)))
        ->text('Project Handbook', new TextOptions(
            x: Units::mm(20),
            y: 720,
            fontSize: 30,
            color: Color::hex('#111827'),
        ))
        ->paragraph($config['cover_subtitle'], new TextOptions(
            x: Units::mm(20),
            y: 680,
            width: Units::mm(160),
            fontSize: 13,
            lineHeight: 18,
            color: Color::hex('#475569'),
            spacingAfter: 16,
        ))
        ->paragraph($config['cover_body'], new TextOptions(
            x: Units::mm(20),
            y: 640,
            width: Units::mm(155),
            fontSize: 12,
            lineHeight: 18,
            color: Color::hex('#334155'),
        ))
        ->newPage();

    $chapters = [
        [
            'title' => 'Introduction',
            'lead' => 'This chapter introduces the document structure and the overall goal of the example.',
            'body' => 'The table of contents is generated after the regular content pages are known. That keeps TOC page numbers deterministic even when TOC pages are inserted at the front or in the middle.',
        ],
        [
            'title' => 'Usage',
            'lead' => 'The public API stays intentionally small: builder, pages and a few focused navigation calls.',
            'body' => 'A typical flow is: create the document, build visible pages, register outlines or explicit TOC entries, and enable the TOC as a final builder step.',
        ],
        [
            'title' => 'Rendering',
            'lead' => 'Rendering remains the last step after the final page order has been resolved.',
            'body' => $config['chapter_note'],
        ],
    ];

    foreach ($chapters as $index => $chapter) {
        if ($index > 0) {
            $builder = $builder->newPage();
        }

        if (($config['use_explicit_entries'] ?? false) === true) {
            $builder = $builder->tableOfContentsEntry($chapter['title']);
        } else {
            $builder = $builder->outline($chapter['title']);
        }

        $builder = $builder
            ->text($chapter['title'], new TextOptions(
                x: Units::mm(20),
                y: 760,
                fontSize: 24,
                color: Color::hex('#0f172a'),
            ))
            ->paragraph($chapter['lead'], new TextOptions(
                x: Units::mm(20),
                y: 724,
                width: Units::mm(165),
                fontSize: 11,
                lineHeight: 16,
                color: Color::hex('#475569'),
                spacingAfter: 10,
            ))
            ->paragraph($chapter['body'], new TextOptions(
                x: Units::mm(20),
                y: 690,
                width: Units::mm(165),
                fontSize: 12,
                lineHeight: 18,
                color: Color::hex('#334155'),
            ));
    }

    $builder
        ->tableOfContents($config['toc_options'])
        ->writeToFile($outputDirectory . '/' . $config['filename'] . '.pdf');

    printf('Generated %s/%s.pdf%s', $outputDirectory, $config['filename'], PHP_EOL);
}
