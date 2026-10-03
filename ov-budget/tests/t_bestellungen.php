<?php
declare(strict_types=1);
/*
 * Bestellungen: Nummer, nur freigegebene Wünsche, Status zieht die Wünsche
 * mit; Buchungen mit mehreren Wünschen und Fahrzeugen; Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'wunsch_modul_name' => 'Wünsche'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['tabellen'] = [];
$GLOBALS['zeilen'] = [];
$GLOBALS['anzahl'] = 0;
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
function db_row(string $sql, array $p = []): ?array {
    foreach ($GLOBALS['zeilen'] as $marke => $row) { if (str_contains($sql, $marke)) { return is_callable($row) ? $row($p) : $row; } }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'COUNT(*) FROM bestellungen') ? $GLOBALS['anzahl'] : $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
function list_id_by_slug(string $key, string $slug): ?int { return ['freigegeben' => 55, 'bestellt' => 60, 'beschafft' => 70][$slug] ?? null; }
function list_options(string $key, ?int $selected, string $empty = '– bitte wählen –', bool $onlyActive = true): string { return ''; }
function wish_find_full(int $id): ?array { return $GLOBALS['wuensche'][$id] ?? null; }
function wish_query(array $f = []): array { return array_values(array_filter($GLOBALS['wuensche'], static fn($w) => $w['status_slug'] === ($f['status_slug'] ?? $w['status_slug']))); }
function wish_mark_ordered(array $wish, array $user): ?string { $GLOBALS['bestellt'][] = (int)$wish['id']; return null; }
function notify_queue(array $ids, string $e, string $t, string $x, string $u = ''): int { return 0; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'view', 'expenses', 'bestellungen'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$u = ['id' => 1, 'role' => 'leitung', 'display_name' => 'Leitung', 'username' => 'leitung'];
$GLOBALS['wuensche'] = [
    1 => ['id' => 1, 'bezeichnung' => 'Kettensäge', 'status_slug' => 'freigegeben', 'netto_gesamt' => 900, 'lieferant' => 'Baumarkt', 'fachgruppe_label' => 'Bergung', 'fahrzeug' => 'GKW 1', 'vehicle_id' => 2, 'budget_id' => 4],
    2 => ['id' => 2, 'bezeichnung' => 'Ersatzkette', 'status_slug' => 'freigegeben', 'netto_gesamt' => 60, 'lieferant' => '', 'fachgruppe_label' => 'Bergung', 'fahrzeug' => '', 'vehicle_id' => null, 'budget_id' => null],
    3 => ['id' => 3, 'bezeichnung' => 'Noch nicht frei', 'status_slug' => 'eingeplant', 'netto_gesamt' => 10, 'lieferant' => '', 'fachgruppe_label' => '', 'fahrzeug' => '', 'vehicle_id' => null, 'budget_id' => null],
];

/* ---------- Nummer und Prüfung ---------- */
$GLOBALS['anzahl'] = 2;
$check('laufende Nummer je Jahr', bestellung_nummer_neu(2026) === 'B-2026-003');
[$gut, $schlecht] = bestellung_wuensche_pruefen(array_values($GLOBALS['wuensche']));
$check('nur freigegebene Wünsche', count($gut) === 2 && $schlecht === ['Noch nicht frei']);
$check('Status-Kennzeichen', str_contains(bestellung_badge('geliefert'), 'geliefert') && bestellung_status('x') === 'bestellt');

/* ---------- Anlegen ---------- */
$GLOBALS['bestellt'] = [];
$_POST = ['lieferant' => 'Baumarkt Müller', 'bestellt_am' => '2026-10-04', 'bestell_nr' => 'A-77', 'notiz' => ''];
[$id, $fehler] = bestellung_save_from_post(null, $u, ['1', '2', '2']);
$check('Bestellung angelegt mit Nummer und zwei Wünschen', $fehler === [] && $id === 1 && $GLOBALS['inserts'][0][0] === 'bestellungen' && $GLOBALS['inserts'][0][1]['nummer'] === 'B-2026-003'
    && $GLOBALS['inserts'][0][1]['status'] === 'bestellt' && count(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'bestellung_wuensche')) === 2 && $GLOBALS['bestellt'] === [1, 2]);
