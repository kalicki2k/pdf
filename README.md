# PDF2

## Docker

Die Entwicklung kann innerhalb des Docker-Containers erfolgen. Der Projektordner wird per Bind-Mount nach `/app` eingebunden, dadurch sind lokale Dateien direkt im Container sichtbar.

### Voraussetzungen

- Docker
- Docker Compose
- `make`

### Image bauen

Vor der ersten Nutzung das PHP-Image bauen:

```bash
make build
```

Wenn sich das Basis-Image ändert und Docker noch ein altes Image verwendet:

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
