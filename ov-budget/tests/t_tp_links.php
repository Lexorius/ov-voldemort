<?php
declare(strict_types=1);
/*
 * Bezüge der Tagesordnungspunkte: speichern, anzeigen, Protokolltext,
 * „besprochen in", Auswahl im Formular, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'kalender_besprechung_wochen' => '6'];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['tabellen'] = [];
$GLOBALS['rechte'] = ['view_vehicles' => true, 'view_radios' => true, 'view_meetings' => true, 'manage_meetings' => true, 'view_events' => true];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    foreach ($GLOBALS['tabellen'] as $marke => $rows) {
        if (str_contains($sql, $marke)) { return is_callable($rows) ? $rows($p) : $rows; }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? false; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'functions' => []]; }
function tp_select(): string { return 'SELECT tp.* FROM talking_points tp LEFT JOIN meetings m ON m.id = tp.meeting_id'; }
function kalender_termine(string $von, string $bis, array $u): array { return $GLOBALS['termine'] ?? []; }
function event_find(?int $id): ?array { return $GLOBALS['events'][$id] ?? null; }
function event_kosten(int $id): array { return ['ausgaben' => 400.0, 'einnahmen' => 0.0, 'buchungen' => 1]; }
function event_guests(int $id, string $status = ''): array { return []; }
function event_stats(array $g): array { return ['personen' => 0]; }
function verpflegung_personen(array $e, array $stats): int { return (int)($e['verpflegung_personen'] ?? 0); }
function verpflegung_saetze(?string $datum = null): array { return ['fruehstueck' => 4.0, 'mittag' => 8.0, 'abend' => 8.0]; }
function verpflegung_kalkulation(array $e, array $s, int $p): array {
    $z = []; $g = 0.0;
    foreach (['fruehstueck' => 'Frühstück', 'mittag' => 'Mittagessen', 'abend' => 'Abendessen'] as $k => $l) { $n = (int)$e['verpflegung_' . $k]; $z[$k] = ['label' => $l, 'anzahl' => $n, 'satz' => $s[$k], 'betrag' => $n * $s[$k] * $p]; $g += $n * $s[$k] * $p; }
    return ['personen' => $p, 'zeilen' => $z, 'gesamt' => $g, 'ohne_satz' => false];
}
$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'tp_links'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Speichern ---------- */
$check('ids bereinigt', tp_link_ids(['3', '0', 'x', '3', 5]) === [3, 5] && tp_link_ids(null) === []);
tp_links_speichern(7, ['termine' => ['4'], 'events' => ['6'], 'vehicles' => ['2', '2', '9'], 'radios' => []]);
$check('alte Bezüge gelöscht, neue eingetragen', count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM tp_links'))) === 1
    && count($GLOBALS['inserts']) === 4 && $GLOBALS['inserts'][0][1] === ['tp_id' => 7, 'typ' => 'termin', 'ziel_id' => 4]
    && $GLOBALS['inserts'][1][1] === ['tp_id' => 7, 'typ' => 'event', 'ziel_id' => 6] && $GLOBALS['inserts'][3][1] === ['tp_id' => 7, 'typ' => 'vehicle', 'ziel_id' => 9]);
$GLOBALS['tabellen']['FROM tp_links WHERE tp_id'] = [['typ' => 'termin', 'ziel_id' => 4], ['typ' => 'vehicle', 'ziel_id' => 2]];
$check('lesen', tp_links(7) === ['termin' => [4], 'event' => [], 'vehicle' => [2], 'radio' => []]);

