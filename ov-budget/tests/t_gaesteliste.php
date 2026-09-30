<?php
declare(strict_types=1);
/* Gästeliste auf Papier: Einlassliste, ganze Liste, Offene – und was unten steht. */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt'];

function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
function upload_dir(): string { return sys_get_temp_dir(); }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';
require $app . '/src/lib/webpush.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/vehicle_files.php';
require $app . '/src/lib/contacts.php';
require $app . '/src/lib/divera_vehicles.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/connector.php';
require $app . '/src/lib/events.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$event = [
    'id' => 12, 'titel' => 'Jahresabschlussfeier', 'beginn' => '2026-12-05 18:00:00',
    'ende' => '2026-12-05 23:00:00', 'ort' => 'Unterkunft', 'rueckmeldung_bis' => '2026-11-28',
    'status' => 'geplant', 'typ_label' => 'Grillabend', 'typ_color' => '#be123c',
    'begleiter_max' => 2, 'kommentare_erlaubt' => 1,
    'vertretung_erlaubt' => 1, 'code_laenge' => 6, 'connector_id' => 1,
];
$gaeste = [
    ['id' => 1, 'name' => '', 'vorname' => 'Anna', 'nachname' => 'Beispiel', 'organisation' => 'Stadt',
     'kontakt_titel' => 'Dr.', 'anrede' => 'Frau', 'position' => 'Pressestelle',
     'strasse' => 'Musterweg 1', 'plz' => '12345', 'ort' => 'Musterstadt', 'land' => '',
     'anschreiben' => '', 'email' => 'anna@example.de', 'telefon' => '+49 201 1234',
     'code' => 'AB23CD', 'status' => 'zusage', 'begleiter' => 2, 'vertretung' => '',
     'kommentar' => 'Bringe Kuchen mit', 'quelle' => 'einladung', 'geantwortet_am' => '2026-11-02 19:20:00'],
    ['id' => 2, 'name' => '', 'vorname' => 'Bernd', 'nachname' => 'Muster', 'organisation' => '',
     'kontakt_titel' => '', 'anrede' => 'Herr', 'position' => '',
     'strasse' => '', 'plz' => '', 'ort' => '', 'land' => '', 'anschreiben' => '',
     'code' => 'XY79ZK', 'status' => 'absage', 'begleiter' => 0, 'vertretung' => '',
     'kommentar' => null, 'quelle' => 'einladung', 'geantwortet_am' => '2026-11-03 08:00:00'],
    ['id' => 3, 'name' => 'Frau Oberbürgermeisterin', 'vorname' => '', 'nachname' => '', 'organisation' => 'Rathaus',
     'kontakt_titel' => '', 'anrede' => '', 'position' => 'Oberbürgermeisterin',
     'strasse' => 'Rathausplatz 1', 'plz' => '12345', 'ort' => 'Musterstadt', 'land' => '',
     'anschreiben' => 'Sehr geehrte Frau Oberbürgermeisterin',
     'code' => 'CD45EF', 'status' => 'vertretung', 'begleiter' => 1, 'vertretung' => 'Hr. Schmidt',
     'kommentar' => null, 'quelle' => 'mensch', 'geantwortet_am' => '2026-11-04 10:00:00'],
    ['id' => 4, 'name' => '', 'vorname' => 'Dora', 'nachname' => 'Dritte', 'organisation' => '',
     'kontakt_titel' => '', 'anrede' => 'Frau', 'position' => '',
     'strasse' => 'Dorfstraße 7', 'plz' => '12346', 'ort' => 'Nachbarort', 'land' => '',
     'anschreiben' => '',
     'code' => 'EF67GH', 'status' => 'offen', 'begleiter' => 0, 'vertretung' => '',
     'kommentar' => null, 'quelle' => '', 'geantwortet_am' => null],
];
$stats = event_stats($gaeste);

$_SERVER['REQUEST_URI'] = '/?p=event_print';
$_GET = ['p' => 'event_print', 'id' => '12'];