[$id, $fehler] = bestellung_save_from_post(null, $u, ['1', '3']);
$check('nicht freigegebener Wunsch wird abgelehnt', $id === null && str_contains($fehler[0], 'Noch nicht frei'));
[$id, $fehler] = bestellung_save_from_post(null, $u, []);
$check('ohne Wünsche Fehler', $id === null && str_contains($fehler[0], 'mindestens'));
$_POST['lieferant'] = '';
[$id, $fehler] = bestellung_save_from_post(null, $u, ['1']);
$check('ohne Lieferant Fehler', $id === null && str_contains($fehler[0], 'Lieferant'));
$_POST = ['lieferant' => 'Neu', 'bestellt_am' => '2026-10-05', 'bestell_nr' => '', 'notiz' => 'x'];
[$id, $fehler] = bestellung_save_from_post(['id' => 9, 'nummer' => 'B-2026-001'], $u, ['3']);
$check('Ändern berührt die Wünsche nicht', $fehler === [] && $id === 9 && end($GLOBALS['updates'])[0] === 'bestellungen' && end($GLOBALS['updates'])[1]['lieferant'] === 'Neu');

/* ---------- Status zieht die Wünsche mit ---------- */
$GLOBALS['tabellen']['FROM bestellung_wuensche bw'] = [['id' => 1, 'bezeichnung' => 'Kettensäge'], ['id' => 2, 'bezeichnung' => 'Ersatzkette']];
$b = ['id' => 5, 'nummer' => 'B-2026-002', 'status' => 'bestellt'];
$GLOBALS['updates'] = [];
$check('geliefert: Wünsche beschafft', bestellung_status_setzen($b, 'geliefert', $u) === null && $GLOBALS['updates'][0][1]['status'] === 'geliefert'
    && count(array_filter($GLOBALS['updates'], static fn($x) => $x[0] === 'wishes' && $x[1]['status_id'] === 70)) === 2);
$GLOBALS['updates'] = [];
$check('storniert: Wünsche wieder freigegeben', bestellung_status_setzen($b, 'storniert', $u) === null && count(array_filter($GLOBALS['updates'], static fn($x) => $x[0] === 'wishes' && $x[1]['status_id'] === 55)) === 2);
$GLOBALS['updates'] = [];
$check('gleicher Stand: nichts', bestellung_status_setzen($b, 'bestellt', $u) === null && $GLOBALS['updates'] === []);
$check('abgerechnet aus bestellt: auch beschafft', bestellung_status_setzen($b, 'abgerechnet', $u) === null && count(array_filter($GLOBALS['updates'], static fn($x) => $x[0] === 'wishes')) === 2);
$GLOBALS['updates'] = [];
$check('abgerechnet aus geliefert: Wünsche bleiben', bestellung_status_setzen(['status' => 'geliefert'] + $b, 'abgerechnet', $u) === null && count(array_filter($GLOBALS['updates'], static fn($x) => $x[0] === 'wishes')) === 0);
$GLOBALS['execs'] = [];
bestellung_delete(['id' => 5, 'nummer' => 'B-2026-002', 'status' => 'geliefert'], $u);
$check('Löschen räumt Verknüpfungen und Bezug der Buchungen', count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM bestellung_wuensche'))) === 1
    && count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'SET bestellung_id = NULL'))) === 1);

