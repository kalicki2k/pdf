# Document

Dieses Paket enthaelt den Dokumentkern und die dokumentweite Ablaufsteuerung.

Die Struktur ist bewusst in kleine Teilpakete geschnitten:

- `Attachment` fuer eingebettete Dateien und ihre PDF-Dateispezifikationen
- `Form` fuer dokumentweite AcroForm-Objekte
- `Metadata` fuer klassische PDF-Metadaten, XMP und Output-Intent-Profile
- `Preparation` fuer Render-Vorbereitung, Guards, TOC- und Decorator-Vorbereitung
- `Serialization` fuer Planaufbau und Writer-Orchestrierung
- `Structure` fuer Root-Objekte wie `Catalog` und `Pages`
- Root-Paket fuer das Dokumentaggregat `Kalle\Pdf\Document\Document` und dokumentweite Manager
