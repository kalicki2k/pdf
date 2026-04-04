<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$sourceDir = $projectRoot . '/doc';
$targetDir = $projectRoot . '/var/docs';

if (!is_dir($sourceDir)) {
    fwrite(STDERR, "Documentation source directory not found: $sourceDir" . PHP_EOL);
    exit(1);
}

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
    fwrite(STDERR, "Failed to create target directory: $targetDir" . PHP_EOL);
    exit(1);
}

$markdownFiles = array_values(array_filter(
    scandir($sourceDir) ?: [],
    static fn (string $file): bool => str_ends_with($file, '.md')
));

$order = [
    'index.md' => 0,
    'getting-started.md' => 1,
    'architecture.md' => 2,
    'roadmap.md' => 3,
];

usort(
    $markdownFiles,
    static function (string $left, string $right) use ($order): int {
        $leftRank = $order[$left] ?? 100;
        $rightRank = $order[$right] ?? 100;

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp($left, $right);
    }
);

$documents = [];

foreach ($markdownFiles as $file) {
    $path = $sourceDir . '/' . $file;
    $markdown = file_get_contents($path);

    if ($markdown === false) {
        fwrite(STDERR, "Failed to read documentation file: $path" . PHP_EOL);
        exit(1);
    }

    $documents[] = [
        'file' => $file,
        'title' => extractTitle($markdown, $file),
        'htmlFile' => basename($file, '.md') . '.html',
        'content' => renderMarkdown($markdown),
    ];
}

foreach ($documents as $document) {
    $html = renderPage($document['title'], $document['content'], $documents, $document['htmlFile']);
    $outputPath = $targetDir . '/' . $document['htmlFile'];

    if (file_put_contents($outputPath, $html) === false) {
        fwrite(STDERR, "Failed to write documentation page: $outputPath" . PHP_EOL);
        exit(1);
    }
}

echo 'Generated ' . count($documents) . ' documentation page(s) in ' . $targetDir . PHP_EOL;

function extractTitle(string $markdown, string $fallbackFile): string
{
    foreach (preg_split("/\R/", $markdown) as $line) {
        if (str_starts_with($line, '# ')) {
            return trim(substr($line, 2));
        }
    }

    return ucwords(str_replace(['-', '.md'], [' ', ''], $fallbackFile));
}

