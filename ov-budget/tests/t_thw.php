<?php
declare(strict_types=1);
/*
 * THW-Kontakte: Dienststellen als Baum mit Suche, Speichern mit Regeln,
 * Funktionspostfächer mit Erreichbarkeit, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'thw_modul_name' => 'THW-Kontakte'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['kinder'] = 0;
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    return $GLOBALS['rows'];
}
function db_row(string $sql, array $p = []): ?array { foreach ($GLOBALS['rows'] as $r) { if ((int)$r['id'] === (int)($p[0] ?? 0)) { return $r; } } return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'COUNT(*) FROM thw_dienststellen WHERE parent_id') ? $GLOBALS['kinder'] : $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'standorte', 'thw', 'contacts'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$u = ['id' => 1];

/* ---------- Baum, Pfad, Suche ---------- */
$rows = [
    ['id' => 1, 'parent_id' => null, 'typ' => 'landesverband', 'name' => 'LV Baden-Württemberg', 'kurz' => 'LV BW', 'strasse' => '', 'plz' => '', 'ort' => 'Stuttgart', 'telefon' => '', 'email' => '', 'web' => '', 'notiz' => '', 'sort_order' => 10, 'is_active' => 1],
    ['id' => 2, 'parent_id' => 1, 'typ' => 'regionalstelle', 'name' => 'Regionalstelle Heilbronn', 'kurz' => 'RSt HN', 'strasse' => 'Musterstraße 1', 'plz' => '74072', 'ort' => 'Heilbronn', 'telefon' => '07131 1234', 'email' => 'rst@example.org', 'web' => 'thw.de', 'notiz' => '', 'sort_order' => 10, 'is_active' => 1],
    ['id' => 3, 'parent_id' => 2, 'typ' => 'ortsverband', 'name' => 'OV Heilbronn', 'kurz' => 'HN', 'strasse' => '', 'plz' => '', 'ort' => 'Heilbronn', 'telefon' => '', 'email' => '', 'web' => '', 'notiz' => 'wir', 'sort_order' => 10, 'is_active' => 1],
    ['id' => 4, 'parent_id' => 2, 'typ' => 'ortsverband', 'name' => 'OV Neckarsulm', 'kurz' => 'NSU', 'strasse' => '', 'plz' => '', 'ort' => 'Neckarsulm', 'telefon' => '', 'email' => '', 'web' => '', 'notiz' => '', 'sort_order' => 20, 'is_active' => 0],
    ['id' => 5, 'parent_id' => null, 'typ' => 'sonstige', 'name' => 'Logistikzentrum', 'kurz' => '', 'strasse' => '', 'plz' => '', 'ort' => '', 'telefon' => '', 'email' => '', 'web' => '', 'notiz' => '', 'sort_order' => 20, 'is_active' => 1],
];
$check('Pfad über die Platz-Hilfen', thw_pfad(3, $rows) === 'LV Baden-Württemberg › Regionalstelle Heilbronn › OV Heilbronn');
$check('Typ-Rückfall', thw_typ('ortsverband') === 'ortsverband' && thw_typ('quatsch') === 'ortsverband');
$check('Anschrift', thw_anschrift($rows[1]) === 'Musterstraße 1, 74072 Heilbronn' && thw_anschrift($rows[0]) === 'Stuttgart' && thw_anschrift(['strasse' => '', 'plz' => '', 'ort' => '']) === '');
$such = thw_suchen($rows, 'neckarsulm');
$check('Suche hält die Vorfahren', array_column($such, 'id') === [1, 2, 4]);
$check('Suche nach Kürzel und Ort', array_column(thw_suchen($rows, 'rst hn'), 'id') === [1, 2] && count(thw_suchen($rows, '')) === 5 && thw_suchen($rows, 'gibtsnicht') === []);

