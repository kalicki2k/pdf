# Entwicklung, Tests und Diagnose

## Laufzeit- und Tooling-Stand

Der aktuelle Projektstand zeigt diese Primarumgebungen:

- `composer.json` verlangt `php: ^8.5`
- Docker baut ein `php:8.5-cli-alpine`-Image
- benoetigte Extensions laut `composer.json`: `exif`, `mbstring`, `openssl`
- optional fuer WebP-Import: `ext-gd`

Aktueller Hinweis aus dem Repository:

- Die GitHub-Actions-Workflows `tests.yml`, `phpstan.yml` und `php-cs-fixer.yml` sind noch auf PHP `8.4` konfiguriert.
- Das passt nicht zum aktuellen `composer.json`-Constraint und nicht zum Dockerfile.

Solange das nicht angeglichen ist, ist die Docker-Umgebung der eindeutigere Referenzpfad fuer lokale Entwicklung.

## Lokales Setup

```bash
make build
make composer-install
make test
```

Wichtige Make-Targets:

- `make build`
- `make composer-install`
- `make test`
- `make phpstan`
- `make cs-check`
- `make coverage`
- `make coverage-html`
- `make shell`

## Docker-Services

`compose.yml` definiert aktuell:

- `php` fuer Entwicklung, Tests und Composer
- `qpdf` fuer PDF-Strukturpruefung
- `verapdf` fuer PDF/A- und PDF/UA-Validierung

Die Container mounten das Repository nach `/app`.

## Testarten im Repository

Die Tests sind nach Themen geschnitten, nicht nur nach Klassen:

- `tests/Document/` fuer Builder, Renderer, Metadaten, Profile, Tagged PDF, TOC, Tabellen, Attachments, Formulare und Signaturen
- `tests/Text/` fuer Segmentierung, Bidi und Shaping
- `tests/Font/` fuer Parser, Standardfont-Metriken und Subsetting
- `tests/Image/` fuer Decoder, Encoder und Fixtures
- `tests/Page/` fuer Page-Value-Objects und Annotationen
- `tests/Encryption/` fuer Security-Handler, Permission-Bits und Objektverschluesselung
- `tests/Writer/` fuer Low-level-Serializer
- `tests/Debug/` fuer Debug-Sinks und PerformanceScope

Die Verteilung macht deutlich: Qualitaetssicherung findet nicht nur auf der Public API statt, sondern auch auf Low-level-PDF- und Subsystem-Ebene.

## Regressionen und externe Validatoren

Neben PHPUnit existiert ein eigener Satz von Regressionen fuer Archivierungsprofile:

- `bin/test-pdfa1b-regressions.sh`
- `bin/test-pdfa1a-*.sh`
- `bin/test-pdfa2*.sh`
- `bin/test-pdfa3*.sh`
- `bin/test-pdfa4*.sh`

Dazu gehoeren passende Generatoren, die Testdateien in `var/pdfa-regression` oder aehnliche Verzeichnisse schreiben.

Der aktuelle Validierungspfad nutzt:

1. PDF-Datei erzeugen
2. `qpdf --check`
3. `veraPDF`

Hilfsskripte:

- `bin/validate-qpdf.sh <pdf-file>`
- `bin/validate-pdfa.sh <pdf-file>`
- `bin/validate-pdfua.sh <pdf-file>`
- `bin/validate-verapdf.sh <pdf-file>`

`validate-qpdf.sh` akzeptiert bewusst nur Dateien innerhalb des Repositories.

## CI-Stand

Der aktuelle GitHub-Actions-Satz umfasst:

- `tests.yml` fuer PHPUnit
- `phpstan.yml`
- `php-cs-fixer.yml`
- `pdfa-regressions.yml`

Die PDF/A-Regression in CI deckt aktuell nur einen Teil der lokalen `Makefile`-Targets ab, insbesondere den PDF/A-1a-Pfad. Fuer andere PDF/A-Profile existieren lokale Skripte und Targets, sind aber nicht in derselben CI-Datei abgebildet.

## Beispiele und manuelle Sichtpruefung

`examples/` ist im aktuellen Repository keine Dekodoku, sondern eine wichtige Sekundaerquelle fuer beabsichtigte Nutzung. Die Skripte schreiben typischerweise nach `var/examples`.

Nuetzliche Beispiele fuer manuelle Pruefung:

- `examples/invoice.php`
- `examples/graphics-primitives.php`
- `examples/complex-text-shaping.php`
- `examples/encryption.php`
- `examples/observability.php`
- `examples/outlines*.php`
- `examples/table-of-contents*.php`

## Debugging im Code

Fuer Laufzeitdiagnostik gibt es ein strukturiertes Debugsystem:

- `DebugConfig`
- `JsonDebugSink`
- `TextDebugSink`
- `InMemoryDebugSink`
- `PsrDebugSink`

Typische Nutzung:

```php
use Kalle\Pdf\Debug\DebugConfig;

$document = \Kalle\Pdf\Document\Document::make()
    ->debug(
        DebugConfig::json()->toFile(__DIR__ . '/../var/pdf-debug.log')
    )
    ->build();
```

Relevante Eventgruppen:

- Lifecycle
- PDF-Struktur
- Performance

Fuer Test- und Profilingcode ist `InMemoryDebugSink` besonders nuetzlich.

## Performance-Werkzeuge

Zwei interne Werkzeuge sind vorhanden:

- `bin/benchmark-performance.php`
- `bin/profile-performance.php`

`benchmark-performance.php` misst getrennt:

- Dokumentaufbau
- Build des Serialisierungsplans
- Rendering/Schreiben

`profile-performance.php` nutzt `InMemoryDebugSink`, um Scope-Zeiten aus dem Debugsystem auszuwerten.

## Wichtige Ausgabeverzeichnisse

Im Repository werden mehrere Arbeitsverzeichnisse verwendet:

- `var/examples`
- `var/pdfa-regression`
- `var/encryption-regression`
- `var/benchmarks`
- `var/coverage`
- `var/pdfa-audit`

Diese Verzeichnisse sind hilfreich fuer lokale Inspektion, sollten aber nicht mit Quell- oder Fixture-Daten verwechselt werden.

## Fehlersuche bei Build-Problemen

Fuer viele Validierungsfehler existiert bereits ein Hint-System:

- `DocumentBuildException`
- `DocumentValidationException`
- `DocumentBuildHintResolver`

Aus den Tests ablesbare Hint-Kategorien sind unter anderem:

- doppelte Named Destinations
- doppelte Attachment-Dateinamen
- ungueltige Outline-Referenzen oder Outline-Hierarchie
- ungueltige Form-Field-Seiten
- fehlende TOC-Eintraege
- ungueltige TOC-Geometrie
- fehlende Unicode-Fonts fuer passende Profile
- fehlende Tagged-PDF-Struktur
- fehlende Bild-Accessibility in Tagged-Profilen
- gesperrte Annotationen oder Formularfelder in PDF/A-Profilen

Wer an Validierung oder Profilregeln arbeitet, sollte diese Hint-Tests mitpruefen.

## Empfehlungen fuer Maintainer

Aus dem aktuellen Projektzuschnitt ergeben sich fuer Aenderungen diese sinnvollen Pruefschritte:

1. relevante Unit- oder Integrations-Tests laufen lassen
2. bei Low-level-PDF-Aenderungen mindestens Writer- und Document-Tests mitnehmen
3. bei PDF/A-Aenderungen zusaetzlich die passenden Regressionen und `qpdf`/`veraPDF` laufen lassen
4. bei Text-, Font- oder Image-Aenderungen explizit Beispielskripte oder Fixtures visuell pruefen
