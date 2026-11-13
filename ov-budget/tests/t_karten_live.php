<?php
declare(strict_types=1);
/* Karten: eingeblendete Fahrzeuge erneuern sich von selbst – Takt, Paket für den Browser, Verdrahtung */
session_start();
date_default_timezone_set('Europe/Berlin');
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
function geo_heim(): ?array { return null; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'standorte', 'karten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Takt ---------- */
$check('Vorgabe 30 Sekunden', karten_fahrzeug_sekunden() === 30 && KARTEN_FAHRZEUG_SEKUNDEN === 30);
$check('in den Grenzen', karten_fahrzeug_sekunden(5) === 10 && karten_fahrzeug_sekunden(0) === 10 && karten_fahrzeug_sekunden(-3) === 10
    && karten_fahrzeug_sekunden(45) === 45 && karten_fahrzeug_sekunden('120') === 120 && karten_fahrzeug_sekunden(99999) === 600);
$check('Unsinn fällt auf die Vorgabe', karten_fahrzeug_sekunden('schnell') === 30 && karten_fahrzeug_sekunden('') === 30 && karten_fahrzeug_sekunden('14.6') === 15);
$GLOBALS['settings']['karten_fahrzeug_sekunden'] = '15';
settings_reset_cache();
$check('aus den Einstellungen', karten_fahrzeug_sekunden() === 15);
$karte = ['id' => 3, 'lat' => 53.0793, 'lng' => 8.8017, 'zoom' => 14, 'fahrzeuge' => 1];
$k = karten_konfig($karte, [], [], ['setzen' => false, 'verwalten' => false, 'fahrzeuge' => true]);
$check('der Browser bekommt den Takt', $k['fahrzeugSekunden'] === 15 && $k['fahrzeugeAn'] === true);
$GLOBALS['settings']['karten_fahrzeug_sekunden'] = '2';
settings_reset_cache();
$check('zu kurzer Takt wird angehoben', karten_konfig($karte, [], [], ['fahrzeuge' => true])['fahrzeugSekunden'] === 10);

/* ---------- Verdrahtung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$js = $lies('public/assets/js/karte.js');
$holen = substr($js, (int)strpos($js, 'function fahrzeugeHolen()'), (int)strpos($js, 'function fahrzeugeSchalten()') - (int)strpos($js, 'function fahrzeugeHolen()'));
$check('Abruf im eingestellten Takt, nicht fest', str_contains($js, 'parseInt(K.fahrzeugSekunden, 10)') && str_contains($holen, 'window.setTimeout(fahrzeugeHolen, fzSekunden * 1000)')
    && !str_contains($js, '60000') && !str_contains($js, 'fzUhr'));
$check('Abruf baut die Fahrzeuge nicht neu auf', str_contains($holen, 'fahrzeugeZeigen(d.fahrzeuge)') && !str_contains($holen, 'fahrzeugeWeg()'));
$check('kein zweiter Abruf, solange einer läuft; im Hintergrund Ruhe', str_contains($holen, 'document.hidden || fzLaeuft') && str_contains($holen, 'fzLaeuft = false'));
$check('kein Zwischenspeicher des Browsers', str_contains($holen, "cache: 'no-store'"));
$check('Fehler lässt die Fahrzeuge stehen und versucht es weiter', str_contains($holen, 'nächster Versuch') && (int)strpos($holen, '.catch(') < (int)strrpos($holen, 'window.setTimeout(fahrzeugeHolen'));
$zeigen = substr($js, (int)strpos($js, 'function fahrzeugeZeigen('), (int)strpos($js, 'function fahrzeugeHolen()') - (int)strpos($js, 'function fahrzeugeZeigen('));
$check('bekannte Fahrzeuge werden angepasst, verschwundene entfernt', str_contains($zeigen, 'nachId[fz.id]') && str_contains($zeigen, 'markerEbene.removeChild(nachId[id].el)')
    && str_contains($zeigen, "classList.add('is-gleitend')") && str_contains($zeigen, "classList.remove('is-gleitend')"));
$check('geändertes Zeichen wird getauscht', str_contains($zeigen, 'f.d.src !== fz.src') && str_contains($js, 'function fahrzeugBild('));
$check('zurück auf der Seite und wieder im Netz wird sofort geholt', str_contains($js, "document.addEventListener('visibilitychange'") && str_contains($js, "window.addEventListener('online'"));
$css = $lies('public/assets/css/app.css');
$check('Gleiten nur nach einem Abruf, mit Rücksicht auf reduzierte Bewegung', str_contains($css, '.lagekarte__fahrzeug.is-gleitend { transition: left') && str_contains($css, 'prefers-reduced-motion: reduce')
    && !preg_match('/\.lagekarte__fahrzeug \{[^}]*transition/', $css));
$check('Stand mit Sekunden', str_contains($lies('src/pages/karten_api.php'), "'stand' => date('H:i:s')"));
$check('Einstellung mitgeliefert', str_contains($lies('sql/seed.sql'), "('karten_fahrzeug_sekunden','30',"));
$check('Doku', str_contains($lies('DOCS.md'), 'erneuert die') && str_contains($lies('CHANGELOG.md'), 'erneuern sich von selbst'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
