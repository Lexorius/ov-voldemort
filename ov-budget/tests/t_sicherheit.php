<?php
declare(strict_types=1);
/*
 * Sicherheitslage: PHP2Shell-Urteil je Fassung, Kernfassung aus Paketangaben,
 * Schutzmaßnahmen im Container, Befund in der Connector-Prüfung.
 */
require dirname(__DIR__) . '/src/lib/sicherheit.php';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$check('Kernfassung aus Paketangaben', php_version_kern('8.4.21-r0') === '8.4.21' && php_version_kern('8.5.12 (fpm-fcgi)') === '8.5.12' && php_version_kern('quatsch') === 'quatsch');
$check('gepatchte Fassungen gelten als sicher', php_verwundbar('8.5.12') === null && php_verwundbar('8.4.27') === null && php_verwundbar('8.3.36') === null && php_verwundbar('8.2.35') === null && php_verwundbar('8.5.13') === null && php_verwundbar('8.6.0') === null);
$check('ältere Fassungen sind verwundbar, mit Zielfassung', str_contains((string)php_verwundbar('8.5.11'), 'behoben ab 8.5.12') && str_contains((string)php_verwundbar('8.4.26-r0'), 'behoben ab 8.4.27') && str_contains((string)php_verwundbar('8.3.35'), '8.3.36') && str_contains((string)php_verwundbar('8.2.34'), '8.2.35'));
$check('8.1 und älter: keine Updates mehr', str_contains((string)php_verwundbar('8.1.33'), 'keine Sicherheitsupdates') && str_contains((string)php_verwundbar('7.4.33'), 'keine Sicherheitsupdates'));
$check('unbekannte Angabe: kein Urteil', php_verwundbar('') === null && php_verwundbar('?') === null);
$lage = php_lage();
$check('eigene Lage hat Fassung, Urteil, Schutzliste', $lage['version'] === PHP_VERSION && is_bool($lage['verwundbar']) && array_key_exists('upload_progress', $lage['schutz']) && array_key_exists('backtraces', $lage['schutz']));

$app = dirname(__DIR__);
$ini = (string)file_get_contents($app . '/docker/rootfs/opt/ovbudget/php/zz-ovbudget.ini');
$check('Container: Upload-Fortschritt der Sitzung aus, Backtraces aus, memory_limit ohne Grenze, post_max_size klein', preg_match('/^session\.upload_progress\.enabled\s*=\s*(0|Off)/mi', $ini) === 1 && preg_match('/^fatal_error_backtraces\s*=\s*(0|Off)/mi', $ini) === 1
    && preg_match('/^memory_limit\s*=\s*-1/m', $ini) === 1 && preg_match('/^post_max_size\s*=\s*(\d+)M/m', $ini, $m) === 1 && (int)$m[1] <= 64);
$fpm = (string)file_get_contents($app . '/docker/rootfs/opt/ovbudget/php/zz-ovbudget.conf');
$check('Container: jeder Worker bedient eine Anfrage (bricht die Zwei-Anfragen-Kette)', preg_match('/^pm\.max_requests\s*=\s*1\s*$/m', $fpm) === 1);
$docker = (string)file_get_contents($app . '/Dockerfile');
$check('Bauvorgang prüft die PHP-Fassung gegen die gepatchten und versucht edge', str_contains($docker, 'PHP2Shell') && str_contains($docker, 'alpine/edge/community') && str_contains($docker, '8.5.12') && str_contains($docker, 'apk upgrade'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
