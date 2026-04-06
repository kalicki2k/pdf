<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Document\Text\ListOptions;
use Kalle\Pdf\Document\Text\ParagraphOptions;
use Kalle\Pdf\Document\Text\TextFrame as InternalTextFrame;
use Kalle\Pdf\Document\Text\TextOptions;
use Kalle\Pdf\Document\Text\TextSegment;
use Kalle\Pdf\Layout\BulletType;

/**
 * Public facade for flowing text across pages.
 */
final readonly class TextFrame
{
    /**
     * @internal Text frames are created by Page::createTextFrame().
     */
    public function __construct(private InternalTextFrame $textFrame)
    {
    }

    public function addText(
        string $text,
        string $fontName,
        int $size,
        TextOptions $options = new TextOptions(),
        ?float $spacingAfter = null,
    ): self {
        $this->textFrame->addText($text, $fontName, $size, $options, $spacingAfter);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addParagraph(
        string | array $text,
        string $fontName,
        int $size,
        ParagraphOptions $options = new ParagraphOptions(),
    ): self {
        $this->textFrame->addParagraph($text, $fontName, $size, $options);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function addBulletList(
        array $items,
        string $fontName,
        int $size,
        BulletType $bulletType = BulletType::DISC,
        ListOptions $options = new ListOptions(),
    ): self {
        $this->textFrame->addBulletList($items, $fontName, $size, $bulletType, $options);

        return $this;
    }

    /**
     * @param list<string|list<TextSegment>> $items
     */
    public function addNumberedList(
        array $items,
        string $fontName,
        int $size,
        int $startAt = 1,
        ListOptions $options = new ListOptions(),
    ): self {
        $this->textFrame->addNumberedList($items, $fontName, $size, $startAt, $options);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $text
     */
    public function addHeading(
        string | array $text,
        string $fontName,
        int $size,
        ParagraphOptions $options = new ParagraphOptions(),
    ): self {
        $this->textFrame->addHeading($text, $fontName, $size, $options);

        return $this;
    }

    public function addSpacer(float $height): self
    {
        $this->textFrame->addSpacer($height);

        return $this;
    }

    public function getPage(): Page
    {
        return new Page($this->textFrame->getPage());
    }

    public function getCursorY(): float
    {
        return $this->textFrame->getCursorY();
    }
}
