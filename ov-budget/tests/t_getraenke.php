<?php
declare(strict_types=1);
/*
 * Getränke: Gruppierung, Lager und Bewegungen, Leermeldungen, Bestellvorschlag,
 * Bestell-Mail, Bestellen/Liefern/Abrechnen, Formulare, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'getraenke_modul_name' => 'Getränke',
    'getraenke_lieferadresse' => "THW OV Musterstadt\nMusterstraße 1\n12345 Musterstadt", 'getraenke_bestell_hinweis' => 'Leergut steht bereit.'];
$GLOBALS['lieferanten'] = [
    ['id' => 1, 'name' => 'Getränke Müller', 'email' => 'bestellung@mueller.example', 'telefon' => '', 'kundennummer' => 'K-4711', 'notiz' => '', 'is_active' => 1, 'getraenke_anzahl' => 1],
    ['id' => 2, 'name' => 'Brauerei Schmidt', 'email' => '', 'telefon' => '0711 1', 'kundennummer' => '', 'notiz' => '', 'is_active' => 1, 'getraenke_anzahl' => 2],
];
$GLOBALS['getraenke'] = [
    ['id' => 1, 'name' => 'Wasser still', 'lieferant_id' => 1, 'gebinde' => 'Kasten 12 × 0,7 l', 'preis' => '6.50', 'mindestbestand' => 5, 'sort_order' => 1, 'notiz' => '', 'is_active' => 1, 'lieferant_name' => 'Getränke Müller'],
    ['id' => 2, 'name' => 'Apfelschorle', 'lieferant_id' => 2, 'gebinde' => 'Kasten', 'preis' => '9.00', 'mindestbestand' => 2, 'sort_order' => 2, 'notiz' => '', 'is_active' => 1, 'lieferant_name' => 'Brauerei Schmidt'],
    ['id' => 3, 'name' => 'Cola-Mix', 'lieferant_id' => 2, 'gebinde' => 'Kasten', 'preis' => '0', 'mindestbestand' => 0, 'sort_order' => 3, 'notiz' => '', 'is_active' => 1, 'lieferant_name' => 'Brauerei Schmidt'],
    ['id' => 4, 'name' => 'Alt', 'lieferant_id' => 2, 'gebinde' => 'Kasten', 'preis' => '0', 'mindestbestand' => 9, 'sort_order' => 4, 'notiz' => '', 'is_active' => 0, 'lieferant_name' => 'Brauerei Schmidt'],
];
$GLOBALS['standort'] = ['id' => 5, 'name' => 'Halle 1', 'getraenke_qr_token' => '', 'getraenke_qr_connector_id' => null, 'is_active' => 1];
$GLOBALS['positionen'] = [];
$GLOBALS['bestand'] = [];
$GLOBALS['bestand_wert'] = -1;
$GLOBALS['meldungen'] = [];
$GLOBALS['bestellung'] = null;
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = []; $GLOBALS['merker'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM getraenke_lieferanten')) { return $GLOBALS['lieferanten']; }
    if (str_contains($sql, 'FROM getraenke g')) { return str_contains($sql, 'is_active = 1') ? array_values(array_filter($GLOBALS['getraenke'], fn($g) => $g['is_active'] === 1)) : $GLOBALS['getraenke']; }
    if (str_contains($sql, 'FROM getraenke_positionen')) { return $GLOBALS['positionen']; }
    if (str_contains($sql, 'FROM getraenke_bestand')) { return $GLOBALS['bestand']; }
    if (str_contains($sql, 'FROM getraenke_meldungen')) { return $GLOBALS['meldungen']; }
    if (str_contains($sql, 'FROM standorte')) { return [$GLOBALS['standort']]; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM getraenke_lieferanten')) { foreach ($GLOBALS['lieferanten'] as $l) { if ($l['id'] === (int)($p[0] ?? 0)) { return $l; } } return null; }
    if (str_contains($sql, 'FROM getraenke g')) { foreach ($GLOBALS['getraenke'] as $g) { if ($g['id'] === (int)($p[0] ?? 0)) { return $g; } } return null; }
    if (str_contains($sql, 'FROM standorte')) { return (int)($p[0] ?? 0) === 5 ? $GLOBALS['standort'] : null; }
    if (str_contains($sql, 'FROM getraenke_bestellungen')) { return $GLOBALS['bestellung']; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM settings')) { return $GLOBALS['merker'][$p[0] ?? ''] ?? $d; }
    if (str_contains($sql, 'FROM getraenke_bestand')) { return $GLOBALS['bestand_wert']; }
    if (str_contains($sql, 'COUNT(*) FROM getraenke_bestellungen')) { return 6; }
    if (str_contains($sql, 'COUNT(*) FROM getraenke_positionen')) { return $GLOBALS['pos_count'] ?? 0; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; } $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Anna Admin', 'email' => 'anna@ov.example', 'phone' => '0171 1'] ; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'mail', 'expenses', 'standorte', 'connector', 'getraenke'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
$user = current_user();

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$getraenke = $GLOBALS['getraenke'];
$aktive = array_values(array_filter($getraenke, fn($g) => $g['is_active'] === 1));

/* ---------- Gruppierung ---------- */
$gruppen = getraenke_nach_lieferant($getraenke);
$check('nach Lieferant gruppiert', array_keys($gruppen) === [1, 2] && count($gruppen[2]) === 3);
$check('Getränketext', getraenk_text($getraenke[0]) === 'Wasser still (Kasten 12 × 0,7 l)' && getraenk_text(['name' => 'X', 'gebinde' => '']) === 'X');

