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
    public function it_resolves_feature_namespace_bridges_to_the_legacy_document_implementation(
        string $featureClass,
        string $legacyClass,
    ): void {
        self::assertSame($legacyClass, (new ReflectionClass($featureClass))->getName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function featureBridgeProvider(): iterable
    {
        foreach (self::featureBridgeClasses() as $featureClass => $legacyClass) {
            yield $featureClass => [$featureClass, $legacyClass];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function featureBridgeClasses(): array
    {
        $bridges = [];

        foreach (self::featureBridgePaths() as $relativePath) {
            $featureClass = self::classNameFromRelativePath($relativePath);
            $bridges[$featureClass] = self::legacyClassFromFeaturePath($relativePath);
        }

        ksort($bridges);

        return $bridges;
    }

    /**
     * @return list<string>
     */
    private static function featureBridgePaths(): array
    {
        $featureRoot = dirname(__DIR__, 2) . '/src/Feature';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($featureRoot));
        $paths = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($featureRoot) + 1);
            $paths[] = 'Feature/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        }

        sort($paths);

        return $paths;
    }

    private static function classNameFromRelativePath(string $relativePath): string
    {
        return 'Kalle\\Pdf\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
    }

    private static function legacyClassFromFeaturePath(string $relativePath): string
    {
        return match (true) {
            $relativePath === 'Feature/Table.php' => 'Kalle\\Pdf\\Document\\Table',
            str_starts_with($relativePath, 'Feature/Table/') => 'Kalle\\Pdf\\Document\\Table\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Table/'),
            str_starts_with($relativePath, 'Feature/Text/') => 'Kalle\\Pdf\\Document\\Text\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Text/'),
            str_starts_with($relativePath, 'Feature/Action/') => 'Kalle\\Pdf\\Document\\Action\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Action/'),
            str_starts_with($relativePath, 'Feature/Annotation/') => 'Kalle\\Pdf\\Document\\Annotation\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Annotation/'),
            str_starts_with($relativePath, 'Feature/Form/') => 'Kalle\\Pdf\\Document\\Form\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Form/'),
            str_starts_with($relativePath, 'Feature/Outline/') => 'Kalle\\Pdf\\Document\\Outline\\' . self::suffixFromFeaturePath($relativePath, 'Feature/Outline/'),
            $relativePath === 'Feature/OptionalContent/OptionalContentGroup.php' => 'Kalle\\Pdf\\Document\\OptionalContentGroup',
            default => throw new InvalidArgumentException(sprintf('Unsupported feature bridge path: %s', $relativePath)),
        };
    }

    private static function suffixFromFeaturePath(string $relativePath, string $prefix): string
    {
        return str_replace(['/', '.php'], ['\\', ''], substr($relativePath, strlen($prefix)));
    }
}
