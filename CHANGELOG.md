# Changelog

## Unreleased

- erweitert den Bildimport um GIF, BMP und zusätzliche TIFF-Untervarianten in kleinen, getesteten Schritten
- ergänzt optionalen WebP-Import über GD, inklusive Docker-PHP-Container mit aktivierter GD-WebP-Runtime
- schärft die Bild-Regressionen für PDF-Ausgabe, Transparenz und bewusst abgelehnte TIFF-Randfälle
- führt `DecodedRasterImage` als kleine interne Raster-Zwischenschicht für rasterbasierte Decoder ein
- dokumentiert die Import-Pipeline und lehnt animiertes WebP jetzt explizit ab
- ergänzt `make regression` als Sammelziel für die vorhandenen Regressionsläufe
- entfernt den veralteten `Kalle\Pdf\Render\*`-Kompatibilitätspfad zugunsten des aktiven `Kalle\Pdf\Writer\*`-Namespaces