/* ---------- Lager ---------- */
$bestand = [
    ['getraenk_id' => 1, 'standort_id' => 5, 'anzahl' => 1, 'standort_name' => 'Halle 1'],
    ['getraenk_id' => 2, 'standort_id' => 5, 'anzahl' => 3, 'standort_name' => 'Halle 1'],
    ['getraenk_id' => 2, 'standort_id' => 6, 'anzahl' => 1, 'standort_name' => 'Kühlschrank'],
];
$summe = getraenke_bestand_summe($bestand);
$check('Summe je Getränk', $summe === [1 => 1, 2 => 4]);
$m = getraenke_bestand_matrix($aktive, $bestand);
$check('Matrix: Plätze und Zeilen', $m['plaetze'] === [5 => 'Halle 1', 6 => 'Kühlschrank'] && $m['zeilen'][2] === ['je' => [5 => 3, 6 => 1], 'summe' => 4]
    && $m['zeilen'][3] === ['je' => [], 'summe' => 0]);

$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$GLOBALS['bestand_wert'] = -1;
$check('Zugang auf leeren Platz legt die Zeile an', getraenke_buchen(1, 5, 'zugang', 4, $user, 'Lieferung') === 4
    && $GLOBALS['inserts'][0] === ['getraenke_bestand', ['getraenk_id' => 1, 'standort_id' => 5, 'anzahl' => 4]]
    && $GLOBALS['inserts'][1][0] === 'getraenke_bewegungen' && $GLOBALS['inserts'][1][1]['bestand_neu'] === 4 && $GLOBALS['inserts'][1][1]['created_by'] === 1);
$GLOBALS['bestand_wert'] = 3;
$check('Abgang nie unter null, Inventur setzt', getraenke_buchen(1, 5, 'abgang', 5, $user) === 0 && getraenke_buchen(1, 5, 'inventur', 7, null, '', null, 'Ben') === 7
    && $GLOBALS['updates'][0] === ['getraenke_bestand', ['anzahl' => 0], [1, 5]] && end($GLOBALS['inserts'])[1]['melder'] === 'Ben');
try { getraenke_buchen(1, 5, 'klau', 1, $user); $check('unbekannte Bewegung wirft', false); }
catch (InvalidArgumentException $e) { $check('unbekannte Bewegung wirft', true); }

