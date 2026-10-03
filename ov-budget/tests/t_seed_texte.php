<?php
declare(strict_types=1);
// Beschriftungen der Einstellungen aus seed.sql lesen (Wanderung 006)
require dirname(__DIR__) . '/src/cli/migrate.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$sql = <<<'SQL'
INSERT IGNORE INTO settings (skey, svalue, label, hint, stype, sgroup, sort_order) VALUES
('ov_name','THW Ortsverband','Name des Ortsverbands','','text','Allgemein',10),
('stein_auto_anlegen','0','Unbekannte Fahrzeuge selbst anlegen','Nur mit erkennbarem Kennzeichen','bool','Stein.APP',60),
('mit_apostroph','x','Das ist Peters'' Feld','Hinweis mit '' Apostroph','text','Allgemein',20);
SQL;

$rows = ovb_seed_settings($sql);
$check('drei Zeilen gelesen', count($rows) === 3);
$check('Schlüssel', array_column($rows, 'skey') === ['ov_name', 'stein_auto_anlegen', 'mit_apostroph']);
$check('Beschriftung', $rows[1]['label'] === 'Unbekannte Fahrzeuge selbst anlegen');
$check('Hinweis', $rows[1]['hint'] === 'Nur mit erkennbarem Kennzeichen');
$check('Typ und Gruppe', $rows[1]['stype'] === 'bool' && $rows[1]['sgroup'] === 'Stein.APP');
$check('Reihenfolge als Zahl', $rows[1]['sort'] === 60);
$check('leerer Hinweis', $rows[0]['hint'] === '');
$check('doppelter Apostroph wird einer', $rows[2]['label'] === "Das ist Peters' Feld"
    && $rows[2]['hint'] === "Hinweis mit ' Apostroph");
$check('der Wert selbst wird nicht gelesen', !array_key_exists('svalue', $rows[0]));

// Andere INSERT-Zeilen dürfen nicht mitgelesen werden
$fremd = <<<'SQL'
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES
('kategorie','Werkzeug','werkzeug','#123456',20);
SQL;
$check('Listeneinträge werden übergangen', ovb_seed_settings($fremd) === []);
$check('leerer Text', ovb_seed_settings('') === []);

// Die echte Datei
$echt = ovb_seed_settings((string)file_get_contents(dirname(__DIR__) . '/sql/seed.sql'));
$keys = array_column($echt, 'skey');
$check('alle Einstellungen der Anwendung', count($echt) === 151);
$check('keine Dubletten', count($keys) === count(array_unique($keys)));
$check('Fahrzeugeinstellung dabei', in_array('fahrzeug_user_darf_melden', $keys, true));
$check('Stein-Einstellung dabei', in_array('stein_intervall_minuten', $keys, true));
$check('Webhook-Einstellung dabei', in_array('stein_webhook_secret', $keys, true));
$check('Mitschnitt-Einstellung dabei', in_array('stein_debug', $keys, true));
foreach ($echt as $r) {
    if ($r['label'] === '' || !in_array($r['stype'], ['text', 'textarea', 'bool', 'number', 'password', 'color', 'select'], true)) {
        $fail++;
        echo "FAIL: unbrauchbare Zeile {$r['skey']}\n";
        break;
    }
}
$ok++;

echo "$ok bestanden, $fail fehlgeschlagen\n";
