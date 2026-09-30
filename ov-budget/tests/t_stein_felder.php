<?php
declare(strict_types=1);
/*
 * Felder aus der Stein.APP, Kennzeichenübernahme und Webhook.
 * Die Feldnamen stammen aus dem Asset-Schema der Spezifikation
 * (https://stein.app/api/api/doc/api-doc.yaml).
 */
session_start();

$GLOBALS['settings'] = [
    'waehrung' => 'EUR',
    'stein_aktiv' => '1', 'stein_api_key' => 'geheim', 'stein_bu_id' => '21',
    'stein_intervall_minuten' => '10',
    'stein_webhook_secret' => 'sehr-geheim',
    // Einstellungen stehen vor dem ersten Zugriff fest (settings_all merkt sie sich).
    // Der letzte Abruf war gerade eben – so löst der Webhook keinen echten Aufruf aus.
    'stein_letzter_abruf' => (string)time(),
];
require __DIR__ . '/stub_db.php';

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/stein.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---- Asset nach Spezifikation ---- */
$asset = [
    'buId' => 21, 'groupId' => 3, 'id' => 206, 'label' => 'GKW 1', 'name' => 'Bergungsgruppe',
    'status' => 'ready', 'comment' => '', 'category' => 'K (A)', 'deleted' => false,
    'lastModified' => '2026-09-18T08:30:00+02:00', 'created' => '2020-01-01T00:00:00+01:00',
    'lastModifiedBy' => 'M. Muster', 'radioName' => 'Heros Musterstadt 24/51', 'issi' => '1234567',
    'sortOrder' => 10, 'operationReservation' => false,
    'huValidUntil' => '2026-12-01T00:00:00+01:00', 'spValidUntil' => '2027-03-01T00:00:00+01:00',
];

/* ---- Ja/Nein-Felder ---- */
$check('Einsatzvorbehalt nein', stein_value_text('operationReservation', false) === 'nein');
$check('Einsatzvorbehalt ja', stein_value_text('operationReservation', true) === 'ja');
$check('gelöscht nein', stein_value_text('deleted', false) === 'nein');
$check('gelöscht ja', stein_value_text('deleted', true) === 'ja');
$check('gelöscht fehlend zählt als nein', stein_value_text('deleted', null) === 'nein');

/* ---- Löschung landet im Journal ---- */
$d = stein_diff($asset, array_merge($asset, ['deleted' => true]));
$check('Löschung wird erkannt', count($d) === 1 && $d[0]['feld'] === 'deleted'
    && $d[0]['alt'] === 'nein' && $d[0]['neu'] === 'ja');
$check('Beschriftung der Löschung', $d[0]['label'] === 'In der Stein.APP gelöscht');
$check('unveränderter Stand bleibt still', stein_diff($asset, $asset) === []);
$check('ISSI mit Erklärung', STEIN_FELDER['issi'] === 'ISSI (Funkrufkennung)');

/* ---- Kennzeichen übernehmen ---- */
$leer = ['funkrufname' => '', 'kennzeichen' => ''];
$daten = stein_vehicle_data(array_merge($asset, ['label' => 'GKW 1 THW-84321']), $leer);
$check('Kennzeichen aus der Bezeichnung', ($daten['kennzeichen'] ?? null) === 'THW-84321');
$daten = stein_vehicle_data(array_merge($asset, ['comment' => 'Kennzeichen HB-XY 456']), $leer);
$check('Kennzeichen aus der Bemerkung', ($daten['kennzeichen'] ?? null) === 'HB-XY 456');
$daten = stein_vehicle_data($asset, $leer);
$check('ohne Kennzeichen kein Feld', !array_key_exists('kennzeichen', $daten));
$daten = stein_vehicle_data(array_merge($asset, ['label' => 'GKW THW-84321']), ['kennzeichen' => 'THW-11111']);
$check('eigenes Kennzeichen bleibt', !array_key_exists('kennzeichen', $daten));
$check('Rohdaten enthalten alle Felder',
    count(json_decode($daten['stein_daten'], true)) === count($asset));

/* ---- Kennzeichen zieht nach, wenn es von der Stein.APP stammt ---- */
$mitKennzeichen = static fn(string $wert) => array_merge($asset, ['name' => $wert]);
$fahrzeug = static fn(string $kennzeichen, array $alterStand) => [
    'funkrufname' => 'x', 'kennzeichen' => $kennzeichen,
    'stein_daten' => json_encode($alterStand),
];

// Bisher kam das Kennzeichen von dort und wurde in der Stein.APP geändert
$daten = stein_vehicle_data($mitKennzeichen('THW 99021'), $fahrzeug('THW 99020', $mitKennzeichen('THW 99020')));
$check('geändertes Kennzeichen zieht nach', ($daten['kennzeichen'] ?? null) === 'THW 99021');

// Von Hand eingetragen: bleibt stehen
$daten = stein_vehicle_data($mitKennzeichen('THW 99021'), $fahrzeug('THW 11111', $mitKennzeichen('THW 99020')));
$check('von Hand eingetragenes bleibt', !array_key_exists('kennzeichen', $daten));

// Unverändert: kein unnötiges Schreiben
$daten = stein_vehicle_data($mitKennzeichen('THW 99020'), $fahrzeug('THW 99020', $mitKennzeichen('THW 99020')));
$check('gleiches Kennzeichen wird nicht erneut geschrieben', !array_key_exists('kennzeichen', $daten));

// Noch nichts eingetragen
$daten = stein_vehicle_data($mitKennzeichen('THW 99020'), $fahrzeug('', []));
$check('leeres Feld wird gefüllt', ($daten['kennzeichen'] ?? null) === 'THW 99020');
$check('Beschriftung nennt das THW-Kennzeichen', STEIN_FELDER['name'] === 'Name / THW-Kennzeichen');

/* ---- Webhook ---- */
[$code, $meldung] = stein_webhook_handle('falsch', '{}');
$check('falsches Secret abgewiesen', $code === 403 && str_contains($meldung, 'Secret'));
[$code, $meldung] = stein_webhook_handle('', '{}');
$check('leeres Secret abgewiesen', $code === 403);

$nutzlast = json_encode([
    'meta' => ['eventId' => 'STEIN-1742652612538', 'timestamp' => '2026-09-18T15:10:12+02:00'],
    'items' => [
        ['type' => 'bu', 'action' => 'update', 'id' => 10, 'url' => 'https://stein.app/api/api/ext/bu/10'],
    ],
]);
[$code, $meldung] = stein_webhook_handle('sehr-geheim', $nutzlast);
$check('Meldung ohne Fahrzeug löst keinen Abruf aus', $code === 200 && str_contains($meldung, 'kein Abruf'));

$mitFahrzeug = json_encode([
    'meta' => ['eventId' => 'x'],
    'items' => [
        ['type' => 'asset', 'action' => 'update', 'id' => 206, 'url' => 'https://stein.app/api/api/ext/assets/206',
         'parentId' => 10, 'parentType' => 'bu'],
    ],
]);
[$code, $meldung] = stein_webhook_handle('sehr-geheim', $mitFahrzeug);
$check('kurz nacheinander wird nicht erneut abgerufen', $code === 200 && str_contains($meldung, 'aktuell'));
$check('dabei kein Abruf protokolliert', $GLOBALS['inserts'] === []);

echo "$ok bestanden, $fail fehlgeschlagen\n";
