<?php
declare(strict_types=1);
/*
 * Einnahmen planen: Stufen abgerechnet → gestellt → zugesagt → eingegangen,
 * Stand aus Daten und Wahl, Betrag aus der letzten Stufe, Übersicht.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'budget_rundung' => '0'];
$GLOBALS['inserts'] = [];
$GLOBALS['gruppen'] = [];
$GLOBALS['kuerzung'] = null;
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'GROUP BY status')) { return $GLOBALS['gruppen']; }
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return str_contains($sql, 'abgerechnet_betrag IS NOT NULL') ? $GLOBALS['kuerzung'] : null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Stufen ---------- */
$check('Rangfolge', einnahme_rang('geplant') < einnahme_rang('abgerechnet') && einnahme_rang('abgerechnet') < einnahme_rang('gestellt')
    && einnahme_rang('gestellt') < einnahme_rang('zugesagt') && einnahme_rang('zugesagt') < einnahme_rang('bezahlt') && einnahme_rang('unsinn') === einnahme_rang('bezahlt'));
$check('ohne Daten gilt die Wahl', einnahme_stufe([], 'abgerechnet') === 'abgerechnet' && einnahme_stufe([], 'geplant') === 'geplant' && einnahme_stufe([]) === 'bezahlt');
$check('Datum hebt die Stufe', einnahme_stufe(['gestellt_am' => '2026-04-05'], 'geplant') === 'gestellt'
    && einnahme_stufe(['abgerechnet_am' => '2026-03-01', 'zugesagt_am' => '2026-05-01'], 'abgerechnet') === 'zugesagt');
$check('Wahl darf höher sein als das Datum', einnahme_stufe(['abgerechnet_am' => '2026-03-01'], 'zugesagt') === 'zugesagt');
$check('Eingangsdatum macht eingegangen', einnahme_stufe(['bezahlt_am' => '2026-06-01'], 'geplant') === 'bezahlt');
$check('unbekannte Wahl wird eingegangen', einnahme_stufe([], 'offen') === 'bezahlt');
$check('Status kennt alle Stufen', buchung_status('zugesagt') === 'zugesagt' && buchung_status('offen') === 'offen' && buchung_status('x') === 'bezahlt');
$check('Kennzeichen je Stufe', str_contains(buchung_status_badge('abgerechnet'), 'abgerechnet') && str_contains(buchung_status_badge('zugesagt'), 'zugesagt')
    && str_contains(buchung_status_badge('gestellt'), 'gestellt') && buchung_status_badge('bezahlt') === '');
$check('Ist-Regel', str_contains(BUCHUNG_IST, "'zugesagt'") && !str_contains(BUCHUNG_IST, "'gestellt'") && !str_contains(BUCHUNG_IST, "'geplant'"));

/* ---------- Weg als Zeile ---------- */
$e = ['abgerechnet_am' => '2026-03-12', 'abgerechnet_betrag' => 1250, 'gestellt_am' => '2026-04-05', 'gestellt_betrag' => 1100, 'gestellt_nr' => 'GB-4711',
      'zugesagt_am' => null, 'zugesagt_betrag' => null, 'bezahlt_am' => null];
$check('Weg nennt Daten, Nummer und Beträge', einnahme_weg($e) === 'Abgerechnet 12.03.2026 1.250,00 € · Rechnung / Bescheid 05.04.2026 Nr. GB-4711 1.100,00 €');
$check('Weg leer ohne Daten', einnahme_weg([]) === '' && einnahme_weg(['bezahlt_am' => '2026-06-01']) === 'eingegangen 01.06.2026');

