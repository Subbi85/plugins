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
## PDF-Export

Im aufgeklappten Hunde-Eintrag liegt „Als PDF exportieren“ neben „Hund bearbeiten“.
Der Download erfordert `manage_options` und einen an den Hund gebundenen Nonce.
Es werden keine PDFs öffentlich gespeichert und keine entfernten Bilder geladen.
Enthalten sind Name, Geburtsdatum, sämtliche Titel/Befunde, Status, Bemerkungen,
ein lokales JPEG-/PNG-Foto und ein anklickbarer Link zur vorhandenen Ahnentafel.
Die Ahnentafel wird nicht als weitere PDF-Seite angehängt. Andere Bildformate oder
nicht einbettbare Fotos erzeugen einen Hinweis; die Hundedaten bleiben exportierbar.

Die Bibliothek `setasign/tfpdf` samt Lizenz liegt in `vendor/`. `composer.json`
und `composer.lock` dokumentieren die Version. Bei Veröffentlichung `vendor/`
mitliefern oder vorher `composer install --no-dev --no-plugins --no-scripts`
ausführen. Hostabhängige Schrift-Caches werden in `.gitignore` ausgeschlossen.
Abhängigkeiten regelmäßig mit `composer audit` prüfen.

Die Suite prüft zusätzlich PDF-Berechtigung, hundgebundenen Nonce, fehlende Hunde,
vollständige Titel, Erzeugung gültiger PDF-Bytes und Umbrüche über mehrere Seiten.
## Interessenten (ab Version 0.3.0)

`vkh_interessent` speichert Vorname, Nachname, Telefon, E-Mail und Hundeerfahrung
als Boolean. Die optionale `hund_id` hat einen Fremdschlüssel auf `vkh_hund`.
Beim Löschen eines Hundes bleibt die Person erhalten, die Zuordnung wird NULL.
Vorname, Nachname und eine gültige E-Mail sind Pflicht; Telefon und Hund sind optional.
Die Backend-Liste zeigt 25 Personen je Seite. Anlegen und Bearbeiten sind mit
`manage_options`, Nonce, serverseitiger Validierung und maskierter Ausgabe geschützt.
Kontaktdaten werden nicht im Fehlerprotokoll gespeichert. Es gibt keinen öffentlichen
Interessenten-Endpunkt. `prospect-form.php` enthält das Formular, `prospects.php`
die Seite und `includes/prospects.php` die Speicher-/Lesefunktionen.

Die Tests prüfen zusätzlich Anlegen und Bearbeiten, vorausgefülltes Formular,
Ungültige Eingaben, Boolean-Rücksetzen, Hunde-Fremdschlüssel, SET NULL, Seitenwechsel,
Berechtigung/Nonce sowie den Erhalt des alten Datensatzes nach einem SQL-Fehler.
Beispieldaten werden nicht automatisch bei jeder Installation angelegt.
## Anlagedatum (ab Version 0.3.1)

Interessenten haben `angelegt_am` (DATETIME). Neue Einträge erhalten automatisch
einen UTC-Zeitstempel vom Server. Client-Eingaben für dieses Feld werden ignoriert;
beim Bearbeiten bleibt es unverändert. Die Übersicht und das Bearbeitungsformular
zeigen Datum/Uhrzeit in der WordPress-Zeitzone an, ohne editierbares Datumsfeld.
Vor Einführung dieses Felds erstellte Datensätze behalten NULL („Nicht erfasst“),
da ihr ursprünglicher Anlagezeitpunkt nicht bekannt ist. Die Migration verändert
keine Kontaktdaten. Direkte SQL-Imports müssen einen bekannten UTC-Zeitpunkt explizit
angeben oder NULL verwenden. Eine Löschautomatik ist damit noch nicht verbunden.
Die Tests decken Migration/Bestandserhalt, Unveränderlichkeit, ignorierte Clientdaten
und die Sommer-/Winterzeitumrechnung ab.
## Öffentliches Anmeldeformular (ab Version 0.4.0)