/* ---------- Speichern ---------- */
$GLOBALS['rows'] = $rows;
$_POST = ['name' => '  OV Öhringen ', 'kurz' => 'ÖHR', 'typ' => 'ortsverband', 'parent_id' => '2', 'strasse' => 'Hauptstraße 5', 'plz' => '74613', 'ort' => 'Öhringen', 'telefon' => '', 'email' => 'ov@example.org', 'web' => '', 'notiz' => '', 'sort_order' => '30', 'is_active' => '1'];
[$id, $fehler] = thw_save_from_post(null, $u);
$d = $GLOBALS['inserts'][0][1] ?? [];
$check('Dienststelle angelegt', $fehler === [] && $id === 101 && $d['name'] === 'OV Öhringen' && $d['parent_id'] === 2 && $d['typ'] === 'ortsverband' && $d['email'] === 'ov@example.org');
$_POST = ['name' => '', 'email' => 'kaputt', 'parent_id' => '99'];
[$id, $fehler] = thw_save_from_post(null, $u);
$check('ohne Namen, falsche E-Mail, unbekannter Elternteil: drei Fehler', $id === null && count($fehler) === 3);
$_POST = ['name' => 'Regionalstelle Heilbronn', 'typ' => 'regionalstelle', 'parent_id' => '3'];
[$id, $fehler] = thw_save_from_post($rows[1], $u);
$check('nicht unter sich selbst', $id === null && str_contains($fehler[0], 'unter sich selbst'));
$GLOBALS['kinder'] = 2;
$check('Löschen mit Unterstellen abgewiesen', thw_delete($rows[1]) !== null);
$GLOBALS['kinder'] = 0;
$GLOBALS['execs'] = [];
$check('Löschen ohne Unterstellen', thw_delete($rows[3]) === null && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM thw_dienststellen'));

/* ---------- Kontakte an Dienststellen, beide Richtungen ---------- */
$GLOBALS['inserts'] = [];
$_POST = ['name' => 'OV Neckarsulm', 'typ' => 'ortsverband', 'parent_id' => '2', 'is_active' => '1', 'kontakte_feld' => '1', 'kontakte' => ['30', '30'], 'kontakte_neu' => '31'];
[$id, $fehler] = thw_save_from_post($rows[3], $u);
$tk = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'thw_dienststelle_kontakte'));
$check('Dienststelle: Kontakte gesetzt, ohne Doppelte', $fehler === [] && count($tk) === 2 && $tk[0][1] === ['dienststelle_id' => 4, 'contact_id' => 30] && $tk[1][1]['contact_id'] === 31);
$GLOBALS['inserts'] = [];
$_POST = ['vorname' => 'Max', 'nachname' => 'Muster', 'organisation' => '', 'email' => '', 'is_active' => '1', 'dienststellen_feld' => '1', 'dienststellen' => ['2'], 'dienststellen_neu' => '4'];
[$cid, $fehler] = contact_save_from_post(null, $u);
$kd = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'thw_dienststelle_kontakte'));
$check('Kontakt: Dienststellen gesetzt', $fehler === [] && count($kd) === 2 && $kd[0][1] === ['dienststelle_id' => 2, 'contact_id' => $cid] && $kd[1][1]['dienststelle_id'] === 4);
$GLOBALS['inserts'] = [];
$_POST = ['vorname' => 'Ohne', 'nachname' => 'Feld', 'organisation' => '', 'email' => '', 'is_active' => '1'];
contact_save_from_post(null, $u);
$check('Kontakt ohne das Feld lässt die Zuordnung in Ruhe', !array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'thw_dienststelle_kontakte'));
$_POST = [];
$leuteJe = thw_kontakte_je_dienststelle([['dienststelle_id' => 2, 'id' => 30, 'vorname' => 'Max', 'nachname' => 'Muster', 'position' => 'Sachbearbeiter', 'organisation' => '', 'email' => '', 'telefon' => '', 'mobil' => '', 'is_active' => 1],
    ['dienststelle_id' => 4, 'id' => 31, 'vorname' => 'Erika', 'nachname' => 'Beispiel', 'position' => 'Ortsbeauftragte', 'organisation' => '', 'email' => 'e@example.org', 'telefon' => '', 'mobil' => '', 'is_active' => 1]]);
$check('Kontakte je Dienststelle', count($leuteJe[2]) === 1 && $leuteJe[4][0]['nachname'] === 'Beispiel');
$opt = thw_optionen($rows, [4]);
$check('Optionen: Name mit Kürzel, Pfad, Gruppe nach Art, stillgelegte nur wenn gewählt', $opt[1]['text'] === 'Regionalstelle Heilbronn (RSt HN)' && $opt[1]['sub'] === 'LV Baden-Württemberg' && $opt[1]['gruppe'] === 'Regionalstellen'
    && in_array(4, array_column($opt, 'id'), true) && !in_array(4, array_column(thw_optionen($rows), 'id'), true) && $opt[2]['sub'] === 'LV Baden-Württemberg › Regionalstelle Heilbronn');
$check('Kontaktliste liest die Dienststellen mit', str_contains((string)file_get_contents($app . '/src/lib/contacts.php'), 'AS dienststellen_namen'));

