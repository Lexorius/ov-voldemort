<?php
declare(strict_types=1);
/*
 * Als App: Web-App-Manifest und Symbole.
 *
 * Damit sich das Multitool am Handy und am Rechner installieren lässt,
 * braucht es ein Manifest mit Name, Startadresse und Symbolen in 192 und
 * 512 Pixeln (eines davon maskierbar) sowie die Verweise im Seitenkopf.
 * Das Manifest liegt neben index.php und arbeitet mit relativen Pfaden –
 * hinter dem Ingress von Home Assistant stimmen nur die.
 */
session_start();
$GLOBALS['settings'] = ['app_name' => 'OV-Multitool Musterstadt', 'ov_kurz' => 'OV Muster', 'theme_color' => '#112233'];
require __DIR__ . '/stub_db.php';
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/manifest.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ------------------------------------------------------------------ */
/* Symbole liegen vor und haben die richtige Größe                      */
/* ------------------------------------------------------------------ */
$symbole = $app . '/public/assets/icon/';
foreach (['app-192.png' => 192, 'app-512.png' => 512, 'app-maskable-512.png' => 512, 'apple-touch-icon.png' => 180] as $datei => $px) {
    $info = is_file($symbole . $datei) ? getimagesize($symbole . $datei) : false;
    $check("$datei ist ein PNG mit {$px}×{$px}", $info !== false && $info[0] === $px && $info[1] === $px && $info['mime'] === 'image/png');
}
$svg = (string)file_get_contents($symbole . 'app.svg');
$check('app.svg ist quadratisch (viewBox 512)', str_contains($svg, 'viewBox="0 0 512 512"'));
$check('app.svg ohne Skript und ohne Verweise nach außen', !str_contains($svg, '<script') && !preg_match('#https?://(?!www\.w3\.org/)#', $svg));
$check('app.svg in THW-Blau', str_contains($svg, '#003399'));
$addon = $app . '/icon.png';
$info = is_file($addon) ? getimagesize($addon) : false;
$check('Add-on-Symbol icon.png mit 128×128', $info !== false && $info[0] === 128 && $info[1] === 128);

/* ------------------------------------------------------------------ */
/* Manifest-Inhalt                                                      */
/* ------------------------------------------------------------------ */
$m = manifest_daten('OV-Multitool', '', '#003399');
$check('Name und Kurzname', $m['name'] === 'OV-Multitool' && $m['short_name'] === 'OV-Multitool');
$check('Startadresse relativ, Geltungsbereich ./', !str_starts_with($m['start_url'], '/') && $m['scope'] === './' && $m['id'] === './');
$check('eigenes Fenster', $m['display'] === 'standalone');
$check('Farbe übernommen', $m['theme_color'] === '#003399' && preg_match('/^#[0-9a-f]{6}$/', $m['background_color']) === 1);
$groessen = array_column($m['icons'], 'sizes');
$check('Symbole 192 und 512', in_array('192x192', $groessen, true) && in_array('512x512', $groessen, true));
$check('ein maskierbares Symbol', count(array_filter($m['icons'], static fn($i) => $i['purpose'] === 'maskable')) === 1);
$check('ein SVG-Symbol mit sizes any', count(array_filter($m['icons'], static fn($i) => $i['type'] === 'image/svg+xml' && $i['sizes'] === 'any')) === 1);
$alleDa = true;
foreach ($m['icons'] as $i) {
    $alleDa = $alleDa && !str_starts_with($i['src'], '/') && is_file($app . '/public/' . $i['src']);
}
$check('jedes genannte Symbol liegt relativ unter public/', $alleDa);

$m = manifest_daten('  ', '', 'rot');
$check('leerer Name → OV-Multitool, falsche Farbe → THW-Blau', $m['name'] === 'OV-Multitool' && $m['theme_color'] === '#003399');
$m = manifest_daten('Ortsverband Musterstadt an der Lahn', '', '#ABCDEF');
$check('ohne Kurznamen wird der Name auf zwölf Zeichen gekürzt', $m['short_name'] === 'Ortsverband ' || mb_strlen($m['short_name']) <= 12);
$check('Farbe kleingeschrieben', $m['theme_color'] === '#abcdef');
$m = manifest_daten('OV-Multitool', 'OV Muster', '#003399');
$check('Kurzname aus den Einstellungen', $m['short_name'] === 'OV Muster');

