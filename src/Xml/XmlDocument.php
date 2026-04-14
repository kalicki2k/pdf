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
}
