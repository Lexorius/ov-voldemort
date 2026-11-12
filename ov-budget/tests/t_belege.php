<?php
declare(strict_types=1);
/*
 * Belege zu Ausgaben und Einnahmen: beim Erfassen mitgeben, an der Buchung
 * sehen und entfernen, mit der Buchung löschen, KI-Auswertung, Liste.
 */
session_start();
date_default_timezone_set('Europe/Berlin');
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-belege-test-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10', 'budget_modul_name' => 'Budget', 'budget_rundung' => '0',
    'ki_aktiv' => '1', 'ki_ollama_url' => 'http://192.168.1.20:11434'];
$GLOBALS['rechte'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['abfragen'] = [];
$GLOBALS['belege'] = [];
$GLOBALS['row'] = null;
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (str_contains($sql, 'FROM expense_files')) { return $GLOBALS['belege']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return str_contains($sql, 'FROM expense_files f JOIN expenses x') ? $GLOBALS['row'] : null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 70 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
final class StubStmtB { public function execute(array $p): bool { return true; } }
final class StubPdoB { public function prepare(string $s): StubStmtB { return new StubStmtB(); } }
function db(): StubPdoB { return new StubPdoB(); }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'expenses', 'ki', 'expense_files'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$ich = ['id' => 1, 'role' => 'leitung'];
$pdfInhalt = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
$hochladen = static function (array $namen) use ($tmp, $pdfInhalt): void {
    $f = ['name' => [], 'tmp_name' => [], 'size' => [], 'error' => []];
    foreach ($namen as $i => $name) {
        $pfad = $tmp . '/quelle' . $i;
        file_put_contents($pfad, str_ends_with($name, '.html') ? '<html><script>alert(1)</script></html>' : $pdfInhalt);
        $f['name'][] = $name; $f['tmp_name'][] = $name === '' ? '' : $pfad; $f['size'][] = $name === '' ? 0 : filesize($pfad);
        $f['error'][] = $name === '' ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK;
    }
    $_FILES = ['belege' => $f];
};

/* ---------- Wurde etwas mitgeschickt? ---------- */
$_FILES = [];
$check('kein Feld, keine Datei', !upload_mitgeschickt('belege'));
$hochladen(['']);
$check('leeres Feld zählt nicht', !upload_mitgeschickt('belege'));
$hochladen(['', 'Rechnung.pdf']);
$check('eine gewählte Datei zählt', upload_mitgeschickt('belege'));

/* ---------- Anhängen ---------- */
$hochladen(['Rechnung Zelt 4711.pdf', 'trick.html', 'Quittung.pdf']);
[$anzahl, $fehler] = xfile_store_uploads(300, 'belege', '', $ich);
$erster = $GLOBALS['inserts'][0][1] ?? [];
$check('zwei Belege angehängt, HTML abgelehnt', $anzahl === 2 && count($fehler) === 1 && str_contains($fehler[0], 'trick.html'));
$check('Eintrag an der Buchung, Titel aus dem Dateinamen', ($GLOBALS['inserts'][0][0] ?? '') === 'expense_files' && $erster['expense_id'] === 300
    && $erster['titel'] === 'Rechnung Zelt 4711' && $erster['orig_name'] === 'Rechnung Zelt 4711.pdf' && $erster['art'] === 'dokument' && $erster['uploaded_by'] === 1);
$check('Datei liegt im Ordner budget unter zufälligem Namen', is_file($tmp . '/budget/' . $erster['stored_name']) && !str_contains($erster['stored_name'], 'Rechnung')
    && $GLOBALS['inserts'][0][1]['stored_name'] !== $GLOBALS['inserts'][1][1]['stored_name']);
$kiAuftraege = array_values(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'INSERT INTO ki_auftraege')));
$check('jeder Beleg geht in die KI-Auswertung', count($kiAuftraege) === 2 && $kiAuftraege[0][1] === ['buchung', 71] && $kiAuftraege[1][1] === ['buchung', 72]);

$GLOBALS['inserts'] = [];
$hochladen(['scan0001.pdf']);
[$anzahl] = xfile_store_uploads(300, 'belege', '  Rechnung Stadtwerke März ', $ich);
$check('eigene Bezeichnung statt Dateiname', $anzahl === 1 && $GLOBALS['inserts'][0][1]['titel'] === 'Rechnung Stadtwerke März');
$_FILES = [];
$check('ohne Feld: Hinweis statt Absturz', xfile_store_uploads(300, 'belege', '', $ich)[0] === 0 && xfile_store_uploads(300, 'belege', '', $ich)[1] !== []);

