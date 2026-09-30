<?php
declare(strict_types=1);
/* Rufnummern: internationale Form, tel:- und mailto:-Verweise */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'telefon_landesvorwahl' => '+49'];
require __DIR__ . '/stub_db.php';
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/contacts.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
};

/* ---------- E.164 ---------- */
$ist('Handy mit führender 0', phone_e164('0151 12345678'), '+4915112345678');
$ist('Festnetz mit Trennzeichen', phone_e164('0201 / 123 45-67'), '+492011234567');
$ist('Schrägstrich trennt die Vorwahl', phone_e164('0201/1234567'), '+492011234567');
$ist('mit 0049', phone_e164('0049 201 1234567'), '+492011234567');
$ist('schon international', phone_e164('+49 201 1234567'), '+492011234567');
$ist('anderes Land bleibt', phone_e164('+43 660 1234567'), '+436601234567');
$ist('Leerzeichen und Klammern', phone_e164(' (0)201 1234 567 '), '+492011234567');
$ist('(0) hinter der Ländervorwahl entfällt', phone_e164('+49 (0)201 1234567'), '+492011234567');
$ist('Klammerzusatz fällt weg', phone_e164('0201 1234567 (privat)'), '+492011234567');
$ist('zweite Nummer abgeschnitten', phone_e164('0201 1234567, 0151 999888'), '+492011234567');
$ist('mit Schrägstrich getrennte zweite Nummer', phone_e164('0201 1234567 / 0151 999888'), '+492011234567');
$ist('„oder" trennt', phone_e164('0201 1234567 oder 0151 999888'), '+492011234567');
$ist('00 bleibt die Auslandskennzahl', phone_e164('0020 1234567'), '+201234567');
$ist('Durchwahl bleibt', phone_e164('0201 1234-56'), '+49201123456');
$ist('ohne Vorwahl wie eingegeben', phone_e164('123456'), '123456');
$ist('Notruf bleibt', phone_e164('112'), '112');
$ist('leer', phone_e164(''), null);
$ist('nur Text', phone_e164('bitte über das Büro'), null);
$ist('zu kurz', phone_e164('12'), null);
$ist('zu lang', phone_e164('+49 1234567890123456'), null);
$ist('andere Landesvorwahl einstellbar', phone_e164('0660 1234567', '+43'), '+436601234567');
$ist('Landesvorwahl ohne Plus', phone_e164('0151 222333', '49'), '+49151222333');
$ist('unsinnige Landesvorwahl fällt auf +49 zurück', phone_e164('0151 222333', 'DE'), '+49151222333');

/* ---------- Anzeige ---------- */
$ist('Mobil gruppiert', phone_human('015112345678'), '+49 151 12345678');
$ist('Festnetz ohne geratene Ortsvorwahl', phone_human('0201 1234567'), '+49 2011234567');
$ist('Ausland ohne Gruppierung', phone_human('+43 660 1234567'), '+436601234567');
$ist('unlesbares bleibt Text', phone_human('über das Büro'), 'über das Büro');
$ist('leer bleibt leer', phone_human(''), '');
$ist('zweimal angewandt bleibt gleich', phone_human(phone_human('0151 12345678')), phone_human('0151 12345678'));

/* ---------- HTML ---------- */
$h = phone_html('0151 12345678');
$check('tel-Verweis', str_contains($h, 'href="tel:+4915112345678"') && str_contains($h, '+49 151 12345678'));
$ist('ohne Nummer ein Strich', phone_html(''), '–');
$ist('eigener Ersatztext', phone_html(null, ''), '');
$check('Text ohne Nummer nicht verlinkt', phone_html('über das Büro') === 'über das Büro');
$check('HTML maskiert', !str_contains(phone_html('<b>0201 1</b>'), '<b>'));
$check('E-Mail verlinkt', str_contains(email_html('a@b.de'), 'href="mailto:a@b.de"'));
$ist('E-Mail leer', email_html(null), '–');
$check('E-Mail maskiert', str_contains(email_html('a"x@b.de'), '&quot;'));

/* ---------- Speichern ---------- */
$_POST = ['nachname' => 'Beispiel', 'telefon' => '0201 / 123 45 67', 'mobil' => '0151-99 88 77', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
[$id, $errors] = contact_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$check('ohne Fehler gespeichert', $errors === [] && $GLOBALS['inserts'][0][0] === 'contacts');
$ist('Telefon normalisiert', $d['telefon'], '+49 2011234567');
$ist('Mobil normalisiert', $d['mobil'], '+49 151 998877');
$_POST = ['nachname' => 'Ohne', 'telefon' => '', 'mobil' => 'Zentrale fragen', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
contact_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$ist('leer bleibt leer', $d['telefon'], '');
$ist('Text bleibt erhalten', $d['mobil'], 'Zentrale fragen');

echo "$ok bestanden, $fail fehlgeschlagen\n";