Einbinden als Widget **VKH: Interessenten-Anmeldung** unter Design → Widgets,
oder mit `[vkh_interessenten]` in einem Shortcode-Block einer Seite bzw. eines
Widgetbereichs. Es wird keine Seite automatisch veröffentlicht oder verändert.
Vor der öffentlichen Nutzung eine veröffentlichte Datenschutzseite unter
Einstellungen → Datenschutz hinterlegen und deren Inhalt um diese Verarbeitung
ergänzen. Der Link erscheint dann automatisch im Formular. Das Formular erfasst
Vorname, Nachname, E-Mail, optional Telefon und Hundeerfahrung. Newsletter und
weitere Infos haben getrennte, nicht vorausgewählte Einwilligungen; mindestens
eine muss gewählt sein. Ab Version 0.5.0 gibt es Double-Opt-in-Bestätigungs-Mails; weiterhin keinen Newsletterversand.

### Speicherung und Backend

`vkh_anmeldung` enthält den Fremdschlüssel zum Interessenten, die E-Mail der
Anfrage, Zwecke, UTC-Zeitpunkt, Version/Wortlaut der Einwilligung und einen leeren
Bestätigungszeitpunkt. Eine eindeutige E-Mail-Prüfsumme verhindert Mehrfachanmeldungen.
Die Anfrage erscheint in der Interessentenliste und ist dort **unbestätigt**.
Der Wortlaut lässt sich beim Bearbeiten einsehen; er wird nicht nachträglich
geändert. Bestehende Kontaktdaten werden durch öffentliche Anfragen nie überschrieben.
Bei bereits vorhandener Adresse wird eine noch fehlende Anfrage dem bestehenden
Interessenten zugeordnet. Weitere öffentliche Anfragen ändern keine bestehende
Anfrage. Die öffentliche Antwort ist für neue und vorhandene Adressen gleich.
Eine ungeprüfte Anmeldung ist keine verifizierte Empfängerliste für spätere Werbung.
Die Bestätigung bzw. Änderung von Zwecken/Adressen braucht später einen gesonderten,
verifizierten Ablauf. Beim Löschen eines Interessenten wird die Anfrage über den
Fremdschlüssel mit entfernt. Für noch bestehende Einträge wird keine automatische
Löschung eingeführt.

### Schutzmaßnahmen und Grenzen

- Ausschließlich POST; keine öffentliche Lese-, Bearbeitungs- oder Bestätigungsfunktion.
- Vorbereitete SQL-Abfragen/WordPress-Datenbankmethoden, geteilte serverseitige
  Feldvalidierung, feste erlaubte Felder und maskierte Ausgabe.
- Maximal 8 KB Anfragedaten, skalare Eingaben, Längen-/E-Mail-/Boolean-Prüfung.
- WordPress-Nonce plus IP-gebundener HMAC-Formulartoken (2 Sekunden bis 1 Stunde),
  Origin-/Referer-Prüfung, Honeypot und Wiederverwendungsschutz nach Speicherung.
  WordPress-Gast-Nonces allein wären kein ausreichender CSRF-Schutz.
- Feste Stundenfenster: maximal 5 verarbeitete Anfragen pro IP und 100 global,
  zusätzliche E-Mail-Sperre nach Speicherung. Zähler werden durch eine MySQL-
  Advisory-Sperre serialisiert. Zählerfehler führen zum Ablehnen der Anfrage.
  Schlüssel sind kurzlebige HMAC-Werte, keine gespeicherten Klartext-IP-Adressen.
  Transients verfallen nach einer Stunde; WordPress kann abgelaufene Datensätze
  erst bei Zugriff/Cron physisch bereinigen.
- Neue Kontaktdaten und die Einwilligung werden gemeinsam in einer Transaktion
  gespeichert. Fehler rollen zurück und werden ohne Formulardaten protokolliert
  (`public_signup_failed`). Keine Namen/E-Mails in Redirect-URLs oder Logs.
- Öffentliches AJAX liefert nur frische Formular-/Nonce-Tokens, niemals Kontaktdaten.
  JavaScript erneuert Tokens auch bei zwischengespeicherten Seiten. Ohne JavaScript
  muss die Formularseite vom Vollseiten-Cache ausgenommen sein. Lange geöffnete
  Formulare müssen nach einer Stunde neu geladen werden.
- IP-Ermittlung verwendet ausschließlich REMOTE_ADDR, keine unkontrollierten
  X-Forwarded-For-Header. Hinter einem Reverse Proxy muss die echte Client-IP
  vertrauenswürdig auf Serverebene konfiguriert werden. Geteilte IPs teilen das Limit.
  Gegen verteilte Bots/DoS sind zusätzlich Hosting-/WAF-Maßnahmen erforderlich.