/* ---------- Buchungen mit mehreren Bezügen ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
expense_links_speichern(12, ['3', '1', '1', '0'], ['2']);
$check('Verknüpfungen neu gesetzt, wish_id = erster Wunsch', count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM expense_links'))) === 1
    && count($GLOBALS['inserts']) === 3 && $GLOBALS['inserts'][0][1] === ['expense_id' => 12, 'typ' => 'wish', 'ziel_id' => 3] && $GLOBALS['inserts'][2][1]['typ'] === 'vehicle'
    && end($GLOBALS['updates'])[1] === ['wish_id' => 3]);
$GLOBALS['tabellen']['FROM expense_links'] = [['typ' => 'wish', 'ziel_id' => 3], ['typ' => 'wish', 'ziel_id' => 1], ['typ' => 'vehicle', 'ziel_id' => 2]];
$check('Verknüpfungen lesen', expense_links(12) === ['wish' => [3, 1], 'vehicle' => [2]]);
$check('Summe nur gebuchte Ausgaben', expenses_summe([['art' => 'ausgabe', 'status' => 'bezahlt', 'betrag_brutto' => 100], ['art' => 'ausgabe', 'status' => 'geplant', 'betrag_brutto' => 50],
    ['art' => 'einnahme', 'status' => 'bezahlt', 'betrag_brutto' => 70], ['art' => 'ausgabe', 'status' => 'offen', 'betrag_brutto' => 20.5]]) === 120.5);

// Speichern über das Buchungsformular: wishes[] und vehicles[] werden mitgeschrieben
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Rechnung Baumarkt', 'datum' => '2026-10-06', 'betrag' => '960', 'status' => 'offen', 'bestellung_id' => '5', 'wishes' => ['1', '2'], 'vehicles' => ['2'], 'bezuege' => '1'];
[$eid, $fehler] = expense_save_from_post(null, $u);
$d = $GLOBALS['inserts'][0][1];
$check('Buchung mit Bestellung, erstem Wunsch und Verknüpfungen', $fehler === [] && $d['bestellung_id'] === 5 && $d['wish_id'] === 1
    && count(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'expense_links')) === 3);
$GLOBALS['inserts'] = [];
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Strom', 'datum' => '2026-10-06', 'betrag' => '80', 'status' => 'bezahlt'];
[$eid, $fehler] = expense_save_from_post(null, $u);
$check('ohne Bezugsfelder keine Verknüpfungen angefasst', $fehler === [] && count($GLOBALS['inserts']) === 1 && $GLOBALS['inserts'][0][1]['wish_id'] === null);

/* ---------- Ansichten ---------- */
$bs = ['id' => 5, 'nummer' => 'B-2026-002', 'lieferant' => 'Baumarkt Müller', 'bestellt_am' => '2026-10-04', 'bestell_nr' => 'A-77', 'status' => 'bestellt', 'notiz' => '', 'erfasser' => 'Leitung', 'wuensche' => 2, 'summe' => 960, 'gebucht' => 0, 'buchungen' => 0];
$ws = [['id' => 1, 'bezeichnung' => 'Kettensäge', 'netto_gesamt' => 900, 'status_label' => 'Bestellt', 'status_color' => '#0891b2', 'fachgruppe_label' => 'Bergung', 'fahrzeug' => 'GKW 1'],
       ['id' => 2, 'bezeichnung' => 'Ersatzkette', 'netto_gesamt' => 60, 'status_label' => 'Bestellt', 'status_color' => '#0891b2', 'fachgruppe_label' => 'Bergung', 'fahrzeug' => '']];
$html = render_partial('bestellung', ['b' => $bs, 'wuensche' => $ws, 'buchungen' => [], 'gebucht' => 0.0, 'darf' => true, 'buchen' => true]);
$check('Bestellung rendert mit Wünschen, Summe, Rechnung erfassen und Stand', str_contains($html, 'B-2026-002') && str_contains($html, 'Kettensäge') && str_contains($html, '960,00')
    && str_contains($html, 'bestellung_id=5') && str_contains($html, 'value="geliefert"') && str_contains($html, 'noch keine Rechnung'));
$html = render_partial('bestellung', ['b' => $bs + ['gebucht' => 500], 'wuensche' => $ws, 'buchungen' => [['id' => 7, 'datum' => '2026-10-07', 'bezeichnung' => 'Teilrechnung', 'status' => 'bezahlt', 'beleg_nr' => 'R1', 'betrag_brutto' => 500, 'art' => 'ausgabe']],
    'gebucht' => 500.0, 'darf' => false, 'buchen' => false]);
$check('Bestellung ohne Rechte: keine Formulare, offener Rest', !str_contains($html, 'value="geliefert"') && str_contains($html, 'noch 460,00 € ohne Rechnung') && str_contains($html, 'Teilrechnung'));
$html = render_partial('bestellungen', ['liste' => [$bs], 'filters' => ['status' => '', 'q' => '', 'jahr' => null, 'offen' => 1], 'summe' => 960.0, 'zuBestellen' => []]);
$check('Liste rendert', str_contains($html, 'B-2026-002') && str_contains($html, 'Baumarkt Müller') && str_contains($html, '>bestellt<'));
$html = render_partial('bestellung_edit', ['b' => ['id' => null, 'lieferant' => 'Baumarkt', 'bestellt_am' => '2026-10-04', 'bestell_nr' => '', 'notiz' => ''], 'frei' => array_values(array_filter($GLOBALS['wuensche'], static fn($w) => $w['status_slug'] === 'freigegeben')),
    'gewaehlt' => [1], 'errors' => [], 'wuensche' => []]);
$check('Anlegen: freigegebene Wünsche zur Auswahl, gewählter angehakt', str_contains($html, 'value="1" id="w1" checked') && str_contains($html, 'value="2" id="w2"') && !str_contains($html, 'Noch nicht frei') && str_contains($html, 'Fahrzeug: GKW 1'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
