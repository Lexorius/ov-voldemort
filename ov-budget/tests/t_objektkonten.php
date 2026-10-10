<?php
declare(strict_types=1);
/*
 * Objektkonten: Auswertung aus Kategorien, Speichern mit Zuordnung,
 * Ansichten (Pflege, Formular, Auswertung).
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'haushaltsjahr_start' => '0'];
$GLOBALS['tabellen'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    foreach ($GLOBALS['tabellen'] as $marke => $rows) { if (str_contains($sql, $marke)) { return is_callable($rows) ? $rows($p) : $rows; } }
    return [];
}
function db_row(string $sql, array $p = []): ?array { foreach ($GLOBALS['tabellen']['FROM objektkonten'] ?? [] as $k) { if ((int)$k['id'] === (int)($p[0] ?? 0)) { return $k; } } return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 7; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'wishes', 'order_rights', 'expenses', 'objektkonten'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nah = static fn(?float $a, float $b): bool => $a !== null && abs($a - $b) < 0.01;

/* ---------- Auswertung (rein) ---------- */
$konten = [
    ['id' => 1, 'nummer' => '4210', 'name' => 'Verpflegung', 'color' => '#b45309', 'is_active' => 1],
    ['id' => 2, 'nummer' => '', 'name' => 'Liegenschaft', 'color' => '#0369a1', 'is_active' => 1],
    ['id' => 3, 'nummer' => '9999', 'name' => 'Altes Konto', 'color' => '#999999', 'is_active' => 0],
];
$kategorien = [
    ['id' => 10, 'list_key' => 'ausgabe_kategorie', 'label' => 'Getränke', 'color' => '#111', 'is_active' => 1, 'objektkonto_id' => 1],
    ['id' => 11, 'list_key' => 'ausgabe_kategorie', 'label' => 'Lebensmittel', 'color' => '#222', 'is_active' => 1, 'objektkonto_id' => 1],
    ['id' => 12, 'list_key' => 'ausgabe_kategorie', 'label' => 'Nebenkosten', 'color' => '#333', 'is_active' => 1, 'objektkonto_id' => 2],
    ['id' => 13, 'list_key' => 'ausgabe_kategorie', 'label' => 'Werkzeug', 'color' => '#444', 'is_active' => 1, 'objektkonto_id' => null],
    ['id' => 20, 'list_key' => 'einnahme_kategorie', 'label' => 'Einsatz', 'color' => '#555', 'is_active' => 1, 'objektkonto_id' => 2],
    ['id' => 21, 'list_key' => 'einnahme_kategorie', 'label' => 'Spende Essen', 'color' => '#666', 'is_active' => 1, 'objektkonto_id' => 1],
];
$ausgaben = [
    ['id' => 11, 'label' => 'Lebensmittel', 'color' => '#222', 'anzahl' => 4, 'betrag' => 300.0],
    ['id' => 10, 'label' => 'Getränke', 'color' => '#111', 'anzahl' => 2, 'betrag' => 100.0],
    ['id' => 12, 'label' => 'Nebenkosten', 'color' => '#333', 'anzahl' => 1, 'betrag' => 500.0],
    ['id' => 13, 'label' => 'Werkzeug', 'color' => '#444', 'anzahl' => 1, 'betrag' => 80.0],
    ['id' => null, 'label' => null, 'color' => null, 'anzahl' => 1, 'betrag' => 20.0],
];
$einnahmen = [
    ['id' => 20, 'label' => 'Einsatz', 'color' => '#555', 'anzahl' => 1, 'betrag' => 1000.0],
    ['id' => 21, 'label' => 'Spende Essen', 'color' => '#666', 'anzahl' => 1, 'betrag' => 50.0],
];
$aw = objektkonto_auswertung($konten, $kategorien, $ausgaben, $einnahmen);
$check('Getränke und Lebensmittel laufen auf Verpflegung', $nah($aw['zeilen'][0]['ausgaben'], 400.0) && $aw['zeilen'][0]['anzahl'] === 7 && $nah($aw['zeilen'][0]['einnahmen'], 50.0)
    && count($aw['zeilen'][0]['posten']) === 3 && $aw['zeilen'][0]['posten'][0]['label'] === 'Lebensmittel');
