<?php
declare(strict_types=1);
/*
 * Veranstaltungsseite und Formular: Was eingeklappt ist, was offen steht –
 * und wo das Löschen steckt.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'haushaltsjahr' => '2026', 'ov_name' => 'THW OV Musterstadt'];

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
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'] ?? true; }
function current_user(): ?array { return ['id' => 1]; }
function upload_dir(): string { return sys_get_temp_dir(); }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'vehicles', 'vehicle_files',
          'contacts', 'divera_vehicles', 'notify', 'connector', 'events'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$event = [
    'id' => 12, 'titel' => 'Jahresabschlussfeier', 'beschreibung' => '', 'ort' => 'Unterkunft',
    'beginn' => '2026-12-05 18:00:00', 'ende' => null, 'status' => 'geplant',
    'typ_label' => 'Feier', 'typ_color' => '#db2777', 'typ_id' => 5,
    'jahr' => 2026, 'budget_id' => null, 'budget_name' => null, 'fachgruppe_label' => null,
    'fachgruppe_id' => null, 'kosten_geplant' => '0.00', 'connector_id' => null,
    'code_laenge' => 6, 'begleiter_max' => 2, 'kommentare_erlaubt' => 1, 'vertretung_erlaubt' => 1,
    'rueckmeldung_bis' => null, 'hinweis' => '', 'notiz' => '', 'gaesteliste' => 1,
    'teilnehmer_geplant' => null, 'teilnehmer_ist' => null,
];
$gast = [
    'id' => 1, 'event_id' => 12, 'contact_id' => 5, 'name' => '', 'vorname' => 'Anna',
    'nachname' => 'Beispiel', 'organisation' => '', 'kontakt_titel' => '', 'anrede' => 'Frau',
    'code' => 'AB23CD', 'status' => 'zusage', 'begleiter' => 1, 'vertretung' => '',
    'kommentar' => null, 'quelle' => 'einladung', 'geantwortet_am' => '2026-11-02 19:20:00',
];
$daten = static fn(array $extra = []) => array_merge([
    'event' => $event, 'gaeste' => [$gast], 'stats' => event_stats([$gast]),
    'dateien' => [], 'buchungen' => [], 'kosten' => ['ausgaben' => 0.0, 'einnahmen' => 0.0, 'buchungen' => 0],
    'connector' => null, 'kandidaten' => [], 'kontaktSuche' => '', 'verteiler' => [],
], $extra);

$_SERVER['REQUEST_URI'] = '/?p=event';
$_GET = ['p' => 'event', 'id' => '12'];

/* ================= Veranstaltungsseite ================= */
$html = render_partial('event', $daten());
$check('Einladen ist eingeklappt', str_contains($html, '<summary>Gäste einladen</summary>'));
$check('mit Gästen bleibt es zu',
    !str_contains($html, '<details class="mt" open>' . "\n" . '    <summary>Gäste einladen'));
$check('Datei-Formular ist eingeklappt', str_contains($html, '<summary>Datei hinzufügen</summary>'));
$check('ohne Dateien steht es offen',
    str_contains($html, '<details class="mt" open>' . "\n" . '    <summary>Datei hinzufügen'));
$check('kein Löschen mehr auf der Seite',
    !str_contains($html, 'name="action" value="loeschen"')
    && !str_contains($html, 'Veranstaltung löschen'));
$check('Bearbeiten führt weiter', str_contains($html, 'p=event_edit&amp;id=12'));
$check('Gästeliste bleibt sichtbar', str_contains($html, 'Anna Beispiel'));

// Ohne Gäste steht das Einladen offen
$html = render_partial('event', $daten(['gaeste' => [], 'stats' => event_stats([])]));
$check('ohne Gäste offen', str_contains($html, '<details class="mt" open>' . "\n" . '    <summary>Gäste einladen'));

// Während einer Kontaktsuche ebenfalls
$html = render_partial('event', $daten(['kontaktSuche' => 'Meier', 'kandidaten' => []]));
$check('bei laufender Suche offen',
    str_contains($html, '<details class="mt" open>' . "\n" . '    <summary>Gäste einladen'));
$check('nur ein open-Merkmal', !str_contains($html, 'open open'));

// Ohne Rechte gibt es die Formulare gar nicht
$GLOBALS['rechte'] = false;
$html = render_partial('event', $daten());
$check('ohne Rechte kein Einladen', !str_contains($html, 'Gäste einladen')
    && !str_contains($html, 'Datei hinzufügen'));
$GLOBALS['rechte'] = true;

/* ================= Bearbeiten-Formular ================= */
$_GET = ['p' => 'event_edit', 'id' => '12'];
$html = render_partial('event_edit', [
    'event' => $event, 'errors' => [], 'budgets' => [], 'fachgruppen' => [],
    'connectoren' => [], 'ohneListe' => [], 'jahr' => 2026,
]);
$check('Löschen steht jetzt hier', str_contains($html, 'name="action" value="loeschen"')
    && str_contains($html, '>Veranstaltung löschen</button>'));
$check('als roter Knopf', str_contains($html, 'btn btn--danger'));
$check('mit zwei Rückfragen', str_contains($html, 'data-confirm="Diese Veranstaltung')
    && str_contains($html, 'data-confirm2="Bist du wirklich sicher?"'));
$check('sagt, was verschwindet', str_contains($html, 'Gästeliste, Rückmeldungen und Dateien'));

// Beim Anlegen gibt es nichts zu löschen
$html = render_partial('event_edit', [
    'event' => array_merge($event, ['id' => null]), 'errors' => [], 'budgets' => [],
    'fachgruppen' => [], 'connectoren' => [], 'ohneListe' => [], 'jahr' => 2026,
]);
$check('beim Anlegen kein Löschen', !str_contains($html, 'value="loeschen"'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
