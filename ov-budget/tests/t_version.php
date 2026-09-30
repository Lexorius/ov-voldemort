<?php
declare(strict_types=1);
// Versionsanzeige in der Fußzeile und "Was ist neu"
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'footer_text' => ''];
require __DIR__ . '/stub_db.php';
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

preg_match('/^version:\s*"([^"]+)"/m', file_get_contents($app . '/config.yaml'), $m);
$check('Version aus config.yaml', app_version() === $m[1]);
$abschnitte = changelog_sections(3);
$check('neuester Abschnitt ist die laufende Version', $abschnitte[0]['version'] === app_version());
$check('drei Abschnitte', count($abschnitte) === 3);
$check('alle Versionen', count(changelog_sections(1000)) >= 20);

$html = markdown_simple("### Titel\n\n- Punkt eins\n  geht weiter\n- **fett** und `code`\n\nAbsatz <b>x</b>");
$check('Überschrift', str_contains($html, '<h3>Titel</h3>'));
$check('Fortsetzungszeile gehört zum Punkt', str_contains($html, '<li>Punkt eins geht weiter</li>'));
$check('fett und Code', str_contains($html, '<strong>fett</strong>') && str_contains($html, '<code>code</code>'));
$check('HTML wird maskiert', str_contains($html, '&lt;b&gt;x&lt;/b&gt;') && !str_contains($html, '<b>'));
$check('javascript-Link nicht möglich', !str_contains(markdown_simple('<javascript:alert(1)>'), 'href'));

$_SERVER['REQUEST_URI'] = '/?p=neu';
$seite = render_partial('changelog', ['version' => app_version(), 'abschnitte' => $abschnitte, 'alle' => false]);
$check('Seite nennt die Version', str_contains($seite, 'OV-Multitool ' . app_version()));
$check('läuft gerade markiert', substr_count($seite, 'läuft gerade') === 1);

// Fußzeile: nur den Teil des Layouts prüfen
$layout = (string)file_get_contents($app . '/views/layout.php');
$check('Fußzeile zeigt die Version', str_contains($layout, "OV-Multitool <?= e(app_version()) ?>"));
$check('Fußzeile verlinkt Was ist neu', str_contains($layout, "url('neu')"));

// Container bekommt config.yaml und CHANGELOG.md
$docker = (string)file_get_contents($app . '/Dockerfile');
$check('Dockerfile kopiert config.yaml und CHANGELOG.md', str_contains($docker, 'COPY config.yaml CHANGELOG.md /app/'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
