# VKH General: wiederholbare Tests

Voraussetzung: PHP 8.0+ mit mbstring. Keine Composer-Abhängigkeiten.
Die PHP-Dateien hier sind ausschließlich per CLI ausführbar, nicht über HTTP.

## Unit-Tests (ohne WordPress oder Datenbank)

Im Plugin-Ordner:

```powershell
C:\xampp\php\php.exe tests/unit.php
```

Prüft Fehlerzustand und Versionsstand bei Migrationsfehlern, manuelle Wiederholung,
pausierte automatische Updates, Sperren, Reparatur fehlender Fremdschlüssel,
Rollback bei Speicherfehlern, Berechtigungen, Nonce-Prüfungen und Log-Inhalte.
Fehler werden mit Test-Doubles gezielt ausgelöst.

## Integrationstests (lokale WordPress-Testinstallation)

MySQL muss laufen. Benötigt CREATE/ALTER/DROP/INSERT/DELETE-Berechtigungen,
InnoDB für die WordPress-Posts-Tabelle sowie einen bestehenden Administrator.

```powershell
C:\xampp\php\php.exe tests/integration.php --allow-test-writes C:/xampp/htdocs/wordpress/wp-load.php
```

Alternativ wird ohne letzten Parameter die WordPress-Installation vier Ebenen
oberhalb dieses Tests ermittelt. Keine Zugangsdaten im Testcode hinterlegen.

Die Tests verwenden zufällig benannte eigene Plugin-Tabellen und kurzzeitig
angelegte Medien-Datensätze ohne echte hochgeladene Dateien. Vorhandene Hunde,
Medien und Schema-Versionsoptionen werden nicht geändert. Testdaten werden im
finally-Block entfernt. Bei einem Prozessabbruch kann eine Bereinigung der
Tabellen mit dem jeweiligen `vkh_test_<Zufallswert>_`-Präfix erforderlich sein.
Nur auf einer lokalen oder dedizierten Testinstallation ausführen.

Geprüft werden Erstinstallation, Wiederholung, Migration bestehender Tabellen,
Erhalt vorhandener Daten, Reparatur teilweise angewandter Migrationen, Anlegen
und Bearbeiten von Hunden, n:m-Zuordnungen, Fremdschlüssel, Medienvalidierung,
sichere HTML-Ausgabe sowie ein tatsächlich erzwungener SQL-Fehler mit Rollback.

Jedes Skript liefert bei einem Fehler einen Exit-Code ungleich 0.
Die Medienauswahl im Browser und vollständige HTTP-Angriffsszenarien sind kein
Bestandteil dieser Suite. Nonces/Berechtigungen werden in den Unit-Tests geprüft.

## Migrationen und Fehlerprotokoll

Die Datenbankversion wird erst nach erfolgreichem Abschluss gespeichert.
Eine MySQL-Advisory-Sperre verhindert parallele Plugin-Migrationen ohne Warten.
Nach einem Fehler pausiert der automatische Versuch für diese Zielversion.
Eine Administrator-Meldung bietet eine per Nonce und Berechtigung geschützte
Schaltfläche zur Wiederholung. Auch eine erneute Aktivierung versucht das Update.
Das übrige Backend bleibt erreichbar; Hunde speichern ist bis zum erfolgreichen
Update gesperrt. Ein vollständiger Datenbankausfall kann weiterhin WordPress
selbst beeinträchtigen.

Fehler werden über PHP `error_log()` mit dem Präfix `[VKH General]` protokolliert.
Das Ziel bestimmt die PHP-/Serverkonfiguration. Diese sollte auf ein nicht
öffentlich zugängliches, rotiertes Serverprotokoll zeigen. Es wird keine Logdatei
im Plugin oder Upload-Verzeichnis angelegt. Kategorien: `schema_install_failed`,
`schema_migration_failed`, `dog_save_failed`, `dog_list_failed`.
Jeder Eintrag enthält Kategorie, UTC-Zeit und eine zufällige Referenznummer.
SQL, Fehlermeldungen des Datenbanktreibers, Zugangsdaten und Formulardaten werden
nicht geloggt. Die Referenznummer aus der Backend-Meldung ermöglicht die Zuordnung.

DDL in MySQL ist nicht vollständig transaktional. Teilweise angelegte Tabellen
bleiben erhalten und werden bei einer Wiederholung ergänzt, nicht gelöscht.
Dies ersetzt kein Datenbankbackup vor einem produktiven Update.