/* ---------- Leermeldung ---------- */
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$GLOBALS['bestand_wert'] = 2;
$n = getraenke_meldung_anwenden($GLOBALS['standort'], ['meldungen' => [
    ['id' => 1, 'stand' => 'leer'], ['id' => 2, 'stand' => 'knapp', 'anzahl' => '1'], ['id' => 3, 'stand' => 'ok'],
    ['id' => 4, 'stand' => 'leer'], ['id' => 99, 'stand' => 'leer'], ['id' => 1, 'stand' => 'unsinn'], 'kaputt',
], 'melder' => ' Anna ', 'zeit' => time()], time());
$check('drei gültige Meldungen (inaktiv und unbekannt nicht)', $n === 3);
$bew = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'getraenke_bewegungen'));
$mel = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'getraenke_meldungen'));
$check('leer = Inventur auf null, knapp mit Zahl = Inventur, Meldungen angelegt', count($bew) === 2 && $bew[0][1]['art'] === 'inventur' && $bew[0][1]['anzahl'] === 0
    && $bew[1][1]['anzahl'] === 1 && count($mel) === 2 && $mel[0][1]['stand'] === 'leer' && $mel[1][1]['anzahl'] === 1 && $mel[0][1]['melder'] === 'Anna');
$check('ok erledigt offene Meldungen, neue Meldung ersetzt die alte', $GLOBALS['updates'][count($GLOBALS['updates']) - 1][1] === ['erledigt_am' => $GLOBALS['updates'][count($GLOBALS['updates']) - 1][1]['erledigt_am']]
    && count(array_filter($GLOBALS['execs'], fn($e) => str_contains($e[0], 'DELETE FROM getraenke_meldungen'))) === 2);
$check('Marken', str_contains(getraenke_stand_badge('leer'), 'leer') && str_contains(getraenke_status_badge('bestellt'), 'bestellt') && str_contains(getraenke_status_badge('x'), '>x<'));

/* ---------- Bestellvorschlag ---------- */
$meldungen = [['getraenk_id' => 2, 'stand' => 'knapp'], ['getraenk_id' => 3, 'stand' => 'leer']];
$check('Vorschlag Müller: bis zum Mindestbestand', getraenke_bestellvorschlag(1, $aktive, $summe, $meldungen) === [1 => 4]);
$check('Vorschlag Schmidt: knapp = 1, leer ohne Mindest = 1, inaktives Getränk nicht', getraenke_bestellvorschlag(2, $getraenke, $summe, $meldungen) === [2 => 1, 3 => 1]);
$check('Vorschlag ohne Bedarf leer', getraenke_bestellvorschlag(1, $aktive, [1 => 9], []) === []);
$check('Summe und Nummer', getraenke_bestellung_summe([['anzahl' => 4, 'preis' => '6.50'], ['anzahl' => 1, 'preis' => 0]]) === 26.0
    && getraenke_bestellnummer((int)strtotime('2026-10-26')) === 'G-2026-007');

/* ---------- Mailtext ---------- */
$b = ['id' => 9, 'nummer' => 'G-2026-007', 'lieferant_id' => 1, 'standort_id' => 5, 'status' => 'entwurf', 'notiz' => 'Bitte Donnerstag.', 'lieferant_name' => 'Getränke Müller', 'created_at' => '2026-10-26 09:00:00'];
$positionen = [['id' => 1, 'bestellung_id' => 9, 'getraenk_id' => 1, 'anzahl' => 4, 'geliefert' => null, 'preis' => '6.50', 'getraenk_name' => 'Wasser still', 'gebinde' => 'Kasten 12 × 0,7 l']];
$text = getraenke_bestellung_text($b, $positionen, $GLOBALS['lieferanten'][0], $GLOBALS['standort'], [
    'ov_name' => 'THW OV Musterstadt', 'besteller' => 'Anna Admin', 'besteller_email' => 'anna@ov.example', 'besteller_telefon' => '0171 1',
    'lieferadresse' => "THW OV Musterstadt\nMusterstraße 1", 'hinweis' => 'Leergut steht bereit.']);
$check('Mailtext vollständig', str_contains($text, 'Bestellnummer G-2026-007, Kundennummer K-4711') && str_contains($text, '     4 × Wasser still (Kasten 12 × 0,7 l)')
    && str_contains($text, "Lieferung an:\n  THW OV Musterstadt\n  Musterstraße 1\n  Abladen: Halle 1") && str_contains($text, 'Bitte Donnerstag.')
    && str_contains($text, 'Leergut steht bereit.') && str_contains($text, "Mit freundlichen Grüßen\nAnna Admin\nTHW OV Musterstadt\nanna@ov.example · 0171 1"));