/* ---------- Postfächer ---------- */
$pf = [
    ['id' => 10, 'dienststelle_id' => 2, 'name' => 'Einsatz', 'email' => 'einsatz.rst@example.org', 'beschreibung' => 'Einsatzabrechnungen', 'offiziell' => 1, 'behoerdennetz' => 1, 'notiz' => '', 'sort_order' => 10, 'is_active' => 1, 'dienststelle_name' => 'Regionalstelle Heilbronn'],
    ['id' => 11, 'dienststelle_id' => 2, 'name' => 'Verwaltung', 'email' => 'verwaltung.rst@example.org', 'beschreibung' => 'Personal und Haushalt', 'offiziell' => 0, 'behoerdennetz' => 1, 'notiz' => '', 'sort_order' => 20, 'is_active' => 1, 'dienststelle_name' => 'Regionalstelle Heilbronn'],
    ['id' => 12, 'dienststelle_id' => null, 'name' => 'Ausbildung', 'email' => 'ausbildung@example.org', 'beschreibung' => '', 'offiziell' => 1, 'behoerdennetz' => 0, 'notiz' => '', 'sort_order' => 10, 'is_active' => 0, 'dienststelle_name' => null],
];
$check('Erreichbarkeit als Text', postfach_netz_text($pf[0]) === 'offizielles Netz · Behördennetz' && postfach_netz_text($pf[1]) === 'Behördennetz' && postfach_netz_text(['offiziell' => 0]) === 'Erreichbarkeit nicht angegeben');
$je = postfach_je_dienststelle($pf);
$check('Postfächer je Dienststelle, 0 = ohne', count($je[2]) === 2 && count($je[0]) === 1);
$check('Postfachsuche', array_column(postfach_suchen($pf, 'haushalt'), 'id') === [11] && array_column(postfach_suchen($pf, 'heilbronn'), 'id') === [10, 11] && count(postfach_suchen($pf, '')) === 3);
$GLOBALS['inserts'] = [];
$_POST = ['name' => 'Einsatz', 'email' => 'einsatz@example.org', 'dienststelle_id' => '2', 'beschreibung' => 'Alarmierung', 'offiziell' => '1', 'is_active' => '1'];
[$id, $fehler] = postfach_save_from_post(null, $u);
$p = $GLOBALS['inserts'][0][1] ?? [];
$check('Postfach angelegt, Behördennetz aus wenn nicht angehakt', $fehler === [] && $p['email'] === 'einsatz@example.org' && $p['offiziell'] === 1 && $p['behoerdennetz'] === 0 && $p['dienststelle_id'] === 2);
$_POST = ['name' => 'x', 'email' => 'keine-adresse', 'dienststelle_id' => '77'];
[$id, $fehler] = postfach_save_from_post(null, $u);
$check('Postfach: falsche Adresse und unbekannte Dienststelle', $id === null && count($fehler) === 2);
$_POST = [];

/* ---------- Ansichten ---------- */
$_SERVER['REQUEST_URI'] = '/?p=thw';
$html = render_partial('thw', ['q' => '', 'alle' => $rows, 'baum' => standort_baum($rows), 'zaehlung' => standort_zaehlung($rows), 'postfaecher' => $pf, 'jeDienststelle' => $je, 'darfPflegen' => true]);
$check('Übersicht: Baum mit Pfad, Anschrift, Links, Postfach-Chips, Knöpfe', str_contains($html, 'LV Baden-Württemberg') && str_contains($html, 'margin-left:2.8rem') && str_contains($html, 'Musterstraße 1, 74072 Heilbronn')
    && str_contains($html, 'tel:071311234') && str_contains($html, 'mailto:rst@example.org') && str_contains($html, 'https://thw.de') && str_contains($html, '✉ Einsatz') && str_contains($html, 'stillgelegt')
    && str_contains($html, 'p=thw_dienststelle_edit&amp;parent_id=2') && str_contains($html, 'p=thw_postfach_edit&amp;dienststelle_id=2') && str_contains($html, '2 Ortsverbände') && str_contains($html, '1 Landesverband'));