/* ---------- Ganze Liste ---------- */
$html = render_partial('event_print', [
    'event' => $event, 'gaeste' => $gaeste, 'stats' => $stats,
    'gezeigt' => $stats, 'wen' => 'alle', 'mitKommentaren' => true,
]);
$check('Kopf mit Ortsverband und Titel',
    str_contains($html, 'THW OV Musterstadt') && str_contains($html, 'Jahresabschlussfeier'));
$check('Überschrift Gästeliste', str_contains($html, '>Gästeliste<'));
$check('Termin ausgeschrieben', str_contains($html, '05.12.2026, 18:00 Uhr bis 23:00 Uhr'));
$check('Ort steht drauf', str_contains($html, 'Unterkunft'));
$check('Art steht im Kopf', str_contains($html, '<td>Art</td><td>Grillabend</td>'));
$check('alle vier Namen', str_contains($html, 'Anna Beispiel') && str_contains($html, 'Bernd Muster')
    && str_contains($html, 'Frau Oberbürgermeisterin') && str_contains($html, 'Dora Dritte'));
$check('Kästchen zum Abhaken', substr_count($html, '<td class="haken"><span></span></td>') === 4);
$check('Rückmeldungen ausgewiesen', str_contains($html, 'Zusage') && str_contains($html, 'Absage')
    && str_contains($html, 'Vertretung') && str_contains($html, 'offen'));
$check('Begleiter genannt', str_contains($html, '+2 Begleiter') && str_contains($html, '+1 Begleiter'));
$check('Vertretung genannt', str_contains($html, 'kommt: Hr. Schmidt'));
$check('Nachricht steht dabei', str_contains($html, 'Bringe Kuchen mit'));
$check('Zusammenzählung im Kopf', str_contains($html, '2 Zusagen') && str_contains($html, '1 Absagen')
    && str_contains($html, '1 offen'));
$check('erwartete Personen', str_contains($html, '<strong>5 Personen</strong>'));
$check('Rückmeldefrist', str_contains($html, '28.11.2026'));
$check('Fuß mit Stand', str_contains($html, 'Stand:'));
$check('Drucken-Knopf, im Ausdruck versteckt', str_contains($html, 'window.print()')
    && str_contains($html, '@media print'));

/* ---------- Einlassliste: nur Zusagen ---------- */
$nurZu = array_values(array_filter($gaeste,
    static fn($g) => in_array($g['status'], ['zusage', 'vertretung'], true)));
$html = render_partial('event_print', [
    'event' => $event, 'gaeste' => $nurZu, 'stats' => $stats,
    'gezeigt' => event_stats($nurZu), 'wen' => 'zusagen', 'mitKommentaren' => true,
]);
$check('heißt Einlassliste', str_contains($html, '>Einlassliste<'));
$check('Abgesagte fehlen', !str_contains($html, 'Bernd Muster') && !str_contains($html, 'Dora Dritte'));
$check('Summe unten stimmt', str_contains($html, '<td class="zahl">5</td>')
    && str_contains($html, '2 Einträge auf dieser Liste'));

/* ---------- Nur Offene ---------- */
$offen = array_values(array_filter($gaeste, static fn($g) => $g['status'] === 'offen'));
$html = render_partial('event_print', [
    'event' => $event, 'gaeste' => $offen, 'stats' => $stats,
    'gezeigt' => event_stats($offen), 'wen' => 'offen', 'mitKommentaren' => false,
]);
$check('Liste der Offenen', str_contains($html, 'Noch keine Rückmeldung')
    && str_contains($html, 'Dora Dritte') && !str_contains($html, 'Anna Beispiel'));
$check('ohne Nachrichten bleibt die Nachricht weg', !str_contains($html, 'Bringe Kuchen mit'));

/* ---------- Leere Auswahl ---------- */
$html = render_partial('event_print', [
    'event' => $event, 'gaeste' => [], 'stats' => $stats,
    'gezeigt' => event_stats([]), 'wen' => 'zusagen', 'mitKommentaren' => true,
]);
$check('leere Liste sagt es', str_contains($html, 'steht niemand auf der Liste'));

/* ---------- Einladungsliste für den Serienbrief ---------- */
$connector = ['id' => 1, 'name' => 'Test', 'url' => 'https://ov.example.de/connector',
              'kurz_url' => 'https://i.example.de'];
