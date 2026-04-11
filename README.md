# PDF2

## Struktur

Der Quellcode ist jetzt grob nach Verantwortlichkeiten organisiert:

```text
src/
├─ Color/
├─ Document/
├─ Drawing/
├─ Font/
├─ Image/
├─ Page/
├─ Text/
├─ Writer/
└─ Pdf.php
```

## Bilder

Das Bildfundament ist über `ImageSource` und `ImagePlacement` angebunden. Die aktuelle API erwartet bereits vorbereitete Bilddaten, die als PDF-Image-XObject eingebettet werden.

```php
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;

$document = DefaultDocumentBuilder::make()
    ->image(
        ImageSource::jpeg($jpegBytes, 600, 300, ImageColorSpace::RGB),
        ImagePlacement::at(40, 500, width: 180),
    )
    ->build();
```

## Docker

Die Entwicklung kann innerhalb des Docker-Containers erfolgen. Der Projektordner wird per Bind-Mount nach `/app` eingebunden, dadurch sind lokale Dateien direkt im Container sichtbar.
Die `make`-Targets reichen dabei automatisch deine lokale `UID` und `GID` an Docker weiter, damit Dateien im gemounteten Projektordner nicht `root` gehören.

### Voraussetzungen

- Docker
- Docker Compose
- `make`

### Image bauen

Vor der ersten Nutzung das PHP-Image bauen:

```bash
make build
```

Wenn sich deine lokale Benutzer- oder Gruppen-ID geändert hat oder das Basis-Image aktualisiert wurde, das Image danach neu bauen:

```bash
make rebuild
```

### Arbeiten über Make

Eine Shell im Container starten:

```bash
make shell
```

Composer-Abhängigkeiten im Container installieren:

```bash
make composer-install
```

PHPStan im Container ausführen:

```bash
make phpstan
```

PHP-CS-Fixer im Container ausführen:

```bash
make cs
```

PHP-CS-Fixer im Prüfmodus ausführen:

```bash
make cs-check
```

PHPUnit im Container ausführen:

```bash
make test
```

Compose-Services starten:

```bash
make up
```

Compose-Services stoppen:

```bash
make down
```

### PHP-Version prüfen

Die verlässliche Prüfung der Container-Version ist:

```bash
make php-version
```

Ein lokales `php -v` prüft dagegen nur die Host-Installation.
