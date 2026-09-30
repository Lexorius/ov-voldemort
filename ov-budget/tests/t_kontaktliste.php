<?php
declare(strict_types=1);
/* Kontaktliste auf dem Handy: gleiche Symbole, keine verschachtelten Links */
ob_start();
require __DIR__ . '/v_kontakte.php';
$html = ob_get_clean();
$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$check('Symbol telefon', str_contains(icon('telefon'), '<svg class="icon"'));
$check('Symbol mobil', str_contains(icon('mobil'), '<svg class="icon"'));
$check('Symbol mail', str_contains(icon('mail'), '<svg class="icon"'));
$check('unbekanntes Symbol leer', icon('gibtsnicht') === '');
$attr = static fn(string $s): string => preg_replace('/<(path|rect)[^>]*>/', '', $s);
$check('alle Symbole gleich gezeichnet', $attr(icon('telefon')) === $attr(icon('mobil')) && $attr(icon('mobil')) === $attr(icon('mail')));
$check('Symbole für Screenreader versteckt', substr_count(icon('mail'), 'aria-hidden="true"') === 1);

$check('keine Emoji mehr in der Liste', !preg_match('/📱|☎|✉/u', $html));
$check('drei Arten Kontaktweg', substr_count($html, '<svg class="icon"') === 5);
$check('Beschriftung für Screenreader', str_contains($html, 'sr-only">Mobil:') && str_contains($html, 'sr-only">E-Mail:'));

// Kein <a> innerhalb eines <a>
$tiefe = 0; $verschachtelt = false;
preg_match_all('#<(/?)a\b[^>]*>#i', $html, $m, PREG_SET_ORDER);
foreach ($m as $t) {
    if ($t[1] === '') { if ($tiefe > 0) { $verschachtelt = true; } $tiefe++; } else { $tiefe--; }
}
$check('keine Links in Links', !$verschachtelt && $tiefe === 0);
$check('Name führt zum Bearbeiten', str_contains($html, 'class="item__link"'));
$check('Karte ist kein Link mehr', !str_contains($html, '<a class="item"'));
echo "$ok bestanden, $fail fehlgeschlagen\n";