$GLOBALS['settings']['ki_buchungen'] = '0';
settings_reset_cache();
$GLOBALS['execs'] = [];
$hochladen(['still.pdf']);
xfile_store_uploads(300, 'belege', '', $ich);
$check('Schalter aus: angehängt, aber nichts eingereiht', array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'ki_auftraege')) === []);
$GLOBALS['settings']['ki_buchungen'] = '1';
settings_reset_cache();

/* ---------- Lesen ---------- */
$GLOBALS['belege'] = [['id' => 71, 'expense_id' => 300, 'titel' => 'Rechnung Zelt', 'orig_name' => 'r.pdf', 'mime' => 'application/pdf'],
    ['id' => 72, 'expense_id' => 300, 'titel' => '', 'orig_name' => 'q.pdf', 'mime' => 'application/pdf'],
    ['id' => 80, 'expense_id' => 301, 'titel' => 'Bescheid', 'orig_name' => 'b.pdf', 'mime' => 'application/pdf']];
$GLOBALS['abfragen'] = [];
$je = xfile_fuer_buchungen([300, '301', 0, 300, 'x']);
$check('Belege vieler Buchungen in einer Abfrage', count($GLOBALS['abfragen']) === 1 && $GLOBALS['abfragen'][0][1] === [300, 301] && str_contains($GLOBALS['abfragen'][0][0], 'expense_id IN (?,?)')
    && array_keys($je) === [300, 301] && count($je[300]) === 2 && $je[301][0]['titel'] === 'Bescheid');
$GLOBALS['abfragen'] = [];
$check('ohne Buchungen keine Abfrage', xfile_fuer_buchungen([]) === [] && xfile_fuer_buchungen([0, '']) === [] && $GLOBALS['abfragen'] === []);
$check('Pfad zur Datei, nie aus dem Ordner hinaus', xfile_path($erster) === $tmp . '/budget/' . $erster['stored_name'] && xfile_path(['stored_name' => '../../etc/passwd']) === null
    && xfile_path($erster, true) === $tmp . '/budget/' . $erster['stored_name']);
$check('Wort der Buchung', xfile_buchung_wort('einnahme') === 'Einnahme' && xfile_buchung_wort('ausgabe') === 'Ausgabe' && xfile_buchung_wort('') === 'Ausgabe');

/* ---------- Entfernen ---------- */
$GLOBALS['execs'] = [];
xfile_delete($erster + ['id' => 71]);
$check('Beleg entfernt: Datei weg, Eintrag weg, KI vergisst', !is_file($tmp . '/budget/' . $erster['stored_name']) && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM expense_files') && $GLOBALS['execs'][0][1] === [71]
    && count(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'DELETE FROM datei_ki') && $e[1] === ['buchung', 71])) === 1);
$GLOBALS['belege'] = [['id' => 72, 'expense_id' => 300, 'titel' => '', 'orig_name' => 'q.pdf', 'stored_name' => 'gibtsnicht.pdf', 'thumb_name' => null],
    ['id' => 73, 'expense_id' => 300, 'titel' => 'x', 'orig_name' => 'x.pdf', 'stored_name' => 'auchnicht.pdf', 'thumb_name' => 'auchnicht_vorschau.jpg']];
$GLOBALS['execs'] = [];
$check('Buchung wird gelöscht: alle Belege gehen mit', xfile_delete_alle(300) === 2
    && count(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'DELETE FROM expense_files'))) === 2);

/* ---------- KI: Kontext und Rechte ---------- */
$GLOBALS['row'] = ['id' => 71, 'expense_id' => 300, 'titel' => 'Rechnung Zelt', 'orig_name' => 'r.pdf', 'mime' => 'application/pdf', 'stored_name' => 'fehlt.pdf', 'thumb_name' => null,
    'buchung_art' => 'ausgabe', 'buchung_jahr' => 2026, 'buchung_bezeichnung' => 'Zelt für das Sommerfest', 'buchung_datum' => '2026-06-02', 'buchung_lieferant' => 'Zeltverleih Huber'];
