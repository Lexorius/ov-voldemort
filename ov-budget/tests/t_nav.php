<?php
declare(strict_types=1);
/* Menü: Module, der Baum mit Gruppen (Grundvorschlag, Bereinigen, Sichtbarkeit), Umbauen, Leiste, Verwaltungsseite */
session_start();
$GLOBALS['settings'] = ['wunsch_modul_name' => 'Wünsche', 'funk_modul_name' => 'Funk'];
$GLOBALS['state'] = [];
$GLOBALS['rechte'] = ['view_vehicles' => true, 'admin' => true];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? false; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Tina Tester', 'username' => 'tina']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'nav'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$lies = static fn(string $pfad): string => (string)file_get_contents($app . '/' . $pfad);

/* ---------- Module ---------- */
$module = nav_module();
$keys = array_keys($module);
$check('vierundzwanzig Module, Übersicht zuerst, Verwaltung zuletzt', count($module) === 24
    && array_key_first($module) === 'dashboard' && array_key_last($module) === 'admin');
$check('Modulnamen aus den Einstellungen', $module['wishes']['label'] === 'Wünsche' && $module['radios']['label'] === 'Funk');

/* ---------- Grundvorschlag ---------- */
$grund = nav_baum_bereinigen(NAV_GRUNDVORSCHLAG, $keys);
$imBaum = array_column(nav_baum_flach($grund), 'modul');
$check('Grundvorschlag: jedes Modul genau einmal', count($imBaum) === 24 && count(array_unique($imBaum)) === 24 && array_diff($keys, $imBaum) === []);
$check('Grundvorschlag: Übersicht vorn, Verwaltung hinten, sechs Gruppen dazwischen', ($grund[0]['modul'] ?? '') === 'dashboard' && ($grund[count($grund) - 1]['modul'] ?? '') === 'admin'
    && array_column(array_filter($grund, static fn($k) => isset($k['gruppe'])), 'gruppe') === ['Einsatz', 'Planung', 'Termine', 'Kontakte', 'Technik', 'Unterkunft']);
$check('Grundvorschlag: kurze Namen für die Leiste am Handy', max(array_map('mb_strlen', array_column(array_filter($grund, static fn($k) => isset($k['gruppe'])), 'gruppe'))) <= 10);
$check('Grundvorschlag bleibt beim Bereinigen, wie er ist', $grund === NAV_GRUNDVORSCHLAG);
$check('ohne eigene Einstellung gilt der Grundvorschlag', nav_baum() === NAV_GRUNDVORSCHLAG && !nav_baum_eigen());

/* ---------- Bereinigen ---------- */
$roh = [['modul' => 'admin'], ['gruppe' => "  Technik \n ", 'icon' => 'XXXXXX', 'module' => ['vehicles', 'gibtsnicht', 'radios', 'vehicles']],
    ['modul' => 'vehicles'], ['gruppe' => '', 'module' => ['sims', 'stan']], ['unsinn' => 1], 'kaputt', ['gruppe' => 'Leer', 'module' => []]];
$sauber = nav_baum_bereinigen($roh, $keys);
$check('unbekannte Module und Dubletten fallen weg, das erste Vorkommen zählt', $sauber[1] === ['gruppe' => 'Technik', 'icon' => 'XXXX', 'module' => ['vehicles', 'radios']]
    && !in_array(['modul' => 'vehicles'], $sauber, true));
$check('eine Gruppe ohne Namen gibt es nicht – ihre Module stehen für sich', $sauber[2] === ['modul' => 'sims'] && $sauber[3] === ['modul' => 'stan']);
$check('leere Gruppe bleibt, ohne Zeichen gilt die Vorgabe', $sauber[4] === ['gruppe' => 'Leer', 'icon' => NAV_GRUPPE_ICON, 'module' => []]);
$check('fehlende Module kommen auf die oberste Ebene – keines geht verloren', count(array_column(nav_baum_flach($sauber), 'modul')) === 24);
$mitEnde = nav_baum_bereinigen([['modul' => 'dashboard'], ['modul' => 'admin']], $keys);
$check('Neues steht vor der Verwaltung, wenn die am Ende ist', ($mitEnde[count($mitEnde) - 1]['modul'] ?? '') === 'admin' && $mitEnde[0] === ['modul' => 'dashboard'] && count($mitEnde) === 24);
$check('Unsinn ergibt alle Module auf einer Ebene', count(nav_baum_bereinigen('kein baum', $keys)) === 24 && count(nav_baum_bereinigen(null, ['a', 'b'])) === 2);
$check('Name und Zeichen einer Gruppe', nav_gruppe_name(str_repeat('ä', 60)) === str_repeat('ä', 40) && nav_gruppe_name(['x']) === '' && nav_gruppe_icon('') === NAV_GRUPPE_ICON && nav_gruppe_icon(' 🚨 ') === '🚨');

