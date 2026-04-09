<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class FeatureNamespaceBridgeTest extends TestCase
{
    #[Test]
    #[DataProvider('featureBridgeProvider')]
    public function it_resolves_legacy_document_feature_namespaces_to_the_new_feature_implementation(
        string $legacyClass,
        string $featureClass,
    ): void {
        self::assertSame($featureClass, (new ReflectionClass($legacyClass))->getName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function featureBridgeProvider(): iterable
    {
        foreach (self::featureBridgeClasses() as $legacyClass => $featureClass) {
            yield $legacyClass => [$legacyClass, $featureClass];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function featureBridgeClasses(): array
    {
        $bridges = [];

        foreach (self::legacyBridgePaths() as $relativePath) {
            $legacyClass = self::classNameFromRelativePath($relativePath);
            $bridges[$legacyClass] = self::featureClassFromLegacyPath($relativePath);
        }

        ksort($bridges);

        return $bridges;
    }

    /**
     * @return list<string>
     */
    private static function legacyBridgePaths(): array
    {
        $documentRoot = dirname(__DIR__, 2) . '/src/Document';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentRoot));
        $paths = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($documentRoot) + 1));

            if (
                !str_starts_with($relativePath, 'Action/')
                && !str_starts_with($relativePath, 'Annotation/')
                && !str_starts_with($relativePath, 'Form/')
                && !str_starts_with($relativePath, 'Outline/')
                && !str_starts_with($relativePath, 'Text/')
                && !str_starts_with($relativePath, 'Table/')
                && $relativePath !== 'Table.php'
                && $relativePath !== 'OptionalContentGroup.php'
            ) {
                continue;
            }

            $paths[] = 'Document/' . $relativePath;
        }

        sort($paths);

        return $paths;
    }

    private static function classNameFromRelativePath(string $relativePath): string
    {
        return 'Kalle\\Pdf\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
    }

    private static function featureClassFromLegacyPath(string $relativePath): string
    {
        return match (true) {
            $relativePath === 'Document/Table.php' => 'Kalle\\Pdf\\Feature\\Table',
            str_starts_with($relativePath, 'Document/Table/') => 'Kalle\\Pdf\\Feature\\Table\\' . self::suffixFromPath($relativePath, 'Document/Table/'),
            str_starts_with($relativePath, 'Document/Text/') => 'Kalle\\Pdf\\Feature\\Text\\' . self::suffixFromPath($relativePath, 'Document/Text/'),
            str_starts_with($relativePath, 'Document/Action/') => 'Kalle\\Pdf\\Feature\\Action\\' . self::suffixFromPath($relativePath, 'Document/Action/'),
            str_starts_with($relativePath, 'Document/Annotation/') => 'Kalle\\Pdf\\Feature\\Annotation\\' . self::suffixFromPath($relativePath, 'Document/Annotation/'),
            str_starts_with($relativePath, 'Document/Form/') => 'Kalle\\Pdf\\Feature\\Form\\' . self::suffixFromPath($relativePath, 'Document/Form/'),
            str_starts_with($relativePath, 'Document/Outline/') => 'Kalle\\Pdf\\Feature\\Outline\\' . self::suffixFromPath($relativePath, 'Document/Outline/'),
            $relativePath === 'Document/OptionalContentGroup.php' => 'Kalle\\Pdf\\Feature\\OptionalContent\\OptionalContentGroup',
            default => throw new InvalidArgumentException(sprintf('Unsupported legacy bridge path: %s', $relativePath)),
        };
    }

    private static function suffixFromPath(string $relativePath, string $prefix): string
    {
        return str_replace(['/', '.php'], ['\\', ''], substr($relativePath, strlen($prefix)));
    }
}