HTTPS ist für den produktiven Einsatz erforderlich. Kein Schutz bietet eine
absolute Sicherheitsgarantie. Automatische Tests decken zusätzlich SQL-ähnliche
Eingaben, XSS-Ausgabe, manipulierte IDs/Bestätigung, CSRF/Tokenfehler, Honeypot,
Einwilligungsprüfung, Duplikate, Rate-Limits, Transaktionsfehler, Fremdschlüssel
und die Zusicherung ab, den kontrollierten Versand über einen abgefangenen Test-Mailtransport. Die Integrationstests
bereinigen auch die unter ihrem zufälligen Testpräfix angelegten Zähler.
## Double-Opt-in (aktueller Stand ab Version 0.5.0)

Dieser Abschnitt ersetzt den früheren reinen Sammelmodus aus Version 0.4.0.
Neue Anmeldungen erhalten eine Bestätigungs-Mail über `wp_mail()`. Absender ist
`admin_email` aus Einstellungen → Allgemein. Es wird kein eigener SMTP-Zugang
im Plugin hinterlegt. Die WordPress-/Hosting-Mailkonfiguration muss funktionieren;
eine Absenderadresse allein stellt keinen Maildienst bereit. Auf dem lokalen
XAMPP war zum Prüfzeitpunkt kein SMTP-Server auf localhost:25 erreichbar.
SMTP-Zugangsdaten gehören in eine separate geschützte Konfiguration oder ein
dafür vorgesehenes SMTP-Plugin, nicht ins Repository. Der Provider muss den
verwendeten Absender zulassen. Für externe Tests ist zudem eine öffentlich
zugängliche Website-Adresse nötig; localhost-Links funktionieren nur lokal.

Der Link enthält einen kryptographisch zufälligen 256-Bit-Token. In der Datenbank
wird ausschließlich dessen SHA-256-Hash gespeichert. Ablauf: 48 Stunden. GET
zeigt nur eine Bestätigungsseite; erst ein POST per Bestätigungsbutton, mit
Nonce und Same-Origin-Prüfung, bestätigt die Anfrage. Die atomare Aktualisierung
verhindert parallele Mehrfachverwendung. Bei Erfolg wird `bestaetigt_am` in UTC
gespeichert und der Token verbraucht. Alte/abgelaufene Links verändern keine Daten.
Die Bestätigungsseite setzt no-store, no-referrer, noindex und eine restriktive CSP.
Token-URLs dürfen nicht öffentlich geteilt werden; Webserver-Zugriffslogs können
sie enthalten und müssen entsprechend geschützt und zeitlich begrenzt sein.

Erneutes Eintragen einer unbestätigten Adresse kann nach einer Stunde eine neue
Bestätigungs-Mail auslösen. Dabei wird der alte Link ungültig. An bereits bestätigte
Adressen wird keine weitere Bestätigungs-Mail verschickt. Vorhandene Kontaktdaten
und der ursprüngliche Einwilligungswortlaut werden nicht überschrieben.

Bestehende Anfragen werden nicht automatisch angeschrieben oder als bestätigt
markiert. Unter Interessenten → Bearbeiten gibt es einen geschützten Button
„Bestätigungsmail senden / erneut senden“ mit mindestens einer Minute Abstand.
Einwilligung und Versandstatus sind dort einsehbar. Bei Transportfehlern bleibt
der Datensatz erhalten und unbestätigt; eine Backend-Warnung und eine generische
Frontend-Meldung informieren über den gestörten Versand. Die Speicherung der
Anmeldung und Mailzustellung sind getrennt. `wp_mail() = true` bedeutet lediglich
Übergabe an den Mailtransport, nicht nachgewiesenen Eingang im Postfach.

Newsletter und regulärer Informationsversand bleiben separate spätere Funktionen.
Dafür darf nur die zur Bestätigung gehörende E-Mail aus `vkh_anmeldung` verwendet
werden, nicht eine nachträglich geänderte Kontaktadresse ohne neue Bestätigung.

Die Tests fangen sämtliche ausgehenden E-Mails ab (keine reale Zustellung an
Testadressen). Sie prüfen zusätzlich Ablauf, Fälschung, Token-Hash, Wiederverwendung,
Neuausstellung, Versandfehler/-wiederholung, Absender, bestätigten Backend-Status
und Berechtigung/Nonce des administrativen Wiederholungsbuttons.
## Würfe und Welpen (ab Version 0.6.0)