$_GET = ['p' => 'event_invites', 'id' => '12'];
$html = render_partial('event_invites', [
    'event' => $event, 'gaeste' => $gaeste, 'connector' => $connector,
    'ohneAnschrift' => 1, 'wen' => 'alle', 'mitQr' => true,
]);
$check('Überschrift Einladungsliste', str_contains($html, 'Einladungsliste'));
$check('Art vor dem Termin', str_contains($html, 'Grillabend · 05.12.2026'));
$check('Anschrift im Briefformat', str_contains($html, 'Musterweg 1')
    && str_contains($html, '12345 Musterstadt'));
$check('Anrede und Titel vor dem Namen', str_contains($html, 'Frau Dr. Anna Beispiel'));
$check('Organisation als erste Zeile', str_contains($html, '<div>Stadt</div>'));
$check('Briefanrede gebildet', str_contains($html, 'Sehr geehrte Frau Dr. Beispiel'));
$check('eigene Briefanrede gewinnt', str_contains($html, 'Sehr geehrte Frau Oberbürgermeisterin'));
$check('Einladungslink als Text', str_contains($html, 'https://i.example.de/AB23CD'));
$check('QR-Code je Person', substr_count($html, 'data-qr="https://i.example.de/') === 4);
$check('QR-Bibliothek eingebunden', str_contains($html, 'qrcode.js') && str_contains($html, 'qr-liste.js'));
$check('Code lesbar darunter', str_contains($html, '>AB23CD<'));
$check('fehlende Anschrift fällt auf', str_contains($html, 'ohne Anschrift')
    && str_contains($html, 'karte--ohne'));
$check('Hinweis auf Fehlende', str_contains($html, '1 Eingeladene haben keine Anschrift'));

// Ohne QR-Codes bleibt die Bibliothek weg
$html = render_partial('event_invites', [
    'event' => $event, 'gaeste' => $gaeste, 'connector' => $connector,
    'ohneAnschrift' => 1, 'wen' => 'alle', 'mitQr' => false,
]);
$check('ohne QR-Codes keine Bibliothek', !str_contains($html, 'qr-liste.js')
    && !str_contains($html, 'data-qr='));
$check('Link bleibt trotzdem', str_contains($html, 'https://i.example.de/AB23CD'));

// Ohne Connector gibt es keine Adresse
$html = render_partial('event_invites', [
    'event' => $event, 'gaeste' => $gaeste, 'connector' => null,
    'ohneAnschrift' => 0, 'wen' => 'alle', 'mitQr' => true,
]);
$check('ohne Connector ein Hinweis', str_contains($html, 'kein Connector eingetragen')
    && !str_contains($html, 'data-qr='));
$check('Codes stehen trotzdem da', str_contains($html, '>AB23CD<'));

// Nur, wer eine Anschrift hat
$post = array_values(array_filter($gaeste, 'event_guest_postfaehig'));
$check('postfähig erkannt', count($post) === 3);
$html = render_partial('event_invites', [
    'event' => $event, 'gaeste' => $post, 'connector' => $connector,
    'ohneAnschrift' => 1, 'wen' => 'post', 'mitQr' => true,
]);
$check('ohne Anschrift fehlt in der Auswahl', !str_contains($html, 'Bernd Muster')
    && !str_contains($html, 'ohne Anschrift'));

/* ---------- Bausteine für den Serienbrief ---------- */
$check('Anschrift eines Freitext-Gastes',
    event_guest_address(['name' => 'Gast X', 'nachname' => '', 'vorname' => '']) === ['Gast X']);
$check('Briefanrede ohne Kontakt fällt auf die Vorgabe zurück',
    str_contains(event_guest_salutation(['name' => 'Gast X']), 'Sehr geehrte'));

/* ---------- Zahlen für die Liste ---------- */
$check('Personen je Zeile: Person plus Begleiter',
    event_stats([$gaeste[0]])['personen'] === 3 && event_stats([$gaeste[2]])['personen'] === 2);
$check('Absage und Offene zählen niemanden',
    event_stats([$gaeste[1]])['personen'] === 0 && event_stats([$gaeste[3]])['personen'] === 0);

echo "$ok bestanden, $fail fehlgeschlagen\n";