$l = ki_datei_laden('buchung', 71);
$check('Ausgabe: Kontext nennt Buchung, Datum und Lieferant', $l !== null && str_contains($l['kontext'], 'zur Ausgabe „Zelt für das Sommerfest" vom 02.06.2026, Lieferant Zeltverleih Huber')
    && str_contains($l['kontext'], 'Dateiname: r.pdf, Titel: Rechnung Zelt') && $l['pfad'] === null);
$check('wer pflegt, kommt zur Buchung', str_contains($l['eltern']['url'], 'p=expense_edit&id=300') && str_ends_with($l['eltern']['url'], '#belege') && $l['eltern']['text'] === 'Ausgabe · Zelt für das Sommerfest');
$GLOBALS['rechte'] = ['manage_budget' => false];
$l2 = ki_datei_laden('buchung', 71);
$check('wer nur liest, kommt in die Liste des Jahres', str_contains($l2['eltern']['url'], 'p=expenses&jahr=2026&art=ausgabe'));
$check('lesen ja, ändern nein', ki_darf_sehen('buchung', $l['datei'], $ich) && !ki_darf_bearbeiten('buchung', $l['datei'], $ich));
$GLOBALS['rechte'] = ['view_expenses' => false, 'manage_budget' => false];
$check('ohne Blick in die Buchungen kein Beleg', !ki_darf_sehen('buchung', $l['datei'], $ich));
$GLOBALS['rechte'] = [];
$GLOBALS['row'] = ['buchung_art' => 'einnahme', 'buchung_bezeichnung' => 'Einsatz Hochwasser', 'buchung_lieferant' => 'Regionalstelle', 'titel' => ''] + $GLOBALS['row'];
$l = ki_datei_laden('buchung', 71);
$check('Einnahme: eigener Wortlaut', str_contains($l['kontext'], 'zur Einnahme „Einsatz Hochwasser"') && str_contains($l['kontext'], 'Zahler Regionalstelle') && $l['eltern']['text'] === 'Einnahme · Einsatz Hochwasser');
$GLOBALS['row'] = null;
$check('gelöschter Beleg', ki_datei_laden('buchung', 5) === null);
$check('Ablage der KI', KI_TYPEN['buchung'] === ['schalter' => 'ki_buchungen', 'label' => 'Buchung (Beleg)', 'tabelle' => 'expense_files', 'route' => 'expense_file'] && ki_modul_aktiv('buchung'));

/* ---------- Formular ---------- */
$leer = ['id' => null, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-03-12', 'bezeichnung' => '', 'beschreibung' => '', 'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null,
    'wish_id' => null, 'event_id' => null, 'betrag_brutto' => '', 'betrag_netto' => '', 'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'notiz' => '', 'bezahlt_am' => null, 'status' => 'bezahlt',
    'ausgelegt_von' => null, 'ausgelegt_name' => '', 'weitergegeben_am' => null, 'erstattet_am' => null];
$form = static fn(array $mehr): string => render_partial('expense_edit', $mehr + ['art' => 'ausgabe', 'expense' => $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => [], 'helfer' => [],
    'stichtag' => ['stichtag' => null, 'tage' => null, 'gesperrt' => false]]);
$neu = $form(['belegMaxMb' => 10]);
$check('neue Ausgabe: Datei gleich mit erfassen', str_contains($neu, 'enctype="multipart/form-data"') && str_contains($neu, 'name="belege[]" multiple') && str_contains($neu, 'name="beleg_titel"')
    && str_contains($neu, 'Rechnung oder Quittung') && str_contains($neu, 'höchstens 10 MB') && !str_contains($neu, 'Angehängte Belege') && !str_contains($neu, 'required name="belege'));
$check('der Beleg ist freiwillig', !preg_match('/name="belege\[\]"[^>]*required/', $neu) && !preg_match('/required[^>]*name="belege\[\]"/', $neu));
$einnahme = $form(['art' => 'einnahme', 'expense' => ['art' => 'einnahme'] + $leer]);
$check('neue Einnahme: dasselbe Feld mit eigenem Wortlaut', str_contains($einnahme, 'name="belege[]" multiple') && str_contains($einnahme, 'Bescheid, Rechnung oder Zahlungsnachweis') && str_contains($einnahme, 'an der Einnahme'));

$belege = [['id' => 71, 'expense_id' => 300, 'titel' => 'Rechnung Zelt', 'orig_name' => 'r.pdf', 'mime' => 'application/pdf', 'size_bytes' => 20480, 'created_at' => '2026-06-03 09:15:00', 'thumb_name' => null, 'hochgeladen_von' => 'Anna'],
    ['id' => 72, 'expense_id' => 300, 'titel' => 'Quittung', 'orig_name' => 'foto.jpg', 'mime' => 'image/jpeg', 'size_bytes' => 204800, 'created_at' => '2026-06-04 10:00:00', 'thumb_name' => 'x_vorschau.jpg', 'hochgeladen_von' => null]];