/* ---------- Formulare ---------- */
$_POST = ['name' => '', 'email' => 'kaputt'];
[$id, $f] = getraenke_lieferant_save_from_post(null);
$check('Lieferant: Name und E-Mail bemängelt', $id === null && count($f) === 2);
$GLOBALS['inserts'] = [];
$_POST = ['name' => ' Getränke Neu ', 'email' => 'neu@example.de', 'telefon' => '', 'kundennummer' => '', 'strasse' => ' Industriestraße 12 ', 'plz' => '74072', 'ort' => 'Heilbronn', 'notiz' => '', 'is_active' => '1'];
[$id, $f] = getraenke_lieferant_save_from_post(null);
$check('Lieferant angelegt mit Anschrift', $id === 101 && $f === [] && $GLOBALS['inserts'][0][1]['name'] === 'Getränke Neu'
    && $GLOBALS['inserts'][0][1]['strasse'] === 'Industriestraße 12' && $GLOBALS['inserts'][0][1]['plz'] === '74072' && $GLOBALS['inserts'][0][1]['ort'] === 'Heilbronn');
$check('Anschrift in einer Zeile', getraenke_lieferant_anschrift($GLOBALS['inserts'][0][1]) === 'Industriestraße 12, 74072 Heilbronn'
    && getraenke_lieferant_anschrift(['ort' => 'Heilbronn']) === 'Heilbronn' && getraenke_lieferant_anschrift([]) === '');
$_POST = ['name' => 'Spezi', 'lieferant_id' => '77'];
[$id, $f] = getraenk_save_from_post(null);
$check('Getränk: unbekannter Lieferant bemängelt', $id === null && count($f) === 1);
$GLOBALS['inserts'] = [];
$_POST = ['name' => 'Spezi', 'lieferant_id' => '2', 'gebinde' => '', 'preis' => '8,90', 'mindestbestand' => '3', 'sort_order' => '5', 'is_active' => '1'];
[$id, $f] = getraenk_save_from_post(null);
$check('Getränk angelegt mit Vorgabe Kasten und Dezimalpreis', $id === 101 && $GLOBALS['inserts'][0][1]['gebinde'] === 'Kasten' && $GLOBALS['inserts'][0][1]['preis'] === 8.9 && $GLOBALS['inserts'][0][1]['mindestbestand'] === 3);

$_POST = ['lieferant_id' => '0', 'pos' => []];
[$id, $f] = getraenke_bestellung_save_from_post(null, $user);
$check('Bestellung: Lieferant und Positionen bemängelt', $id === null && count($f) === 2);
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = [];
$_POST = ['lieferant_id' => '1', 'standort_id' => '5', 'notiz' => 'Bitte Donnerstag.', 'pos' => ['1' => '4', '2' => '0', '99' => '3']];
[$id, $f] = getraenke_bestellung_save_from_post(null, $user);
$check('Bestellung als Entwurf mit Nummer und Positionen zum Listenpreis', $id === 101 && $f === [] && $GLOBALS['inserts'][0][1]['nummer'] === 'G-' . date('Y') . '-007'
    && $GLOBALS['inserts'][0][1]['status'] === 'entwurf' && count($GLOBALS['inserts']) === 2
    && $GLOBALS['inserts'][1] === ['getraenke_positionen', ['bestellung_id' => 101, 'getraenk_id' => 1, 'anzahl' => 4, 'preis' => 6.5]]);
[$id, $f] = getraenke_bestellung_save_from_post(['id' => 9, 'status' => 'bestellt'], $user);
$check('Bestellte lässt sich nicht ändern', $id === null && str_contains($f[0], 'Entwurf'));
$_POST = [];

/* ---------- Bestellen, liefern, abrechnen ---------- */
$GLOBALS['positionen'] = $positionen;
$post = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name) use (&$post): void { $post[] = [$an, $betreff, $text, $name]; };
$r = getraenke_bestellung_senden(['status' => 'bestellt'] + $b, $user);
$check('schon bestellt: nicht nochmal', !$r['ok'] && str_contains($r['text'], 'schon bestellt'));
$r = getraenke_bestellung_senden(['lieferant_id' => 2] + $b, $user);
$check('ohne E-Mail-Adresse kein Versand', !$r['ok'] && str_contains($r['text'], 'E-Mail'));
$GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$r = getraenke_bestellung_senden($b, $user);
$check('verschickt: Mail an den Lieferanten mit Nummer im Betreff', $r['ok'] && count($post) === 1 && $post[0][0] === 'bestellung@mueller.example'
    && $post[0][1] === 'Bestellung G-2026-007 – THW OV Musterstadt' && $post[0][3] === 'Getränke Müller' && str_contains($post[0][2], '4 × Wasser still'));