/* ---------- Anzeige ---------- */
$GLOBALS['tabellen']['FROM tp_links l'] = [
    ['tp_id' => 7, 'typ' => 'termin', 'ziel_id' => 4, 'termin_titel' => 'Übung', 'termin_beginn' => '2026-10-17 08:00:00', 'termin_ganztag' => 0, 'fahrzeug' => null, 'fahrzeug_ruf' => null, 'geraet' => null, 'geraet_ruf' => null],
    ['tp_id' => 7, 'typ' => 'vehicle', 'ziel_id' => 2, 'termin_titel' => null, 'termin_beginn' => null, 'termin_ganztag' => null, 'fahrzeug' => 'GKW 1', 'fahrzeug_ruf' => 'Heros HN 21/51', 'geraet' => null, 'geraet_ruf' => null],
    ['tp_id' => 8, 'typ' => 'radio', 'ziel_id' => 3, 'termin_titel' => null, 'termin_beginn' => null, 'termin_ganztag' => null, 'fahrzeug' => null, 'fahrzeug_ruf' => null, 'geraet' => 'HRT 12', 'geraet_ruf' => ''],
    ['tp_id' => 8, 'typ' => 'vehicle', 'ziel_id' => 99, 'termin_titel' => null, 'termin_beginn' => null, 'termin_ganztag' => null, 'fahrzeug' => null, 'fahrzeug_ruf' => null, 'geraet' => null, 'geraet_ruf' => null],
    ['tp_id' => 9, 'typ' => 'event', 'ziel_id' => 6, 'event_titel' => 'Sommerfest', 'event_beginn' => '2026-07-10 16:00:00', 'termin_titel' => null, 'termin_beginn' => null, 'termin_ganztag' => null, 'fahrzeug' => null, 'fahrzeug_ruf' => null, 'geraet' => null, 'geraet_ruf' => null],
];
$l = tp_links_fuer([7, 8]);
$check('je Punkt mit Beschriftung und Adresse, gelöschte Ziele fehlen', count($l[7]) === 2 && count($l[8]) === 1 && $l[7][0]['label'] === 'Übung' && $l[7][0]['zusatz'] === '17.10.2026, 08:00 Uhr'
    && str_contains($l[7][0]['url'], 'p=kalender_edit') && $l[7][1]['zusatz'] === 'Heros HN 21/51' && str_contains($l[7][1]['url'], 'p=vehicle') && $l[8][0]['typ'] === 'radio');
$check('ohne ids nichts', tp_links_fuer([]) === []);
$check('Protokolltext', tp_links_text($l[7]) === 'Termin Übung (17.10.2026, 08:00 Uhr) · Fahrzeug GKW 1 (Heros HN 21/51)' && tp_links_text($l[8]) === 'Funkgerät HRT 12');
$l9 = tp_links_fuer([9]);
$check('Veranstaltung als Bezug', $l9[9][0]['typ'] === 'event' && $l9[9][0]['label'] === 'Sommerfest' && $l9[9][0]['zusatz'] === '10.07.2026' && str_contains($l9[9][0]['url'], 'p=event'));

/* ---------- Kurzbericht der Veranstaltung ---------- */
$GLOBALS['events'] = [6 => ['id' => 6, 'titel' => 'Sommerfest', 'beginn' => '2026-07-10 16:00:00', 'ende' => null, 'ort' => 'Unterkunft', 'typ_label' => 'Feier', 'status' => 'geplant',
    'budget_name' => 'Jugendarbeit', 'kosten_geplant' => 1000, 'verpflegung' => 1, 'verpflegung_personen' => 12, 'verpflegung_fruehstueck' => 0, 'verpflegung_mittag' => 1, 'verpflegung_abend' => 1]];
$b = tp_event_kurzbericht(6);
$check('Kurzbericht: Topf, Kosten, Verpflegung', $b['budget'] === 'Jugendarbeit' && $b['geplant'] === 1000.0 && $b['gebucht'] === 400.0 && $b['personen'] === 12 && $b['verpflegung']['gesamt'] === 192.0);
$z = tp_event_kurzbericht_zeilen($b);
$check('Kurzbericht als Zeilen', $z[0] === 'Veranstaltung Sommerfest (Feier) – 10.07.2026, 16:00 Uhr, Unterkunft' && $z[1] === 'Budgettopf: Jugendarbeit · geplant 1.000,00 € · gebucht 400,00 €'
    && $z[2] === 'Verpflegung: 12 Personen, 1 × Mittagessen à 8,00 €, 1 × Abendessen à 8,00 € = 192,00 €');
$GLOBALS['events'][7] = ['id' => 7, 'titel' => 'Dienstabend', 'beginn' => '2026-07-11 00:00:00', 'ende' => '2026-07-12 00:00:00', 'ort' => '', 'typ_label' => '', 'status' => 'geplant', 'budget_name' => null, 'kosten_geplant' => 0, 'verpflegung' => 0];
$z = tp_event_kurzbericht_zeilen(tp_event_kurzbericht(7));
$check('Kurzbericht ohne Topf und Verpflegung', count($z) === 2 && $z[0] === 'Veranstaltung Dienstabend – 11.07.2026 bis 12.07.2026' && $z[1] === 'Budgettopf: keiner · geplant 0,00 € · gebucht 400,00 €');
$check('Kurzberichte aus den Bezügen, gelöschte übersprungen', array_keys(tp_event_kurzberichte(['x' => [['typ' => 'event', 'id' => 6], ['typ' => 'event', 'id' => 99], ['typ' => 'vehicle', 'id' => 2]]])) === [6] && tp_event_kurzbericht(99) === null);

