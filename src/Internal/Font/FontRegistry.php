<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use InvalidArgumentException;

final class FontRegistry
{
    /**
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $definitions
     */
    public static function get(string $name, ?array $definitions = null): FontPreset
    {
        $preset = self::find($name, $definitions);

        if ($preset !== null) {
            return $preset;
        }

        throw new InvalidArgumentException("Unknown embedded font '$name'.");
    }

    /**
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $definitions
     */
    public static function has(string $name, ?array $definitions = null): bool
    {
        return self::find($name, $definitions) !== null;
    }

    /**
     * @return list<FontPreset>
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $definitions
     */
    public static function all(?array $definitions = null): array
    {
        if ($definitions === null) {
            $definitions = self::loadDefaultDefinitions();
        }

        $presets = [];

        foreach ($definitions as $definition) {
            $presets[] = self::fromDefinition($definition);
        }

        return $presets;
    }

    /**
     * @param list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>|null $definitions
     */
    private static function find(string $name, ?array $definitions = null): ?FontPreset
    {
        foreach (self::all($definitions) as $preset) {
            if ($preset->baseFont === $name) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @param array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * } $definition
     */
    private static function fromDefinition(array $definition): FontPreset
    {
        return new FontPreset(
            baseFont: $definition['baseFont'],
            path: $definition['path'],
            unicode: $definition['unicode'],
            subtype: $definition['subtype'] ?? 'Type1',
            encoding: $definition['encoding'] ?? 'WinAnsiEncoding',
        );
    }

    /**
     * @return list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: bool,
     *     subtype?: string,
     *     encoding?: string
     * }>
     */
    private static function loadDefaultDefinitions(): array
    {
        return DefaultFontPresetDefinitions::all();
    }
}
