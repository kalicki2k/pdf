<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

final readonly class XmlDocument
{
    public function __construct(
        public XmlElement $root,
        public string $version = '1.0',
        public string $encoding = 'UTF-8',
        public bool $standalone = false,
    ) {
    }

    public function withRoot(XmlElement $root): self
    {
        return new self(
            root: $root,
            version: $this->version,
            encoding: $this->encoding,
            standalone: $this->standalone,
        );
    }
}