„Unsere Welpen“ zeigt Würfe als aufklappbare Gruppen mit Name, Geschlecht und
Verkaufsstatus je Welpe. Statuswerte: `nein` (rot), `vorgemerkt` (gelb), `verkauft`
(grün), stets mit Text zusätzlich zur Farbe. Dropdown-Änderungen werden mit
JavaScript per AJAX gespeichert. Ohne JavaScript steht ein Speichern-Button bereit.
„Verfügbar“ zählt ausschließlich den Status `nein`, nicht vorgemerkte Welpen.

„Neuen Wurf anlegen“ führt durch Wurfbezeichnung/optionales Datum, die Welpen
und eine abschließende Prüfung. Start mit drei leeren Welpenzeilen; 1–20 Welpen
können erfasst werden. Zurück erhält die Eingaben. Erst das abschließende Speichern
schreibt den Wurf und alle Welpen in einer Transaktion. Ohne JavaScript sind die
Wurf- und drei Welpenfelder gemeinsam sichtbar. Serverseitige Validierung bleibt
immer aktiv. Bei Fehlern bleiben gültig darstellbare Eingaben im Formular erhalten.

Neue Tabellen: `vkh_wurf` und `vkh_welpe`. Die Bezeichnung eines Wurfs ist eindeutig.
Ein Foreign Key verknüpft die Welpen mit dem Wurf; Löschen eines Wurfs auf Datenbank-
ebene entfernt seine Welpen mit. Es gibt keine öffentliche Schreibschnittstelle.
Die Verwaltung ist auf `manage_options` beschränkt; Wurf-Anlage und Statusänderung
haben Nonces, Statusänderungen zusätzlich einen je Welpe gebundenen Nonce.
Keine direkte Verbindung zur bisherigen `vkh_hund`-Tabelle und keine Löschoberfläche.

Die lokal gewünschten Beispieldaten A–E wurden einmalig angelegt und als Beispiel
markiert. Sie werden bei Installation/Aktivierung auf anderen Systemen nicht
automatisch eingespielt. Ein Schema-Update überschreibt keine bestehenden Würfe.

Neue Tests prüfen gemeinsame Speicherung, doppelte Bezeichnungen, Statuswechsel,
SQL-/XSS-Eingaben, unzulässige Felder, Fremdschlüssel/Cascade, Rollback, sichere
Formularausgabe sowie Berechtigungen und Nonces beider HTTP-Schreibwege.
## Würfe bearbeiten (ab Version 0.6.1)

Jeder aufgeklappte Wurf hat „Wurf bearbeiten“. Dasselbe dreistufige Formular lädt
Wurfbezeichnung, Datum und alle Welpen vorausgefüllt. Änderungen an Name,
Geschlecht und Verkaufsstatus sowie zusätzliche Welpen werden gemeinsam gespeichert.
Bestehende Wurf-/Welpen-IDs und das ursprüngliche Anlagedatum bleiben erhalten.
Vorhandene Welpen können hier nicht gelöscht werden. Neue, noch nicht gespeicherte
Zeilen lassen sich entfernen. Fehlen gespeicherte Welpen im POST oder gehört eine
ID zu einem anderen Wurf, wird die Änderung vollständig abgelehnt. Eine gleichzeitige
Änderung des Namens/Datums ist ebenfalls Teil derselben Transaktion. Eine fehlgeschlagene
Änderung lässt den bisherigen Stand unverändert. Keine Datenbankmigration erforderlich.
Die Integrationstests decken diese Fälle und das vorausgefüllte Bearbeitungsformular ab.
## Frontend-Widget / nativer Block: Aktueller Wurf (ab Version 0.7.0)

Klassisches Widget: **VKH: Aktueller Wurf** unter Design → Widgets.
Für Block-Themes, den Site-Editor und Seiten gibt es zusätzlich den nativen Block
**VKH: Aktueller Wurf** (Kategorie Widgets). Kein Shortcode. Beide verwenden denselben
Renderer und zeigen ausschließlich Welpennamen und Verkaufsstatus des neuesten Wurfs.
Es wird keine Seite und kein Template automatisch geändert.

