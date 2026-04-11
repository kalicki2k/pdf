<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Render;

use InvalidArgumentException;
use Kalle\Pdf\Render\FileOutput;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileOutputTest extends TestCase
{
    public function testItWritesBytesToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf2-file-output-');
        $output = new FileOutput($path);

        $output->write('Hello World');
        $output->close();

        self::assertSame(11, $output->offset());
        self::assertSame('Hello World', file_get_contents($path));

        unlink($path);
    }

    public function testItRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileOutput('');
    }

    public function testItThrowsWhenTheTargetFileCannotBeOpened(): void
    {
        $this->expectException(RuntimeException::class);

        new FileOutput('/does-not-exist/output.pdf');
    }
}
