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
$check('Fassungen vom 19.11.2026 und neuere gelten als sicher', php_verwundbar('8.5.13') === null && php_verwundbar('8.4.28') === null && php_verwundbar('8.3.37') === null && php_verwundbar('8.2.36') === null && php_verwundbar('8.5.14-r1') === null && php_verwundbar('8.6.0') === null);
$check('ältere Fassungen sind verwundbar, mit Zielfassung', str_contains((string)php_verwundbar('8.5.11'), 'behoben ab 8.5.13') && str_contains((string)php_verwundbar('8.4.26-r0'), 'behoben ab 8.4.28') && str_contains((string)php_verwundbar('8.3.35'), '8.3.37') && str_contains((string)php_verwundbar('8.2.34'), '8.2.36'));
$check('vor PHP2Shell gepatcht: beides genannt', str_contains((string)php_verwundbar('8.5.11'), 'PHP2Shell') && str_contains((string)php_verwundbar('8.5.11'), '19.11.2026') && php2shell_offen('8.5.11') && php2shell_offen('8.1.33'));
$check('gegen PHP2Shell gepatcht, aber ohne die Fassung vom 19.11.: nur die wird genannt', str_contains((string)php_verwundbar('8.5.12'), '19.11.2026') && !str_contains((string)php_verwundbar('8.5.12'), 'PHP2Shell')
    && str_contains((string)php_verwundbar('8.4.27-r0'), 'CVE-2026-129496') && !php2shell_offen('8.5.12') && !php2shell_offen('8.4.27') && !php2shell_offen('8.6.0') && !php2shell_offen('?'));
$check('8.1 und älter: keine Updates mehr', str_contains((string)php_verwundbar('8.1.33'), 'keine Sicherheitsupdates') && str_contains((string)php_verwundbar('7.4.33'), 'keine Sicherheitsupdates'));
$check('unbekannte Angabe: kein Urteil', php_verwundbar('') === null && php_verwundbar('?') === null);
$lage = php_lage();
$check('eigene Lage hat Fassung, Urteil, Schutzliste', $lage['version'] === PHP_VERSION && is_bool($lage['verwundbar']) && is_bool($lage['php2shell']) && array_key_exists('upload_progress', $lage['schutz']) && array_key_exists('backtraces', $lage['schutz']));

$app = dirname(__DIR__);
$ini = (string)file_get_contents($app . '/docker/rootfs/opt/ovbudget/php/zz-ovbudget.ini');
$check('Container: Upload-Fortschritt der Sitzung aus, Backtraces aus, memory_limit ohne Grenze, post_max_size klein', preg_match('/^session\.upload_progress\.enabled\s*=\s*(0|Off)/mi', $ini) === 1 && preg_match('/^fatal_error_backtraces\s*=\s*(0|Off)/mi', $ini) === 1
    && preg_match('/^memory_limit\s*=\s*-1/m', $ini) === 1 && preg_match('/^post_max_size\s*=\s*(\d+)M/m', $ini, $m) === 1 && (int)$m[1] <= 64);
$fpm = (string)file_get_contents($app . '/docker/rootfs/opt/ovbudget/php/zz-ovbudget.conf');
$check('Container: jeder Worker bedient eine Anfrage (bricht die Zwei-Anfragen-Kette)', preg_match('/^pm\.max_requests\s*=\s*1\s*$/m', $fpm) === 1);
$docker = (string)file_get_contents($app . '/Dockerfile');
$check('Bauvorgang stellt Alpine auf edge, bricht ab wenn das nicht greift, und zieht alle Pakete nach', str_contains($docker, "s#/alpine/v[0-9]+\\.[0-9]+/#/alpine/edge/#")
    && str_contains($docker, "grep -q '/alpine/edge/main' /etc/apk/repositories") && str_contains($docker, "grep -q '/alpine/edge/community' /etc/apk/repositories")
    && str_contains($docker, 'apk upgrade --no-cache --available') && strpos($docker, '/alpine/edge/#') < strpos($docker, 'apk upgrade'));
$check('Bauvorgang prüft die PHP-Fassung gegen die Sicherheitsfassungen vom 19.11.2026', str_contains($docker, 'PHP2Shell') && str_contains($docker, 'SOLL=8.5.13') && str_contains($docker, 'SOLL=8.4.28')
    && str_contains($docker, 'SOLL=8.3.37') && str_contains($docker, 'SOLL=8.2.36') && !str_contains($docker, 'SOLL=8.5.12'));
$check('Bauvorgang nimmt PHP 8.5: kein OPcache-Paket ab 8.5 verlangt, 8.5 steht in der Suche vorn', str_contains($docker, 'for v in 85 84 83 82')
    && str_contains($docker, '[ "$v" -ge 85 ] || set -- "$@" "php$v-opcache"') && substr_count($docker, 'php$v-opcache') === 1);
$check('Dockerfile und Anwendung nennen dieselben Fassungen', array_values(PHP_SICHER_AB) === ['8.5.13', '8.4.28', '8.3.37', '8.2.36']);
$admin = (string)file_get_contents($app . '/views/admin/index.php');
$check('Verwaltung: Schutzmaßnahmen nur erwähnt, solange PHP2Shell offen ist', str_contains($admin, "!\$php['php2shell'] ? ''") && str_contains($admin, '19.11.2026'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