/* ---------- Speichern ---------- */
$_POST = ['art' => 'einnahme', 'bezeichnung' => 'Einsatz Hochwasser', 'datum' => '2026-03-12', 'betrag' => '', 'status' => 'geplant',
          'abgerechnet_am' => '2026-03-12', 'abgerechnet_betrag' => '1.250,00', 'gestellt_am' => '', 'gestellt_betrag' => '', 'gestellt_nr' => '',
          'zugesagt_am' => '', 'zugesagt_betrag' => ''];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('Abrechnung: Betrag aus der Stufe, Stand abgerechnet', $fehler === [] && $d['status'] === 'abgerechnet' && $d['betrag_brutto'] === 1250.0
    && $d['abgerechnet_betrag'] === 1250.0 && $d['abgerechnet_am'] === '2026-03-12' && $d['gestellt_betrag'] === null && $d['gestellt_nr'] === '');
$_POST['gestellt_am'] = '2026-04-05'; $_POST['gestellt_betrag'] = '1100'; $_POST['gestellt_nr'] = 'GB-4711';
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('Bescheid: Betrag der letzten Stufe, Stand gestellt', $d['status'] === 'gestellt' && $d['betrag_brutto'] === 1100.0 && $d['gestellt_nr'] === 'GB-4711');
$_POST['betrag'] = '1000'; $_POST['status'] = 'zugesagt';
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('eigener Betrag und gewählte Stufe gelten', $d['status'] === 'zugesagt' && $d['betrag_brutto'] === 1000.0);
$_POST['bezahlt_am'] = '2026-06-01'; $_POST['status'] = 'abgerechnet';
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$check('Eingangsdatum schlägt die Wahl', end($GLOBALS['inserts'])[1]['status'] === 'bezahlt');
$_POST = ['art' => 'einnahme', 'bezeichnung' => 'Spende', 'datum' => '2026-03-12', 'betrag' => '', 'status' => 'bezahlt'];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$check('ohne jeden Betrag Fehler', $id === null && str_contains($fehler[0], 'Betrag'));
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Strom', 'datum' => '2026-03-12', 'betrag' => '80', 'status' => 'zugesagt', 'abgerechnet_am' => '2026-01-01'];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = end($GLOBALS['inserts'])[1];
$check('Ausgaben kennen keine Stufen', $d['status'] === 'bezahlt' && !array_key_exists('abgerechnet_am', $d));

/* ---------- Stand der Einnahmen ---------- */
$GLOBALS['gruppen'] = [['status' => 'abgerechnet', 'anzahl' => 2, 'summe' => 2500], ['status' => 'gestellt', 'anzahl' => 1, 'summe' => 1100],
                       ['status' => 'zugesagt', 'anzahl' => 1, 'summe' => 1000], ['status' => 'bezahlt', 'anzahl' => 3, 'summe' => 4000], ['status' => 'geplant', 'anzahl' => 1, 'summe' => 300]];
$GLOBALS['kuerzung'] = ['anzahl' => 2, 'abgerechnet' => 2350, 'gestellt' => 2100];
$st = einnahmen_stand(2026);
$check('Summen je Stufe', $st['stufen']['abgerechnet']['anzahl'] === 2 && $st['stufen']['abgerechnet']['summe'] === 2500.0 && $st['stufen']['bezahlt']['summe'] === 4000.0);
$check('Forderungen, zugesagt, eingegangen, erwartet', $st['forderungen'] === 3600.0 && $st['zugesagt'] === 1000.0 && $st['eingegangen'] === 4000.0 && $st['erwartet'] === 300.0);
$check('Kürzung Abrechnung gegen Bescheid', $st['kuerzung'] === 250.0 && $st['kuerzung_anzahl'] === 2);