$check('Status bestellt, Mailtext gemerkt, Meldungen erledigt', $GLOBALS['updates'][0][1]['status'] === 'bestellt' && $GLOBALS['updates'][0][1]['gesendet_an'] === 'bestellung@mueller.example'
    && str_contains($GLOBALS['updates'][0][1]['mail_text'], 'Kundennummer K-4711') && str_contains($GLOBALS['execs'][0][0], 'UPDATE getraenke_meldungen SET erledigt_am'));
$GLOBALS['mail_attrappe'] = static function (): void { throw new MailException('Server weg'); };
$r = getraenke_bestellung_senden($b, $user);
$check('Versandfehler als Text', !$r['ok'] && str_contains($r['text'], 'Server weg'));

$r = getraenke_bestellung_liefern(['status' => 'bestellt'] + $b, [1 => 3], 99, $user);
$check('Liefern ohne Platz', !$r['ok'] && str_contains($r['text'], 'Platz'));
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['bestand_wert'] = 1;
$r = getraenke_bestellung_liefern(['status' => 'bestellt'] + $b, [1 => 3], 5, $user);
$bew = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'getraenke_bewegungen'));
$check('Lieferung: Zugang gebucht, Position geliefert, Status', $r['ok'] && str_contains($r['text'], '3 Gebinde') && count($bew) === 1 && $bew[0][1]['art'] === 'zugang' && $bew[0][1]['anzahl'] === 3
    && $bew[0][1]['bestand_neu'] === 4 && $bew[0][1]['bestellung_id'] === 9 && $GLOBALS['updates'][0] === ['getraenke_positionen', ['geliefert' => 3], [1]]
    && end($GLOBALS['updates'])[1]['status'] === 'geliefert');

$r = getraenke_bestellung_abrechnen(['status' => 'geliefert'] + $b, 0.0, '2026-10-26', 3, 'R-1', null, $user);
$check('Abrechnen ohne Betrag', !$r['ok'] && str_contains($r['text'], 'Rechnungsbetrag'));
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$GLOBALS['positionen'][0]['geliefert'] = 3;
$r = getraenke_bestellung_abrechnen(['status' => 'geliefert'] + $b, 27.5, '2026-10-26', 3, 'R-2026-55', '', $user);
$e = $GLOBALS['inserts'][0];
$check('Ausgabe im Budget angelegt, gebucht', $r['ok'] && $r['expense_id'] === 101 && $e[0] === 'expenses' && $e[1]['art'] === 'ausgabe' && $e[1]['jahr'] === 2026
    && $e[1]['betrag_brutto'] === 27.5 && $e[1]['status'] === 'offen' && $e[1]['referenz'] === 'G-2026-007' && $e[1]['beleg_nr'] === 'R-2026-55'
    && $e[1]['kategorie_id'] === 3 && $e[1]['lieferant'] === 'Getränke Müller' && str_contains($e[1]['beschreibung'], '3 × Wasser still')
    && $GLOBALS['updates'][0][1] === ['status' => 'abgerechnet', 'expense_id' => 101, 'rechnungsbetrag' => 27.5]);
$GLOBALS['inserts'] = [];
$r = getraenke_bestellung_abrechnen(['status' => 'geliefert'] + $b, 27.5, '2026-10-26', 0, '', '2026-10-27', $user);
$check('mit Zahlungsdatum bezahlt, ohne Kategorie null', $r['ok'] && $GLOBALS['inserts'][0][1]['status'] === 'bezahlt' && $GLOBALS['inserts'][0][1]['bezahlt_am'] === '2026-10-27' && $GLOBALS['inserts'][0][1]['kategorie_id'] === null);
$r = getraenke_bestellung_abrechnen(['status' => 'abgerechnet', 'expense_id' => 7] + $b, 1.0, '2026-10-26', 0, '', null, $user);
$check('nicht doppelt abrechnen', !$r['ok'] && $r['expense_id'] === 7);

