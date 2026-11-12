<?php
declare(strict_types=1);
/*
 * Hinweis über jedem Upload-Feld: Text aus den Einstellungen, leer = aus,
 * und er steht an jedem Feld, das Dokumente oder Bilder annimmt.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$einstellen = static function (?string $text): void {
    if ($text === null) { unset($GLOBALS['settings']['upload_hinweis']); } else { $GLOBALS['settings']['upload_hinweis'] = $text; }
    settings_reset_cache();
};

/* ---------- Der Baustein ---------- */
$check('ohne Einstellung kein Hinweis', upload_hinweis_html() === '');
$einstellen('   ');
$check('leerer Text = aus', upload_hinweis_html() === '');
$einstellen('Keine Dokumente mit personenbezogenen Daten, keine als VS und höher eingestuften Dokumente.');
$html = upload_hinweis_html();
$check('Text aus der Einstellung als Hinweis', str_starts_with($html, '<p class="upload-hinweis" role="note">') && str_contains($html, 'Keine Dokumente mit personenbezogenen Daten, keine als VS und höher eingestuften Dokumente.'));
$einstellen("Nichts <b>Geheimes</b> & nichts Privates\nIm Zweifel fragen");
$html = upload_hinweis_html();
$check('Text wird maskiert, Zeilen bleiben Zeilen', str_contains($html, 'Nichts &lt;b&gt;Geheimes&lt;/b&gt; &amp; nichts Privates<br />') && str_contains($html, 'Im Zweifel fragen') && !str_contains($html, '<b>'));

/* ---------- Er steht an jedem Upload-Feld für Dokumente und Bilder ---------- */
// Datenimporte sind keine Ablage: eine Kontaktliste enthält naturgemäß Personen, die Sicherung alles
$ausnahmen = ['contacts_import.php', 'tankkarten_import.php', 'admin/backup.php'];
$felder = 0;
$ohne = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($app . '/views/')));
    $quelle = (string)file_get_contents($f->getPathname());
    $n = substr_count($quelle, 'type="file"');
    if ($n === 0 || in_array($rel, $ausnahmen, true)) {
        continue;
    }
    $felder += $n;
    // direkt vor dem Feld: im Fließtext der Ansicht oder im zusammengesetzten Text des Bausteins
    $mit = substr_count($quelle, '<?= upload_hinweis_html() ?><input type="file"')
        + preg_match_all('/upload_hinweis_html\(\)\s*\.\s*\'<input type="file"/', $quelle);
    if ($mit !== $n) {
        $ohne[] = $rel . ' (' . $mit . ' von ' . $n . ')';
    }
}
$check('Upload-Felder gefunden', $felder >= 12);
$check('jedes Upload-Feld trägt den Hinweis' . ($ohne ? ': ' . implode(', ', $ohne) : ''), $ohne === []);
foreach ($ausnahmen as $rel) {
    $check("Ausnahme $rel gibt es und ist ein Import", str_contains((string)file_get_contents($app . '/views/' . $rel), 'type="file"'));
}

/* ---------- In einer echten Ansicht ---------- */
$leer = ['id' => null, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-03-12', 'bezeichnung' => '', 'beschreibung' => '', 'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null,
    'wish_id' => null, 'event_id' => null, 'betrag_brutto' => '', 'betrag_netto' => '', 'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'notiz' => '', 'bezahlt_am' => null, 'status' => 'bezahlt',
    'ausgelegt_von' => null, 'ausgelegt_name' => '', 'weitergegeben_am' => null, 'erstattet_am' => null];
$form = static fn(): string => render_partial('expense_edit', ['art' => 'ausgabe', 'expense' => $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => [], 'helfer' => [],
    'stichtag' => ['stichtag' => null, 'tage' => null, 'gesperrt' => false]]);
$einstellen('Keine Dokumente mit personenbezogenen Daten.');
$seite = $form();
$check('Buchungsformular: Hinweis steht über dem Feld', (bool)preg_match('#<p class="upload-hinweis" role="note">.*?Keine Dokumente mit personenbezogenen Daten\.</p><input type="file" id="belege"#s', $seite));
$einstellen('');
$check('Buchungsformular: ohne Text auch kein Hinweis', !str_contains($form(), 'upload-hinweis') && str_contains($form(), '<input type="file" id="belege"'));

/* ---------- Einstellung ---------- */
$seed = (string)file_get_contents($app . '/sql/seed.sql');
$check('Einstellung als mehrzeiliger Text unter Allgemein, mit Vorgabe', (bool)preg_match("/\\('upload_hinweis','Keine Dokumente mit personenbezogenen Daten[^']*','[^']*','[^']*Leer = kein Hinweis\\.','textarea','Allgemein',\\d+\\)/u", $seed));
$check('Gestaltung vorhanden', str_contains((string)file_get_contents($app . '/public/assets/css/app.css'), '.upload-hinweis {'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
