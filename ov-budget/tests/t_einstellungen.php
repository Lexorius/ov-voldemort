<?php
declare(strict_types=1);
/*
 * Einstellungsmaske: Speichern einer Gruppe darf die Schalter anderer
 * Gruppen nicht anfassen. Nachgestellt: Stein.APP-Abgleich ist an, jemand
 * speichert danach die Gruppe "Divera 24/7".
 */
session_start();
$GLOBALS['execs'] = [];
$GLOBALS['zeilen'] = [
    ['skey' => 'ov_name', 'svalue' => 'THW OV Musterstadt', 'stype' => 'text', 'sgroup' => 'Allgemein', 'label' => 'Name', 'hint' => '', 'sort_order' => 1],
    ['skey' => 'stein_aktiv', 'svalue' => '1', 'stype' => 'bool', 'sgroup' => 'Stein.APP', 'label' => 'Abgleich', 'hint' => '', 'sort_order' => 10],
    ['skey' => 'stein_api_key', 'svalue' => 'geheim', 'stype' => 'password', 'sgroup' => 'Stein.APP', 'label' => 'Schlüssel', 'hint' => '', 'sort_order' => 20],
    ['skey' => 'divera_aktiv', 'svalue' => '1', 'stype' => 'bool', 'sgroup' => 'Divera 24/7', 'label' => 'Divera', 'hint' => '', 'sort_order' => 10],
    ['skey' => 'divera_fahrzeuge_aktiv', 'svalue' => '0', 'stype' => 'bool', 'sgroup' => 'Divera 24/7', 'label' => 'Fahrzeuge', 'hint' => '', 'sort_order' => 100],
    ['skey' => 'divera_accesskey', 'svalue' => 'alt', 'stype' => 'password', 'sgroup' => 'Divera 24/7', 'label' => 'Key', 'hint' => '', 'sort_order' => 30],
    ['skey' => 'divera_timeout', 'svalue' => '15', 'stype' => 'number', 'sgroup' => 'Divera 24/7', 'label' => 'Timeout', 'hint' => '', 'sort_order' => 70],
    // interner Merkwert (nach Wanderung 009)
    ['skey' => 'stein_letzter_abruf', 'svalue' => '1800000000', 'stype' => 'text', 'sgroup' => '_intern', 'label' => 'stein_letzter_abruf', 'hint' => '', 'sort_order' => 0],
];
function db_all(string $sql, array $p = []): array { return str_contains($sql, 'FROM settings') ? $GLOBALS['zeilen'] : []; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }

$app = dirname(__DIR__);
require $app . '/src/lib/settings.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$gruppen = settings_grouped();
$check('interne Merkwerte nicht in der Maske', !isset($gruppen['_intern']));
$check('drei sichtbare Gruppen', array_keys($gruppen) === ['Allgemein', 'Stein.APP', 'Divera 24/7']);

// Das Formular der Gruppe "Divera 24/7" – so wie der Browser es schickt:
// "Fahrzeuge" angehakt, "Divera" angehakt, Passwort leer gelassen
$post = [
    's_divera_aktiv' => '1',
    's_divera_fahrzeuge_aktiv' => '1',
    's_divera_accesskey' => '',
    's_divera_timeout' => ' 20 ',
];
$werte = settings_from_post($gruppen['Divera 24/7'], $post);
$check('Stein-Abgleich bleibt unberührt', !array_key_exists('stein_aktiv', $werte));
$check('nichts aus anderen Gruppen', array_diff(array_keys($werte),
    ['divera_aktiv', 'divera_fahrzeuge_aktiv', 'divera_timeout']) === []);
$check('Schalter angehakt', $werte['divera_fahrzeuge_aktiv'] === '1' && $werte['divera_aktiv'] === '1');
$check('leeres Passwort bleibt, wie es ist', !array_key_exists('divera_accesskey', $werte));
$check('Zahl ohne Leerzeichen', $werte['divera_timeout'] === '20');

// Schalter abgehakt: fehlt im Formular ganz – in DIESER Gruppe heißt das "aus"
$werte = settings_from_post($gruppen['Divera 24/7'], ['s_divera_timeout' => '15']);
$check('abgehakter Schalter der eigenen Gruppe wird aus', $werte['divera_aktiv'] === '0'
    && $werte['divera_fahrzeuge_aktiv'] === '0');

// Die Gruppe Stein.APP speichern: Passwort neu gesetzt
$werte = settings_from_post($gruppen['Stein.APP'], ['s_stein_aktiv' => '1', 's_stein_api_key' => 'neu']);
$check('Stein-Gruppe: Schalter und neuer Schlüssel', $werte === ['stein_aktiv' => '1', 'stein_api_key' => 'neu']);

// Passwortfeld gezielt leeren (z. B. Cron-Token abschalten)
$werte = settings_from_post($gruppen['Stein.APP'], ['s_stein_aktiv' => '1', 's_stein_api_key' => '', 's_stein_api_key__leeren' => '1']);
$check('Passwort gezielt geleert', ($werte['stein_api_key'] ?? null) === '');
$werte = settings_from_post($gruppen['Stein.APP'], ['s_stein_aktiv' => '1', 's_stein_api_key' => 'x', 's_stein_api_key__leeren' => '1']);
$check('Leeren hat Vorrang vor Eingabe', ($werte['stein_api_key'] ?? null) === '');

// Merkwert speichern: eigene Gruppe, damit er nie in der Maske landet
$GLOBALS['execs'] = [];
state_save('stein_letzter_abruf', '1800000600');
[$sql, $p] = $GLOBALS['execs'][0];
$check('Merkwert in der internen Gruppe', $p === ['stein_letzter_abruf', '1800000600', '_intern', 'stein_letzter_abruf']);
$check('bestehende Zeile wird in die interne Gruppe verschoben', str_contains($sql, 'sgroup = VALUES(sgroup)'));

// Die Einstellungsseite nutzt die neue Funktion und nicht mehr alle Einstellungen
$seite = (string)file_get_contents($app . '/src/pages/admin/settings.php');
$check('Seite speichert nur die Gruppe', str_contains($seite, 'settings_from_post($gruppen[$group], $_POST)')
    && !str_contains($seite, 'settings_all()'));
foreach (['stein.php', 'divera_vehicles.php'] as $datei) {
    $quelltext = (string)file_get_contents($app . '/src/lib/' . $datei);
    $check("$datei: Merkwerte über state_save", !preg_match("/setting_save\\('(stein|divera)_(letzter|pause|offene|status_letzter|stamm_letzter)/", $quelltext));
}

echo "$ok bestanden, $fail fehlgeschlagen\n";