/* ---------- Ansichten ---------- */
$con = ['id' => 3, 'name' => 'Feedback', 'url' => 'https://ov.example.de/connector', 'kurz_url' => '', 'is_active' => 1, 'fuer_getraenke' => 1, 'pem' => 'x', 'pubkey' => 'y', 'server_pub' => 'z'];
$html = render_partial('getraenke', ['title' => 'Getränke', 'getraenke' => $getraenke, 'gruppen' => $gruppen, 'lieferanten' => [1 => $GLOBALS['lieferanten'][0], 2 => $GLOBALS['lieferanten'][1]],
    'matrix' => $m, 'summe' => $summe, 'meldungen' => [['id' => 1, 'gemeldet_am' => '2026-10-26 08:00:00', 'getraenk_name' => 'Cola-Mix', 'lieferant_id' => 2, 'standort_id' => 5, 'standort_name' => 'Halle 1', 'stand' => 'leer', 'anzahl' => null, 'melder' => 'Ben']],
    'bedarf' => [1 => [1 => 4], 2 => [2 => 1, 3 => 1]], 'offen' => [['id' => 9, 'nummer' => 'G-2026-007', 'lieferant_name' => 'Getränke Müller', 'gebinde_anzahl' => 4, 'summe' => 26, 'status' => 'bestellt', 'gesendet_am' => '2026-10-26 09:00:00', 'created_at' => '2026-10-26 08:00:00']],
    'erledigt' => [], 'lagerplaetze' => [$GLOBALS['standort']], 'plaetzeAlle' => [$GLOBALS['standort']], 'darf' => true, 'mailAktiv' => false, 'connectoren' => [$con]]);
$check('Übersicht: Bedarf je Lieferant, Meldung, Matrix rot, Bestellung, Lieferanten', str_contains($html, 'Bestellung vorbereiten') && str_contains($html, 'Wasser still: <strong>4 ×')
    && str_contains($html, 'Cola-Mix') && str_contains($html, 'style="color:var(--bad)"') && str_contains($html, 'G-2026-007') && str_contains($html, 'keine E-Mail – Bestellen nicht möglich')
    && str_contains($html, 'Meldungen abholen') && str_contains($html, 'Verwaltung → E-Mail'));
$html = render_partial('getraenke', ['title' => 'Getränke', 'getraenke' => [], 'gruppen' => [], 'lieferanten' => [], 'matrix' => ['plaetze' => [], 'zeilen' => []], 'summe' => [], 'meldungen' => [],
    'bedarf' => [], 'offen' => [], 'erledigt' => [], 'lagerplaetze' => [], 'plaetzeAlle' => [], 'darf' => false, 'mailAktiv' => true, 'connectoren' => []]);
$check('Übersicht leer ohne Rechte', str_contains($html, 'Nichts fehlt') && str_contains($html, 'Erst einen Lieferanten anlegen') && !str_contains($html, '+ Bestellung'));

$html = render_partial('getraenke_bestellung', ['b' => $b, 'positionen' => $positionen, 'lieferant' => $GLOBALS['lieferanten'][0], 'standort' => $GLOBALS['standort'], 'summe' => 26.0,
    'vorschau' => $text, 'plaetze' => [$GLOBALS['standort']], 'lagerplaetze' => [], 'kategorien' => '<option value="3">Getränke</option>', 'darf' => true, 'darfBudget' => true, 'mailAktiv' => true, 'expense' => null]);
$check('Bestellung Entwurf: Senden-Knopf, Vorschau, Lieferung, kein Abrechnen', str_contains($html, 'value="senden"') && str_contains($html, 'Geprüft – per E-Mail bestellen')
    && str_contains($html, 'Kundennummer K-4711') && str_contains($html, 'value="liefern"') && !str_contains($html, 'value="abrechnen"') && str_contains($html, 'value="bestellung_loeschen"'));
$html = render_partial('getraenke_bestellung', ['b' => ['status' => 'geliefert', 'geliefert_am' => '2026-10-27 10:00:00'] + $b, 'positionen' => $positionen, 'lieferant' => $GLOBALS['lieferanten'][0], 'standort' => null, 'summe' => 26.0,
    'vorschau' => 'Mail', 'plaetze' => [], 'lagerplaetze' => [], 'kategorien' => '', 'darf' => true, 'darfBudget' => true, 'mailAktiv' => true, 'expense' => null]);