Auswahl des neuesten Wurfs: Wurfdatum absteigend; bei fehlendem Datum wird der
UTC-Anlagetag verwendet. Bei gleichem Tag entscheiden Anlagedatum/-zeit und ID.
Dadurch verdrängt ein später erfasster historischer Wurf mit bekanntem älterem
Wurfdatum keinen jüngeren Wurf. Für historische Daten deshalb das echte Wurfdatum
pflegen. Die lokal vorhandenen Beispielwürfe haben kein erfundenes Wurfdatum.

Mindestens ein Welpe mit Status `nein`: Überschrift „Wir haben aktuell Welpen“.
Sind alle vorgemerkt/verkauft, entfällt nur die Überschrift, die Namens-/Statusliste
bleibt sichtbar. Rot = Nein, Gelb = Vorgemerkt, Grün = Verkauft; Text ergänzt die
Farben. Ältere Würfe beeinflussen die Überschrift nicht. Ohne Wurf/Welpen oder bei
Datenbankfehlern entsteht keine öffentliche Ausgabe. DB-Fehler werden datensparsam
mit Kategorie `latest_litter_failed` protokolliert. Das Widget erzeugt keine neue
öffentliche Schreibschnittstelle und liest keine Interessenten-/Kontaktfelder.

Der Inhalt wird bei jedem ungecachten Seitenaufruf frisch aus der Datenbank gelesen.
Ein vorgeschalteter Vollseiten-Cache kann nach Änderungen eine Leerung erfordern.
Die Suite prüft Widget-/Blockregistrierung, Gastzugriff, Datumsauswahl/Fallback,
Verfügbarkeit ausschließlich im neuesten Wurf, alle Statusfälle, sichere Ausgabe,
Block-/Widgetrendering und kontrolliertes Verhalten bei Datenbankfehlern.
### Kompakte Widget-Anzeige (ab Version 0.7.1)

Im Frontend steht das Geschlecht als ♂ bzw. ♀ neben dem Welpennamen.
Die Statusspalte zeigt nur den farbigen Ampelpunkt. Die ausgeschriebenen Angaben
zu Status und Geschlecht bleiben ausschließlich für Screenreader verfügbar.
Die Backend-Tabellen behalten ihre ausgeschriebenen Status- und Geschlechtsangaben.
## Interessenten-Zuordnung (ab Version 0.8.0)

„Interesse an“ ist eine gemeinsame Auswahl: ausschließlich Welpen aus einem
vorhandenen Wurf, nach Wurf gruppiert, oder Safron (Zuchtrüde) aus `vkh_hund`.
„Noch offen“ entfernt die Zuordnung. In diesem Stand wird Safron über seinen
Namen ermittelt (bei doppelten Namenseinträgen der erste Datensatz). Andere
Hunde sind auch bei manipulierten Formularen nicht zuweisbar. Nach Umbenennung
Safrons muss diese Identifikation angepasst werden.

`vkh_interessent.welpe_id` ist ein neuer nullable Foreign Key auf `vkh_welpe`.
`hund_id` bleibt für Safron bestehen. Die Anwendung lässt jeweils höchstens eine
Zuordnung zu und setzt die andere beim Wechsel auf NULL. Ein FK-Löschen des
Welpen/Wurfs oder Safrons erhält den Interessenten und setzt seine Zuordnung auf
NULL. Zuordnen verändert keine Verkaufs-/Reservierungsstatus. Mehrere Interessenten
können demselben Welpen zugeordnet werden. Bestehende Einwilligungen/Bestätigungen
und Anlagedaten bleiben unverändert. Die öffentliche Anmeldung bietet weiterhin
keine Hundezuordnung und darf diese nicht per zusätzlichen POST-Feldern setzen.
Die SQL-Datei enthält die Tabellen in der für die Fremdschlüssel nötigen Reihenfolge.

Die Suite prüft erlaubte Kandidaten, die Gruppierung und Vorauswahl im Backend,
Speicherung, Wechsel, Entfernen, ungültige IDs, doppelte Zuordnung, Abweisung
anderer erwachsener Hunde und SET NULL bei Löschung. Vorhandene frühere Zuordnungen
zu anderen Hunden werden nicht stillschweigend gelöscht; die Liste kennzeichnet
sie zur Überprüfung, während die Auswahl sie nicht mehr anbietet.