/* ---------- Was jemand sieht ---------- */
$sicht = nav_baum_sichtbar(NAV_GRUNDVORSCHLAG, $module, 'vehicle_edit');
$arten = array_map(static fn($k) => $k['typ'] === 'gruppe' ? 'G:' . $k['name'] : $k['key'], $sicht);
$check('nur mit Rechten: Gruppen ohne sichtbares Modul fallen weg, mit einem zeigen sie das Modul selbst',
    $arten === ['dashboard', 'G:Planung', 'kalender', 'thw', 'G:Technik', 'standorte', 'admin']);
$technik = $sicht[4];
$check('Gruppe: Kennung, erstes Modul als Ziel, aktiv mit der aufgerufenen Seite', $technik['id'] === 'g5' && $technik['route'] === 'vehicles' && $technik['aktiv'] === true
    && array_column($technik['module'], 'key') === ['vehicles', 'stan'] && $technik['module'][0]['aktiv'] === true && $technik['module'][1]['aktiv'] === false);
$check('andere Knoten sind nicht aktiv', $sicht[0]['aktiv'] === false && $sicht[1]['aktiv'] === false);
$leiste = nav_leiste();
$check('Leiste nur mit Rechten: keine Kontakte, aber Fahrzeuge und Verwaltung, in der Reihenfolge des Baums',
    isset($leiste['vehicles'], $leiste['admin'], $leiste['dashboard']) && !isset($leiste['contacts']) && !isset($leiste['radios'])
    && array_keys($leiste) === ['dashboard', 'wishes', 'budget', 'todos', 'kalender', 'thw', 'vehicles', 'stan', 'standorte', 'admin']);

/* ---------- Umbauen ---------- */
$b = [['modul' => 'a'], ['gruppe' => 'G1', 'icon' => '▸', 'module' => ['b', 'c', 'd']], ['modul' => 'e'], ['gruppe' => 'G2', 'icon' => '▸', 'module' => []], ['modul' => 'admin']];
$check('Liste verschieben: hoch, runter, am Rand bleibt es', nav_liste_verschieben(['a', 'b', 'c'], 1, -1) === ['b', 'a', 'c'] && nav_liste_verschieben(['a', 'b', 'c'], 1, 1) === ['a', 'c', 'b']
    && nav_liste_verschieben(['a', 'b', 'c'], 0, -1) === ['a', 'b', 'c'] && nav_liste_verschieben(['a', 'b', 'c'], 2, 1) === ['a', 'b', 'c'] && nav_liste_verschieben(['a'], 5, 1) === ['a']);
$check('Knoten der obersten Ebene verschieben', array_keys(array_column(nav_baum_verschieben($b, 1, '', -1), 'gruppe', 'gruppe'))[0] === 'G1'
    && isset(nav_baum_verschieben($b, 1, '', -1)[0]['gruppe']) && nav_baum_verschieben($b, 1, '', -1)[1] === ['modul' => 'a']);
$check('Modul in seiner Gruppe verschieben', nav_baum_verschieben($b, 1, 'c', -1)[1]['module'] === ['c', 'b', 'd'] && nav_baum_verschieben($b, 1, 'd', 1)[1]['module'] === ['b', 'c', 'd']
    && nav_baum_verschieben($b, 1, 'x', 1) === $b);
$u1 = nav_baum_umhaengen($b, 'e', 3);
$check('einzelnes Modul in eine Gruppe hängen', $u1[2]['gruppe'] === 'G2' && $u1[2]['module'] === ['e'] && count($u1) === 4);
$u2 = nav_baum_umhaengen($b, 'c', 3);
$check('Modul von Gruppe zu Gruppe', $u2[1]['module'] === ['b', 'd'] && $u2[3]['module'] === ['c']);
$u3 = nav_baum_umhaengen($b, 'c', -1);
$check('Modul aus der Gruppe auf die oberste Ebene: steht hinter seiner Gruppe', $u3[1]['module'] === ['b', 'd'] && $u3[2] === ['modul' => 'c'] && $u3[3] === ['modul' => 'e']);
$check('umhängen in dieselbe Gruppe, an eine Stelle ohne Gruppe oder mit unbekanntem Modul ändert nichts', nav_baum_umhaengen($b, 'c', 1) === $b && nav_baum_umhaengen($b, 'c', 0) === $b && nav_baum_umhaengen($b, 'x', 3) === $b);
$n1 = nav_baum_gruppe_neu($b, '  Lager  ', '📦');
$check('neue Gruppe steht vor der Verwaltung', $n1[4] === ['gruppe' => 'Lager', 'icon' => '📦', 'module' => []] && $n1[5] === ['modul' => 'admin'] && nav_baum_gruppe_neu($b, '  ') === $b);
$check('Gruppe umbenennen; leerer Name ändert nichts', nav_baum_gruppe_aendern($b, 1, 'Neu', '★')[1]['gruppe'] === 'Neu' && nav_baum_gruppe_aendern($b, 1, 'Neu', '★')[1]['icon'] === '★'
    && nav_baum_gruppe_aendern($b, 1, ' ', '★') === $b && nav_baum_gruppe_aendern($b, 0, 'Neu', '') === $b);