/* ---------- Ampel ---------- */
$heute = strtotime('2026-10-03 12:00:00');
$alt = static fn(string $seit, string $status = 'abgerechnet') => einnahme_alter(['art' => 'einnahme', 'status' => $status, 'abgerechnet_am' => $seit, 'datum' => '2026-01-01'], $heute);
$check('unter 30 Tagen grün', $alt('2026-09-10')['stufe'] === 'gruen' && $alt('2026-09-10')['tage'] === 23);
$check('30 Tage gelb, 60 orange, 90 rot', $alt('2026-09-03')['stufe'] === 'gelb' && $alt('2026-08-04')['stufe'] === 'orange' && $alt('2026-07-05')['stufe'] === 'rot');
$check('59 Tage noch gelb', $alt('2026-08-05')['stufe'] === 'gelb');
$check('zugesagt wartet auch, eingegangen und erwartet nicht', $alt('2026-07-01', 'zugesagt')['stufe'] === 'rot' && $alt('2026-07-01', 'bezahlt') === null && $alt('2026-07-01', 'geplant') === null);
$check('ohne Abrechnungsdatum zählt der Bescheid, sonst die Buchung',
    einnahme_alter(['art' => 'einnahme', 'status' => 'gestellt', 'abgerechnet_am' => null, 'gestellt_am' => '2026-08-01', 'datum' => '2026-01-01'], $heute)['seit'] === '2026-08-01'
    && einnahme_alter(['art' => 'einnahme', 'status' => 'abgerechnet', 'abgerechnet_am' => null, 'gestellt_am' => null, 'datum' => '2026-09-20'], $heute)['tage'] === 13);
$check('Ausgaben haben keine Ampel', einnahme_alter(['art' => 'ausgabe', 'status' => 'offen', 'datum' => '2026-01-01'], $heute) === null);
$check('Kennzeichen nur ab gelb', einnahme_alter_badge($alt('2026-09-10')) === '' && str_contains(einnahme_alter_badge($alt('2026-07-05')), '#b91c1c')
    && str_contains(einnahme_alter_badge($alt('2026-08-04')), 'wartet seit 60 Tagen'));

$GLOBALS['wartende'] = [
    ['id' => 1, 'art' => 'einnahme', 'status' => 'abgerechnet', 'bezeichnung' => 'Einsatz A', 'betrag_brutto' => 1000, 'abgerechnet_am' => '2026-07-01', 'gestellt_am' => null, 'datum' => '2026-07-01'],
    ['id' => 2, 'art' => 'einnahme', 'status' => 'gestellt', 'bezeichnung' => 'Einsatz B', 'betrag_brutto' => 500, 'abgerechnet_am' => '2026-08-20', 'gestellt_am' => '2026-09-01', 'datum' => '2026-08-20'],
    ['id' => 3, 'art' => 'einnahme', 'status' => 'zugesagt', 'bezeichnung' => 'Einsatz C', 'betrag_brutto' => 200, 'abgerechnet_am' => '2026-09-25', 'gestellt_am' => null, 'datum' => '2026-09-25'],
];
$w = einnahmen_wartend_aus($GLOBALS['wartende'], $heute);
$check('Zählung je Stufe, älteste zuerst', $w['rot'] === 1 && $w['gelb'] === 1 && $w['orange'] === 0 && $w['ueber30'] === 2 && $w['summe_ueber30'] === 1500.0
    && $w['liste'][0]['id'] === 1 && $w['liste'][2]['id'] === 3);
$check('Text', einnahmen_wartend_text($w) === '1 über 90 Tage, 1 über 30 Tage');

$GLOBALS['queue'] = [];
$GLOBALS['state'] = [];
function notify_ereignis_aktiv(string $k): bool { return true; }
function notify_leitung(): array { return [1]; }
function notify_queue(array $ids, string $ereignis, string $titel, string $text, string $url = ''): int { $GLOBALS['queue'][] = compact('ereignis', 'titel', 'text', 'url'); return count($ids); }
$n = einnahmen_warnungen_taeglich($heute, $w['liste']);
$check('Meldung für gelb und rot, grün nicht', $n === 1 && count($GLOBALS['queue']) === 1 && str_contains($GLOBALS['queue'][0]['text'], 'Einsatz A')
    && str_contains($GLOBALS['queue'][0]['text'], 'Einsatz B') && !str_contains($GLOBALS['queue'][0]['text'], 'Einsatz C') && $GLOBALS['state']['einnahme_warn_1'] === 'rot');