$check('ganztägiger Termin ohne Uhrzeit', tp_link_anzeige(['typ' => 'termin', 'ziel_id' => 1, 'termin_titel' => 'Lehrgang', 'termin_beginn' => '2026-11-02 00:00:00', 'termin_ganztag' => 1])['zusatz'] === '02.11.2026');

/* ---------- Besprochen in ---------- */
$GLOBALS['tabellen']['JOIN tp_links l ON l.tp_id = tp.id'] = static fn(array $p) => $p === ['vehicle', 2] ? [['id' => 7, 'titel' => 'Reifen GKW', 'meeting_datum' => '2026-10-12', 'meeting_titel' => 'OV-Runde', 'status_label' => 'Offen', 'status_color' => '#0284c7', 'ergebnis' => '']] : [];
$check('Punkte zu einem Fahrzeug', count(tps_fuer('vehicle', 2)) === 1 && tps_fuer('vehicle', 3) === [] && tps_fuer('unsinn', 2) === [] && tps_fuer('vehicle', 0) === []);

/* ---------- Auswahl im Formular ---------- */
$GLOBALS['termine'] = [['id' => 4, 'titel' => 'Übung', 'beginn' => '2026-10-17 08:00:00', 'ganztag' => 0], ['id' => 5, 'titel' => 'Dienstabend', 'beginn' => '2026-10-10 19:00:00', 'ganztag' => 0]];
$GLOBALS['tabellen']['FROM calendar_entries k'] = [['id' => 1, 'titel' => 'Alter Termin', 'beginn' => '2026-01-05 10:00:00', 'ganztag' => 0]];
$GLOBALS['tabellen']['FROM vehicles'] = [['id' => 2, 'bezeichnung' => 'GKW 1', 'funkrufname' => '']];
$GLOBALS['tabellen']['FROM radios'] = [['id' => 3, 'bezeichnung' => 'HRT 12', 'funkrufname' => '']];
$GLOBALS['tabellen']['FROM events e'] = [['id' => 6, 'titel' => 'Sommerfest', 'beginn' => '2026-07-10 16:00:00']];
$a = tp_links_auswahl(['termin' => [1], 'event' => [], 'vehicle' => [], 'radio' => []], current_user());
$check('Auswahl: anstehende und schon verknüpfte Termine, nach Beginn', array_column($a['termine'], 'id') === [1, 5, 4] && count($a['vehicles']) === 1 && count($a['radios']) === 1 && count($a['events']) === 1);
$GLOBALS['rechte']['view_radios'] = false;
$check('ohne Recht keine Geräte', tp_links_auswahl(['termin' => [], 'event' => [], 'vehicle' => [], 'radio' => []], current_user())['radios'] === []);
$GLOBALS['rechte']['view_radios'] = true;

/* ---------- Ansichten ---------- */
$html = render_partial('partials/tp_links', ['liste' => $l[7]]);
$check('Kennzeichen mit Links', substr_count($html, 'class="chip"') === 2 && str_contains($html, 'Übung') && str_contains($html, 'GKW 1') && str_contains($html, 'p=vehicle'));
$check('ohne Bezüge nichts', trim(render_partial('partials/tp_links', ['liste' => []])) === '');
$html = render_partial('partials/tp_links', ['liste' => $l9[9], 'events' => tp_event_kurzberichte($l9)]);
$check('Kennzeichen mit Kurzbericht der Veranstaltung', str_contains($html, 'Sommerfest') && str_contains($html, 'Budgettopf: Jugendarbeit') && str_contains($html, 'Verpflegung: 12 Personen'));
$html = render_partial('partials/tp_besprochen', ['punkte' => tps_fuer('vehicle', 2)]);
$check('Besprochen in: Punkt mit Besprechung', str_contains($html, 'In Besprechungen') && str_contains($html, 'Reifen GKW') && str_contains($html, 'OV-Runde') && str_contains($html, '12.10.2026'));
$check('Besprochen in: leer', str_contains(render_partial('partials/tp_besprochen', ['punkte' => []]), 'Noch in keinem'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