$ki = ['ergebnisse' => [71 => ['zusammenfassung' => 'Rechnung über ein Festzelt.', 'tags' => ['rechnung', 'zelt'], 'modell' => 'qwen', 'geaendert_am' => '2026-06-03 09:20:00', 'quelle' => 'text']], 'auftraege' => []];
$alt = $form(['expense' => ['id' => 300, 'bezeichnung' => 'Zelt'] + $leer, 'belege' => $belege, 'belegKi' => $ki]);
$check('bestehende Buchung: Belege mit Verweis, Größe, Wer, Vorschau', str_contains($alt, 'id="belege"') && str_contains($alt, 'Angehängte Belege') && str_contains($alt, 'p=expense_file&amp;id=71')
    && str_contains($alt, 'r.pdf · 20 KB · 03.06.2026 09:15 · Anna') && str_contains($alt, 'p=expense_file&amp;id=72&amp;vorschau=1') && str_contains($alt, '2 Belege hängen schon an'));
$check('bestehende Buchung: Zusammenfassung und Schlagwörter der KI', str_contains($alt, 'Rechnung über ein Festzelt.') && str_contains($alt, '#zelt') && str_contains($alt, 'name="typ" value="buchung"'));
$check('bestehende Buchung: jeder Beleg einzeln zu entfernen, weiterer anzuhängen', substr_count($alt, 'value="beleg_weg"') === 2 && str_contains($alt, 'name="beleg_id" value="72"')
    && str_contains($alt, 'Beleg „Quittung“ entfernen?') && str_contains($alt, 'name="belege[]" multiple'));
$check('Löschen nennt die Belege', str_contains($alt, 'Diese Buchung samt ihren 2 Belegen endgültig löschen?'));
$einer = $form(['expense' => ['id' => 300] + $leer, 'belege' => [$belege[0]], 'belegKi' => $ki]);
$check('Einzahl', str_contains($einer, 'Ein Beleg hängt schon an') && str_contains($einer, 'samt ihrem Beleg endgültig löschen?'));
$check('ohne Belege bleibt die Löschfrage wie bisher', str_contains($form(['expense' => ['id' => 300] + $leer]), 'data-confirm="Diese Buchung endgültig löschen?"'));

/* ---------- Liste ---------- */
$row = ['id' => 300, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-06-02', 'bezeichnung' => 'Zelt für das Sommerfest', 'betrag_brutto' => 480.0, 'status' => 'bezahlt', 'lieferant' => 'Zeltverleih Huber',
    'kategorie_label' => 'Veranstaltung', 'kategorie_color' => '#b45309', 'fachgruppe_label' => '', 'budget_name' => '', 'wunsch_bezeichnung' => '', 'wuensche_namen' => '', 'fahrzeuge_namen' => '',
    'bestellung_nummer' => '', 'veranstaltung_titel' => '', 'beleg_nr' => 'RE-4711', 'referenz' => '', 'erfasser' => 'Tester', 'bezahlt_am' => '2026-06-05',
    'ausgelegt_von' => null, 'ausgelegt_name' => '', 'ausgelegt_display' => '', 'weitergegeben_am' => null, 'erstattet_am' => null, 'wiederkehrend' => ''];