$check('Anteile am Jahr', $aw['zeilen'][0]['anteil_ausgaben'] === 40.0 && $aw['zeilen'][1]['anteil_ausgaben'] === 50.0 && $aw['zeilen'][1]['anteil_einnahmen'] === 95.2 && $aw['zeilen'][0]['anteil_einnahmen'] === 4.8);
$check('Summen', $nah($aw['summe']['ausgaben'], 1000.0) && $nah($aw['summe']['einnahmen'], 1050.0));
$check('Ohne Konto: Werkzeug und Buchungen ohne Kategorie', $nah($aw['ohne']['ausgaben'], 100.0) && count($aw['ohne']['posten']) === 2 && $aw['ohne']['posten'][1]['ohne_kategorie'] === true
    && $aw['ohne']['posten'][1]['label'] === 'ohne Kategorie' && $aw['ohne']['anteil_ausgaben'] === 10.0 && $aw['ohne']['anteil_einnahmen'] === null);
$check('stillgelegtes Konto ohne Beträge bleibt weg', count($aw['zeilen']) === 2);
$aw2 = objektkonto_auswertung($konten, [['id' => 13, 'list_key' => 'ausgabe_kategorie', 'label' => 'Werkzeug', 'color' => '#444', 'is_active' => 1, 'objektkonto_id' => 3]], [$ausgaben[3]], []);
$check('stillgelegtes Konto mit Beträgen erscheint', count($aw2['zeilen']) === 3 && $nah($aw2['zeilen'][2]['ausgaben'], 80.0));
$check('ohne Buchungen leer', objektkonto_auswertung($konten, $kategorien, [], [])['summe'] === ['ausgaben' => 0.0, 'einnahmen' => 0.0]);
$check('Titel mit und ohne Nummer', objektkonto_titel($konten[0]) === '4210 Verpflegung' && objektkonto_titel($konten[1]) === 'Liegenschaft');
$je = objektkonto_kategorien_je_konto($kategorien);
$check('Kategorien je Konto, 0 = ohne', count($je[1]) === 3 && count($je[2]) === 2 && count($je[0]) === 1 && $je[0][0]['label'] === 'Werkzeug');

/* ---------- Speichern ---------- */
$_POST = ['name' => '  Verpflegung ', 'nummer' => '4210', 'beschreibung' => 'Essen und Trinken', 'color' => '#b45309', 'sort_order' => '10', 'is_active' => '1', 'kategorien' => ['10', '11', '11', 'x', '21']];
[$id, $fehler] = objektkonto_save_from_post(null, ['id' => 1]);
$check('Konto angelegt, Kategorien gesetzt', $id === 7 && $fehler === [] && $GLOBALS['inserts'][0][1]['name'] === 'Verpflegung' && $GLOBALS['inserts'][0][1]['nummer'] === '4210'
    && str_contains($GLOBALS['execs'][0][0], 'SET objektkonto_id = NULL WHERE objektkonto_id = ?') && $GLOBALS['execs'][0][1] === [7]
    && str_contains($GLOBALS['execs'][1][0], 'IN (?,?,?)') && $GLOBALS['execs'][1][1] === [7, 10, 11, 21]);
$GLOBALS['execs'] = [];
$_POST = ['name' => '', 'color' => 'rot'];
[$id, $fehler] = objektkonto_save_from_post(null, ['id' => 1]);
$check('ohne Namen Fehler', $id === null && str_contains($fehler[0], 'Namen'));
$_POST = ['name' => 'Liegenschaft', 'color' => 'rot', 'kategorien' => []];
[$id, $fehler] = objektkonto_save_from_post(['id' => 2, 'name' => 'alt'], ['id' => 1]);
$check('bearbeiten: ungültige Farbe wird Vorgabe, Zuordnung geleert', $id === 2 && $GLOBALS['updates'][0][1]['color'] === '#64748b' && count($GLOBALS['execs']) === 1);
$_POST = [];

