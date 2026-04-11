<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\PageOptions;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Text\TextOptions;

interface DocumentBuilder
{
    public function title(string $title): self;

    public function author(string $author): self;

    public function subject(string $subject): self;

    public function language(string $language): self;

    public function creator(string $creator): self;

    public function creatorTool(string $creatorTool): self;

    public function profile(Profile $profile): self;

    public function pageSize(PageSize $size): self;

    public function content(string $content): self;

    public function text(string $text, ?TextOptions $options = null): self;

    public function newPage(?PageOptions $options = null): self;

    public function build(): Document;

    public function save(string $path): string;
    //
    //    public function margin(int|float $margin): self;
    //    public function page(callable $callback): self;
    //
    //    public function save(string $path): string;
}
