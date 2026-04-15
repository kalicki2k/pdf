<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
//        __DIR__ . '/examples',
        __DIR__ . '/src',
//        __DIR__ . '/tests',
    ]);

return new Config()
    ->setRiskyAllowed(false)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        'array_indentation' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return'],
        ],
        'cast_spaces' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_parentheses' => true,
        'function_declaration' => [
            'closure_fn_spacing' => 'one',
        ],
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
        'global_namespace_import' => [
            'import_classes' => true,
        ],
        'line_ending' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'no_closing_tag' => true,
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'use'],
        ],
        'no_trailing_whitespace' => true,
        'no_unused_imports' => true,
        'no_unneeded_import_alias' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_imports' => [
            'imports_order' => ['const', 'function', 'class'],
            'sort_algorithm' => 'alpha',
        ],
        'single_blank_line_at_eof' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'single_quote' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'types_spaces' => [
            'space' => 'single',
        ],
        'unary_operator_spaces' => true,
    ])
    ->setFinder($finder);