$htmlL = render_partial('thw', ['q' => '', 'alle' => $rows, 'baum' => standort_baum($rows), 'zaehlung' => standort_zaehlung($rows), 'postfaecher' => $pf, 'jeDienststelle' => $je2 = postfach_je_dienststelle($pf), 'darfPflegen' => true, 'kontakteJe' => $leuteJe]);
$check('Übersicht: Ansprechpartner als Chips an der Dienststelle', str_contains($htmlL, '👤 Erika Beispiel') && str_contains($htmlL, 'Ortsbeauftragte') && str_contains($htmlL, 'p=contact_edit&amp;id=31'));
$htmlF = render_partial('thw_dienststelle_edit', ['d' => $rows[3], 'errors' => [], 'alle' => $rows, 'ausser' => [4], 'kontaktOptionen' => [['id' => 30, 'text' => 'Max Muster', 'sub' => 'Sachbearbeiter', 'gruppe' => ''], ['id' => 31, 'text' => 'Erika Beispiel', 'sub' => '', 'gruppe' => '']], 'kontakteGewaehlt' => [31]]);
$check('Dienststellenformular: Kontakte wählbar', str_contains($htmlF, 'Ansprechpartner aus dem Kontaktmodul') && str_contains($htmlF, 'data-wahl="kontakte"') && str_contains($htmlF, 'name="kontakte[]" value="31"') && str_contains($htmlF, 'data-text="Max Muster"'));
$dstZeilen = $rows; $opt4 = thw_optionen($rows, [4]);
$GLOBALS['rows'] = [];
$htmlC = render_partial('contact_edit', ['contact' => ['id' => 30, 'anrede' => '', 'titel' => '', 'vorname' => 'Max', 'nachname' => 'Muster', 'organisation' => '', 'position' => '', 'kategorie_id' => null, 'email' => '', 'telefon' => '', 'mobil' => '',
    'strasse' => '', 'plz' => '', 'ort' => '', 'land' => '', 'anschreiben' => '', 'notiz' => '', 'is_active' => 1, 'extra' => null], 'errors' => [], 'verteiler' => [], 'mitglied' => [], 'dienststellenOptionen' => $opt4, 'dienststellenGewaehlt' => [4]]);
$rows = $dstZeilen; $GLOBALS['rows'] = $rows;
$check('Kontaktformular: Dienststellen wählbar', str_contains($htmlC, '>THW-Dienststellen</h3>') && str_contains($htmlC, 'data-wahl="dienststellen"') && str_contains($htmlC, 'name="dienststellen[]" value="4"') && str_contains($htmlC, '<optgroup label="Ortsverbände">'));
$check('Übersicht: Postfachtabelle mit Erreichbarkeit', str_contains($html, '>Behördennetz</span>') && str_contains($html, '>offizielles Netz</span>') && str_contains($html, 'Personal und Haushalt') && str_contains($html, 'value="postfach_aktiv"'));
$html = render_partial('thw', ['q' => '', 'alle' => $rows, 'baum' => standort_baum($rows), 'zaehlung' => standort_zaehlung($rows), 'postfaecher' => $pf, 'jeDienststelle' => $je, 'darfPflegen' => false]);
$check('ohne Pflegerecht keine Knöpfe', !str_contains($html, '+ Dienststelle') && !str_contains($html, 'value="postfach_aktiv"') && str_contains($html, 'mailto:einsatz.rst@example.org'));
$html = render_partial('thw', ['q' => 'xyz', 'alle' => $rows, 'baum' => [], 'zaehlung' => standort_zaehlung($rows), 'postfaecher' => [], 'jeDienststelle' => $je, 'darfPflegen' => true]);
$check('Suche ohne Treffer', str_contains($html, 'Keine Dienststelle passt zu „xyz"') && str_contains($html, 'Kein Postfach passt'));
$html = render_partial('thw_dienststelle_edit', ['d' => $rows[2], 'errors' => [], 'alle' => $rows, 'ausser' => [3]]);
$check('Dienststellenformular: Art, Elternteil ohne sich selbst, Löschen', str_contains($html, 'value="ortsverband" selected') && str_contains($html, 'value="2" selected') && !str_contains($html, 'value="3"') && str_contains($html, 'Dienststelle löschen'));
$html = render_partial('thw_postfach_edit', ['p' => $pf[1], 'errors' => [], 'alle' => $rows]);
$check('Postfachformular: Haken für Netze', str_contains($html, 'name="behoerdennetz" value="1" checked') && str_contains($html, 'name="offiziell" value="1">') && str_contains($html, 'value="2" selected') && str_contains($html, 'Postfach löschen'));
$check('Route, Menü, Wanderung', str_contains((string)file_get_contents($app . '/public/index.php'), "'thw_postfach_edit'") && str_contains((string)file_get_contents($app . '/src/lib/nav.php'), "'thw'")
    && str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), '067_thw_kontakte'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
