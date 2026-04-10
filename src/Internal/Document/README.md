# Internal Document

Dieses Paket enthaelt den internen Dokumentkern und die dokumentweite Ablaufsteuerung.

Die Struktur ist bewusst in kleine Teilpakete geschnitten:

- `Preparation` fuer Render-Vorbereitung, Guards, TOC- und Decorator-Vorbereitung
- `Serialization` fuer Planaufbau und Writer-Orchestrierung
- Root-Paket fuer den internen Dokumentzustand und dokumentweite Manager