function renderPage(string $title, string $content, array $documents, string $currentHtmlFile): string
{
    $navigation = [];

    foreach ($documents as $document) {
        $className = $document['htmlFile'] === $currentHtmlFile ? ' class="active"' : '';
        $navigation[] = sprintf(
            '<li><a%s href="%s">%s</a></li>',
            $className,
            htmlspecialchars($document['htmlFile'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($document['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    $navigationHtml = implode(PHP_EOL, $navigation);
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f1e8;
            --panel: #fffdf8;
            --line: #d5ccb7;
            --text: #1f1f1b;
            --muted: #6d6558;
            --accent: #9b4d28;
            --accent-soft: #f3e1d7;
            --code: #2a2926;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(180deg, #efe6d3 0%, var(--bg) 100%);
            color: var(--text);
        }

        .layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: 100vh;
        }

        aside {
            padding: 32px 24px;
            border-right: 1px solid var(--line);
            background: rgba(255, 253, 248, 0.82);
            backdrop-filter: blur(8px);
        }

        aside h1 {
            margin: 0 0 8px;
            font-size: 1.4rem;
        }

        aside p {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.5;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        nav li + li {
            margin-top: 10px;
        }

        nav a {
            display: block;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            border: 1px solid transparent;
        }

        nav a:hover,
        nav a.active {
            background: var(--accent-soft);
            border-color: #e2c5b6;
        }

        main {
            padding: 48px min(6vw, 72px);
        }

        article {
            max-width: 860px;
            padding: 40px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(76, 52, 33, 0.08);
        }

        h1, h2, h3 {
            line-height: 1.2;
            margin-top: 1.6em;
            margin-bottom: 0.6em;
        }

        h1:first-child {
            margin-top: 0;
            font-size: 2.4rem;
        }

        h2 {
            font-size: 1.5rem;
            border-top: 1px solid var(--line);
            padding-top: 0.8em;
        }

        h3 {
            font-size: 1.15rem;
        }

        p, li {
            line-height: 1.7;
        }

        code {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
            font-size: 0.95em;
            background: #f3efe6;
            color: var(--code);
            padding: 0.12em 0.35em;
            border-radius: 6px;
        }

        pre {
            margin: 1.2em 0;
            padding: 18px;
            overflow-x: auto;
            background: #1f1e1a;
            color: #f7f3eb;
            border-radius: 16px;
        }

        pre code {
            background: transparent;
            color: inherit;
            padding: 0;
        }

        ul, ol {
            padding-left: 1.4em;
        }

        a {
            color: var(--accent);
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }

            aside {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            main {
                padding: 24px;
            }

            article {
                padding: 24px;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside>
            <h1>PDF Docs</h1>
            <p>Statisch generiert aus den Markdown-Dateien im Ordner <code>doc/</code>.</p>
            <nav>
                <ul>
{$navigationHtml}
                </ul>
            </nav>
        </aside>
        <main>
            <article>
{$content}
            </article>
        </main>
    </div>
</body>
</html>
HTML;
}

function renderMarkdown(string $markdown): string
{
    $lines = preg_split("/\R/", str_replace("\r\n", "\n", $markdown)) ?: [];
    $html = [];
    $paragraph = [];
    $listItems = [];
    $listType = null;
    $inCodeBlock = false;
    $codeLines = [];

    foreach ($lines as $line) {
        if (str_starts_with($line, '```')) {
            flushParagraph($html, $paragraph);
            flushList($html, $listItems, $listType);

            if ($inCodeBlock) {
                $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
                $codeLines = [];
                $inCodeBlock = false;
            } else {
                $inCodeBlock = true;
            }

            continue;
        }

        if ($inCodeBlock) {
            $codeLines[] = $line;
            continue;
        }

        $trimmed = trim($line);

        if ($trimmed === '') {
            flushParagraph($html, $paragraph);
            flushList($html, $listItems, $listType);
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $matches) === 1) {
            flushParagraph($html, $paragraph);
            flushList($html, $listItems, $listType);
            $level = strlen($matches[1]);
            $html[] = sprintf('<h%d>%s</h%d>', $level, renderInline($matches[2]), $level);
            continue;
        }

        if (preg_match('/^- (.*)$/', $trimmed, $matches) === 1) {
            flushParagraph($html, $paragraph);

            if ($listType !== 'ul') {
                flushList($html, $listItems, $listType);
                $listType = 'ul';
            }

            $listItems[] = renderInline($matches[1]);
            continue;
        }

        if (preg_match('/^\d+\. (.*)$/', $trimmed, $matches) === 1) {
            flushParagraph($html, $paragraph);

            if ($listType !== 'ol') {
                flushList($html, $listItems, $listType);
                $listType = 'ol';
            }

            $listItems[] = renderInline($matches[1]);
            continue;
        }

        $paragraph[] = $trimmed;
    }

    flushParagraph($html, $paragraph);
    flushList($html, $listItems, $listType);

    if ($inCodeBlock) {
        $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }

    return implode(PHP_EOL, $html);
}

function flushParagraph(array &$html, array &$paragraph): void
{
    if ($paragraph === []) {
        return;
    }

    $html[] = '<p>' . renderInline(implode(' ', $paragraph)) . '</p>';
    $paragraph = [];
}

function flushList(array &$html, array &$listItems, ?string &$listType): void
{
    if ($listItems === [] || $listType === null) {
        return;
    }

    $html[] = '<' . $listType . '>';

    foreach ($listItems as $item) {
        $html[] = '<li>' . $item . '</li>';
    }

    $html[] = '</' . $listType . '>';
    $listItems = [];
    $listType = null;
}

function renderInline(string $text): string
{
    $placeholderPrefix = '__INLINE_CODE_';
    $codeMap = [];

    $text = preg_replace_callback(
        '/`([^`]+)`/',
        static function (array $matches) use (&$codeMap, $placeholderPrefix): string {
            $key = $placeholderPrefix . count($codeMap) . '__';
            $codeMap[$key] = '<code>' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';

            return $key;
        },
        $text
    ) ?? $text;

    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $escaped = preg_replace_callback(
        '/\[(.*?)\]\((.*?)\)/',
        static function (array $matches): string {
            $label = htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $target = $matches[2];

            if (str_ends_with($target, '.md')) {
                $target = basename($target, '.md') . '.html';
            }

            return sprintf(
                '<a href="%s">%s</a>',
                htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $label
            );
        },
        $escaped
    ) ?? $escaped;

    foreach ($codeMap as $placeholder => $codeHtml) {
        $escaped = str_replace($placeholder, $codeHtml, $escaped);
    }

    return $escaped;
}