$a1 = nav_baum_gruppe_aufloesen($b, 1);
$check('Gruppe auflösen: ihre Module stehen an ihrer Stelle', array_slice($a1, 0, 5) === [['modul' => 'a'], ['modul' => 'b'], ['modul' => 'c'], ['modul' => 'd'], ['modul' => 'e']] && nav_baum_gruppe_aufloesen($b, 0) === $b);
$check('alles auf eine Ebene', nav_baum_flach($b) === [['modul' => 'a'], ['modul' => 'b'], ['modul' => 'c'], ['modul' => 'd'], ['modul' => 'e'], ['modul' => 'admin']]);

/* ---------- Speichern ---------- */
nav_baum_speichern(nav_baum_umhaengen(nav_baum(), 'vehicles', -1));
$check('gespeicherter Baum gilt und ist als eigener erkennbar', nav_baum_eigen() && in_array(['modul' => 'vehicles'], nav_baum(), true)
    && isset(json_decode($GLOBALS['state']['nav_baum'], true)[0]['modul']));
$GLOBALS['state']['nav_baum'] = '{kaputt';
$check('kaputt gespeichert: der Grundvorschlag gilt', nav_baum() === NAV_GRUNDVORSCHLAG);
$GLOBALS['state']['nav_baum'] = '';

/* ---------- Leiste im Rahmen ---------- */
$_GET['p'] = 'budget';
$_SERVER['REQUEST_URI'] = '/index.php?p=budget';
ob_start();
$__content = '';
$title = 'Budget';
include $app . '/views/layout.php';
$seite = (string)ob_get_clean();
$check('Leiste: Gruppe als Verweis auf ihr erstes Modul, aufklappbar, die aktive steht offen', str_contains($seite, 'data-nav-gruppe="g2"') && str_contains($seite, 'aria-controls="nav-g2"')
    && (bool)preg_match('/mainnav__item--gruppe is-active" href="[^"]*p=wishes"\s+data-nav-gruppe="g2"[^>]*aria-expanded="true"/', $seite));
$check('Leiste: Module der aktiven Gruppe sichtbar, die anderen Gruppen zu', (bool)preg_match('/id="nav-g2" data-nav-panel="g2" data-nav-aktiv/', $seite)
    && (bool)preg_match('/id="nav-g5" data-nav-panel="g5" hidden/', $seite) && (bool)preg_match('/mainnav__unter is-active" href="[^"]*p=budget" aria-current="page"/', $seite));
$check('Leiste: Blatt mit Überschrift und Schließen-Knopf fürs Handy', substr_count($seite, 'data-nav-zu') === 2 && str_contains($seite, 'mainnav__panel-kopf'));
$check('Leiste: einzelne Module bleiben einfache Verweise', (bool)preg_match('/<a class="mainnav__item" href="[^"]*p=dashboard">/', $seite) && !str_contains($seite, 'p=contacts'));

/* ---------- Verwaltungsseite ---------- */
$html = render_partial('admin/nav', ['baum' => $b = nav_baum(), 'module' => $module, 'eigen' => false]);
$check('Verwaltung: jede Gruppe mit Name, Zeichen, Auflösen; jedes Modul mit Pfeilen und Umhängen', substr_count($html, 'value="gruppe_aendern"') === 6 && substr_count($html, 'value="gruppe_aufloesen"') === 6
    && substr_count($html, 'value="umhaengen"') === 24 && substr_count($html, 'value="hoch"') === 24 + 6 && str_contains($html, 'value="Einsatz"'));
$check('Verwaltung: neue Gruppe, Grundvorschlag (hier gesperrt), alles auf eine Ebene', str_contains($html, 'value="gruppe_neu"') && str_contains($html, 'value="flach"')
    && (bool)preg_match('/value="grundvorschlag">\s*<button[^>]* disabled>/', $html) && str_contains($html, 'Zu sehen ist der <strong>Grundvorschlag</strong>'));
$eigen = render_partial('admin/nav', ['baum' => nav_baum_flach($b), 'module' => $module, 'eigen' => true]);
$check('Verwaltung ohne Gruppen: keine Auswahl zum Umhängen, Zurücksetzen möglich', !str_contains($eigen, 'value="umhaengen"') && str_contains($eigen, 'Dieses Menü ist angepasst')
    && !preg_match('/value="grundvorschlag">\s*<button[^>]* disabled>/', $eigen));
$aktion = $lies('src/pages/admin/nav.php');
$check('Verwaltung nur für die Administration, jede Änderung im Protokoll', str_contains($aktion, "require_role('admin')") && str_contains($aktion, "audit('menue.baum'"));
$js = $lies('public/assets/js/app.js');
$css = $lies('public/assets/css/app.css');
$check('Skript: Gruppe klappt auf, am Handy ruht das Blatt zu', str_contains($js, '[data-nav-gruppe]') && str_contains($js, "navSchmal.matches ? null : navAktivId") && str_contains($js, "classList.add('hat-js')"));
$check('Stil: Blatt am Handy mit großen Tippflächen, zweite Zeile am Desktop', str_contains($css, '.mainnav.hat-js .mainnav__unter') && str_contains($css, 'min-height: 54px')
    && str_contains($css, 'body.nav-offen::before') && str_contains($css, '.mainnav__panel { position: static;'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