$check('Bestellung geliefert: Abrechnen mit Summe vorbelegt', str_contains($html, 'value="abrechnen"') && str_contains($html, 'value="26,00"') && !str_contains($html, 'value="senden"'));
$html = render_partial('getraenke_bestellung', ['b' => ['status' => 'abgerechnet', 'rechnungsbetrag' => 27.5, 'expense_id' => 101] + $b, 'positionen' => $positionen, 'lieferant' => $GLOBALS['lieferanten'][0], 'standort' => null, 'summe' => 26.0,
    'vorschau' => 'Mail', 'plaetze' => [], 'lagerplaetze' => [], 'kategorien' => '', 'darf' => true, 'darfBudget' => true, 'mailAktiv' => true,
    'expense' => ['id' => 101, 'bezeichnung' => 'Getränke G-2026-007 – Getränke Müller', 'jahr' => 2026, 'status' => 'offen']]);
$check('Bestellung abgerechnet: Link zur Ausgabe', str_contains($html, '27,50') && str_contains($html, 'expense_edit') && str_contains($html, 'Getränke G-2026-007'));

$html = render_partial('getraenke_lager', ['standort' => ['getraenke_qr_token' => 'tok', 'getraenke_qr_connector_id' => 3] + $GLOBALS['standort'], 'plaetze' => [], 'lagerplaetze' => [], 'darf' => true,
    'getraenke' => $aktive, 'bestand' => [1 => 4], 'bewegungen' => [['created_at' => '2026-10-26 09:00:00', 'getraenk_name' => 'Wasser still', 'art' => 'zugang', 'anzahl' => 4, 'bestand_neu' => 4, 'benutzer' => 'Anna', 'melder' => '', 'notiz' => 'Lieferung']],
    'meldungen' => [], 'connector' => $con, 'connectoren' => [$con]]);
$check('Lager: Bestand, Buchen, QR mit Platzname im Anker, Bewegungen', str_contains($html, '<strong>4</strong>') && str_contains($html, 'value="buchen"') && str_contains($html, 'data-qr="https://ov.example.de/connector/index.php?p=getraenke&amp;t=tok#n=')
    && str_contains($html, 'data-qr-untertitel="Getränke melden"') && str_contains($html, 'Lieferung') && str_contains($html, 'value="qr_weg"'));
$html = render_partial('getraenke_lager', ['standort' => null, 'plaetze' => [$GLOBALS['standort']], 'lagerplaetze' => [], 'darf' => true]);
$check('Lager ohne Platz: Auswahl', str_contains($html, 'Platz wählen') && str_contains($html, 'Halle 1'));
$html = render_partial('getraenke_bestellung_edit', ['b' => null, 'errors' => [], 'lieferanten' => $GLOBALS['lieferanten'], 'lieferantId' => 2, 'standortId' => 0, 'notiz' => '', 'getraenke' => $aktive, 'pos' => [2 => 1, 3 => 1], 'summe' => $summe, 'plaetze' => [$GLOBALS['standort']]]);
$check('Bestellentwurf: nur Getränke des Lieferanten, Vorschlag eingetragen', str_contains($html, 'name="pos[2]"') && str_contains($html, 'name="pos[2]" min="0" max="9999" value="1"') && !str_contains($html, 'name="pos[1]"') && str_contains($html, 'value="2" selected'));
$html = render_partial('getraenk_edit', ['getraenk' => $getraenke[0], 'errors' => [], 'lieferanten' => $GLOBALS['lieferanten']]);
$check('Getränk bearbeiten', str_contains($html, 'value="Wasser still"') && str_contains($html, 'value="1" selected') && str_contains($html, 'value="loeschen"'));
$html = render_partial('getraenke_lieferant_edit', ['lieferant' => $GLOBALS['lieferanten'][0], 'errors' => ['Fehler X']]);
$check('Lieferant bearbeiten mit Anschriftsfeldern', str_contains($html, 'value="bestellung@mueller.example"') && str_contains($html, 'Fehler X')
    && str_contains($html, 'name="strasse"') && str_contains($html, 'name="plz"') && str_contains($html, 'name="ort"'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