$check('am nächsten Tag nichts Neues', einnahmen_warnungen_taeglich($heute + 86400, $w['liste']) === 0);
$w2 = einnahmen_wartend_aus($GLOBALS['wartende'], $heute + 30 * 86400);
$check('nächste Stufe meldet erneut', einnahmen_warnungen_taeglich($heute + 30 * 86400, $w2['liste']) === 1 && str_contains(end($GLOBALS['queue'])['text'], 'Einsatz B')
    && !str_contains(end($GLOBALS['queue'])['text'], 'Einsatz A'));

/* ---------- Ansichten ---------- */
$row = ['id' => 1, 'art' => 'einnahme', 'datum' => '2026-03-12', 'bezeichnung' => 'Einsatz Hochwasser', 'lieferant' => 'Regionalstelle', 'referenz' => 'E-2026-014', 'beleg_nr' => '',
        'kategorie_label' => 'Einsatz', 'kategorie_color' => '#ccc', 'fachgruppe_label' => '', 'budget_name' => '', 'wunsch_bezeichnung' => '', 'veranstaltung_titel' => '',
        'betrag_brutto' => 1100, 'betrag_netto' => 1100, 'status' => 'gestellt'] + $e;
$html = render_partial('expenses', ['art' => 'einnahme', 'rows' => [$row], 'stats' => ['anzahl' => 1, 'summe' => 1100.0],
    'filters' => ['q' => '', 'kategorie_id' => 0, 'fachgruppe_id' => 0, 'budget_id' => 0, 'status' => '', 'von' => '', 'bis' => '', 'sort' => 'datum'],
    'jahr' => 2026, 'jahre' => [2026], 'jahresbudget' => 10000.0, 'jahresSumme' => 5000.0, 'gegenSumme' => 4000.0, 'budgets' => [], 'einnahmenStand' => $st, 'wartend' => $w]);
$check('Liste: Ampelkasten mit den ältesten', str_contains($html, '2 Abrechnungen warten länger als 30 Tage') && str_contains($html, 'Einsatz A') && str_contains($html, '1 über 90 Tage, 1 über 30 Tage'));
$check('Liste: Stand der Einnahmen mit Stufen und Kürzung', str_contains($html, 'Stand der Einnahmen 2026') && str_contains($html, 'status=abgerechnet')
    && str_contains($html, '250,00 € gekürzt') && str_contains($html, 'Haben sollten wir <strong>8.600,00 €'));
$check('Liste: Weg je Zeile und Filter nach Stufe', str_contains($html, 'Nr. GB-4711') && str_contains($html, '>gestellt<') && str_contains($html, '<option value="zugesagt"'));
$html = render_partial('expense_edit', ['art' => 'einnahme', 'expense' => ['id' => 3, 'art' => 'einnahme', 'jahr' => 2026, 'datum' => '2026-03-12', 'bezeichnung' => 'x', 'beschreibung' => '',
    'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null, 'wish_id' => null, 'event_id' => null, 'betrag_brutto' => 1100, 'betrag_netto' => 1100,
    'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'notiz' => '', 'status' => 'gestellt'] + $e, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => []]);
$check('Formular: Weg der Einnahme mit Stufen', str_contains($html, 'Weg der Einnahme') && str_contains($html, 'name="gestellt_nr"') && str_contains($html, 'value="GB-4711"')
    && str_contains($html, 'value="gestellt" checked') && !str_contains($html, 'name="status" value="offen"'));
$html = render_partial('expense_edit', ['art' => 'ausgabe', 'expense' => ['id' => null, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-03-12', 'bezeichnung' => '', 'beschreibung' => '',
    'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null, 'wish_id' => null, 'event_id' => null, 'betrag_brutto' => '', 'betrag_netto' => '',
    'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'notiz' => '', 'bezahlt_am' => null, 'status' => 'bezahlt'], 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => []]);
$check('Ausgaben-Formular unverändert', !str_contains($html, 'Weg der Einnahme') && str_contains($html, 'name="status" value="offen"') && str_contains($html, 'name="bezahlt_am"'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