/* ------------------------------------------------------------------ */
/* Antwort der Datei                                                    */
/* ------------------------------------------------------------------ */
[$code, $kopf, $rumpf] = manifest_antwort('GET', true);
$json = json_decode($rumpf, true);
$check('GET mit Datenbank: 200 und Manifest-Inhaltstyp', $code === 200 && str_starts_with($kopf['Content-Type'], 'application/manifest+json'));
$check('Werte aus den Einstellungen', is_array($json) && $json['name'] === 'OV-Multitool Musterstadt' && $json['short_name'] === 'OV Muster' && $json['theme_color'] === '#112233');
$check('kurz zwischenspeicherbar', str_contains($kopf['Cache-Control'], 'max-age=3600'));
$check('Umlaute lesbar, Pfade ohne Backslash', str_contains($rumpf, 'für den THW') && !str_contains($rumpf, '\\/'));

[$code, , $rumpf] = manifest_antwort('GET', false);
$json = json_decode($rumpf, true);
$check('ohne Datenbank (Einrichtung): Standardwerte statt Fehler', $code === 200 && $json['name'] === 'OV-Multitool' && $json['theme_color'] === '#003399');
[$code, $kopf] = manifest_antwort('POST', true);
$check('POST wird abgewiesen', $code === 405 && $kopf['Allow'] === 'GET, HEAD');
[$code] = manifest_antwort('HEAD', true);
$check('HEAD geht', $code === 200);

/* ------------------------------------------------------------------ */
/* Einbau: Datei, Seitenkopf, Service Worker                            */
/* ------------------------------------------------------------------ */
$datei = (string)file_get_contents($app . '/public/manifest.php');
$check('public/manifest.php nutzt manifest_antwort und den Bootstrap', str_contains($datei, 'manifest_antwort(') && str_contains($datei, "src/bootstrap.php'"));
$check('Bootstrap lädt die Bibliothek', str_contains((string)file_get_contents($app . '/src/bootstrap.php'), "lib/manifest.php'"));

$layout = (string)file_get_contents($app . '/views/layout.php');
$check('Seitenkopf verweist auf das Manifest – mit Sitzung, wegen des Ingress', str_contains($layout, '<link rel="manifest" href="<?= e(base_path()) ?>/manifest.php" crossorigin="use-credentials">'));
$check('Symbol für den Reiter (SVG und PNG)', str_contains($layout, "asset('icon/app.svg')") && str_contains($layout, "asset('icon/app-192.png')"));
$check('Symbol fürs iPhone', str_contains($layout, 'rel="apple-touch-icon"') && str_contains($layout, "asset('icon/apple-touch-icon.png')"));
$check('Name auf dem iPhone-Startbildschirm', str_contains($layout, 'name="apple-mobile-web-app-title"'));
$check('Manifest vor dem Stylesheet, damit es früh gelesen wird', strpos($layout, 'rel="manifest"') < strpos($layout, 'rel="stylesheet"'));

$js = (string)file_get_contents($app . '/public/assets/js/app.js');
$check('app.js meldet sw.js neben index.php an', str_contains($js, "new URL('sw.js', window.location.href.split('?')[0]).pathname") && str_contains($js, 'navigator.serviceWorker.register(swPfad)'));
$check('… nur im sicheren Kontext und ohne Fehler nach außen', str_contains($js, "'serviceWorker' in navigator && window.isSecureContext") && str_contains($js, '.register(swPfad).catch('));
$check('sw.js liegt neben index.php', is_file($app . '/public/sw.js') && is_file($app . '/public/index.php'));

$check('Dockerfile nimmt public/ samt Symbolen mit', str_contains((string)file_get_contents($app . '/Dockerfile'), 'COPY public/ /app/public/'));
$check('nginx lässt SVG und PNG zwischenspeichern', str_contains((string)file_get_contents($app . '/docker/rootfs/etc/nginx/nginx.conf'), 'svg|png'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