$filters = ['q' => '', 'kategorie_id' => 0, 'fachgruppe_id' => 0, 'budget_id' => 0, 'status' => '', 'von' => '', 'bis' => '', 'sort' => 'datum', 'auslage' => ''];
$liste = static fn(array $rows, array $belegeJe): string => render_partial('expenses', ['art' => 'ausgabe', 'rows' => $rows, 'belege' => $belegeJe, 'stats' => ['anzahl' => count($rows), 'summe' => 480.0],
    'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026], 'jahresbudget' => 10000.0, 'jahresSumme' => 480.0, 'gegenSumme' => 0.0, 'budgets' => [], 'einnahmenStand' => null, 'wartend' => null, 'auslagenOffen' => []]);
$html = $liste([$row, ['id' => 301, 'beleg_nr' => '', 'bezeichnung' => 'Brötchen'] + $row], [300 => $belege]);
$check('Liste: Belege als Verweise neben der Belegnummer', str_contains($html, 'RE-4711') && substr_count($html, 'p=expense_file&amp;id=71') === 1 && str_contains($html, 'title="Rechnung Zelt"')
    && str_contains($html, 'p=expense_file&amp;id=72'));
$check('Liste: Zahl der Belege auf dem Handy', str_contains($html, '📎 2</span>') && substr_count($html, 'title="Belege"') === 1);
$check('Liste: ohne Beleg und ohne Nummer ein Strich', substr_count($html, '<span class="mono">–</span>') === 1);
$check('Liste rendert auch ohne die Belege', str_contains(render_partial('expenses', ['art' => 'ausgabe', 'rows' => [$row], 'stats' => ['anzahl' => 1, 'summe' => 480.0],
    'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026], 'jahresbudget' => 10000.0, 'jahresSumme' => 480.0, 'gegenSumme' => 0.0, 'budgets' => [], 'einnahmenStand' => null, 'wartend' => null, 'auslagenOffen' => []]), 'RE-4711'));

/* ---------- Einbindung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$seite = $lies('src/pages/expense_edit.php');
$check('Seite: Belege erst nach dem Speichern anhängen, an die neue Buchung', str_contains($seite, "xfile_store_uploads((int)\$newId, 'belege'")
    && strpos($seite, 'expense_save_from_post($expense, $user)') < strpos($seite, 'xfile_store_uploads('));
$check('Seite: Beleg entfernen nur an der eigenen Buchung', str_contains($seite, "(int)\$beleg['expense_id'] === (int)\$expense['id']"));
$check('Seite: beim Löschen erst die Dateien, dann die Buchung', strpos($seite, 'xfile_delete_alle(') < strpos($seite, "db_exec('DELETE FROM expenses"));
$check('Seite: bei Eingabefehlern Hinweis auf die verlorene Auswahl', str_contains($seite, 'noch einmal auswählen'));
$check('Seite bleibt der Leitung vorbehalten', str_contains($seite, "require_role('admin', 'leitung')"));
$abruf = $lies('src/pages/expense_file.php');
$check('Abruf prüft das Recht auf die Buchungen und liefert nichts Ausführbares aus', str_contains($abruf, "can('view_expenses')") && str_contains($abruf, 'X-Content-Type-Options: nosniff')
    && str_contains($abruf, "default-src \\'none\\'"));
$check('Liste holt die Belege nur für die gezeigte Seite', str_contains($lies('src/pages/expenses.php'), "xfile_fuer_buchungen(array_column(\$seite, 'id'))"));
$check('Seite und Bibliothek eingebunden', str_contains($lies('public/index.php'), "'expense_file'") && str_contains($lies('src/bootstrap.php'), "/lib/expense_files.php"));
$schema = $lies('sql/schema.sql');
$wanderung = $lies('src/cli/migrate.php');
preg_match('/CREATE TABLE IF NOT EXISTS expense_files \((.*?)\) ENGINE/s', $schema, $ms);
preg_match('/CREATE TABLE expense_files \((.*?)\) ENGINE/s', $wanderung, $mw);
$spalten = static fn(string $s): array => array_values(array_filter(array_map(static fn($z) => preg_match('/^\s+([a-z_]+)\s+[A-Z]/', $z, $m) ? $m[1] : '', explode("\n", $s))));
$check('Tabelle in Schema und Wanderung mit denselben Spalten', $spalten($ms[1] ?? '') === $spalten($mw[1] ?? 'x') && in_array('expense_id', $spalten($ms[1] ?? ''), true)
    && str_contains($schema, 'REFERENCES expenses(id) ON DELETE CASCADE') && str_contains($wanderung, "\$merken('103_buchungsbelege')"));
$check('Tabelle steht im Schema nach den Buchungen', strpos($schema, 'CREATE TABLE IF NOT EXISTS expenses (') < strpos($schema, 'CREATE TABLE IF NOT EXISTS expense_files ('));
$check('Einstellung und Speicherübersicht', str_contains($lies('sql/seed.sql'), "('ki_buchungen',") && str_contains($lies('src/lib/speicher.php'), "'budget'"));

// Aufräumen
foreach (glob($tmp . '/budget/*') ?: [] as $f) { @unlink($f); }
foreach (glob($tmp . '/*') ?: [] as $f) { is_dir($f) ? @rmdir($f) : @unlink($f); }
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