/* ---------- Ansichten ---------- */
$html = render_partial('objektkonten', ['jahr' => 2026, 'konten' => $konten, 'jeKonto' => $je]);
$check('Pflegeseite: Konten mit Kategorie-Chips, Unzugeordnetes, Stilllegen', str_contains($html, '4210 Verpflegung') && str_contains($html, 'Getränke') && str_contains($html, 'Noch keinem Objektkonto zugeordnet')
    && str_contains($html, 'Werkzeug') && str_contains($html, 'value="aktiv"') && str_contains($html, 'Aktivieren') && str_contains($html, 'p=objektkonto_edit'));
$check('Pflegeseite leer', str_contains(render_partial('objektkonten', ['jahr' => 2026, 'konten' => [], 'jeKonto' => []]), 'Noch kein Objektkonto'));
$html = render_partial('objektkonto_edit', ['konto' => $konten[1] + ['beschreibung' => '', 'sort_order' => 0], 'errors' => [], 'kategorien' => $kategorien, 'gewaehlt' => [12, 20], 'kontenNamen' => array_column($konten, null, 'id')]);
$check('Formular: Kästchen je Kategorie, gewählte an, fremde mit Hinweis', substr_count($html, 'name="kategorien[]"') === 6 && str_contains($html, 'value="12" checked') && str_contains($html, 'value="20" checked')
    && str_contains($html, 'bisher 4210 Verpflegung') && str_contains($html, '<h3>Ausgaben</h3>') && str_contains($html, '<h3>Einnahmen</h3>') && str_contains($html, 'Objektkonto löschen'));
$html = render_partial('budget_objektkonten', ['jahr' => 2026, 'jahre' => [2026, 2025], 'auswertung' => $aw, 'konten' => 2]);
$check('Auswertung: Konten, Anteile, Posten, Ohne Objektkonto, Summen', str_contains($html, '4210 Verpflegung') && str_contains($html, '400,00') && str_contains($html, '40,0 %') && str_contains($html, '3 Posten')
    && str_contains($html, 'Lebensmittel') && str_contains($html, 'Ohne Objektkonto') && str_contains($html, 'Buchungen ohne Kategorie') && str_contains($html, '1.050,00') && str_contains($html, 'Objektkonten pflegen'));
$html = render_partial('budget_objektkonten', ['jahr' => 2026, 'jahre' => [2026], 'auswertung' => objektkonto_auswertung([], [], [], []), 'konten' => 0]);
$check('Auswertung ohne Konten: Hinweis', str_contains($html, 'Noch kein Objektkonto angelegt') && str_contains($html, 'Erstes Objektkonto anlegen'));
$html = render_partial('partials/budget_tabs', ['jahr' => 2026]);
$check('Reiter Objektkonten im Budget', str_contains($html, 'p=budget_objektkonten') && str_contains($html, '>Objektkonten<'));

/* ---------- Datenbank-Seite ---------- */
$GLOBALS['tabellen'] = ['FROM objektkonten' => $konten, 'FROM list_items' => $kategorien, 'FROM expenses e' => static fn(array $p) => $p[1] === 'einnahme' ? $einnahmen : $ausgaben];
$awDb = objektkonto_auswertung_jahr(2026);
$check('Auswertung aus der Datenbank', $nah($awDb['zeilen'][0]['ausgaben'], 400.0) && $nah($awDb['summe']['einnahmen'], 1050.0));
$mig = (string)file_get_contents($app . '/src/cli/migrate.php');
$check('Wanderung 057 legt Konten und Zuordnung an', str_contains($mig, '057_objektkonten') && str_contains($mig, 'fk_list_objektkonto'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
