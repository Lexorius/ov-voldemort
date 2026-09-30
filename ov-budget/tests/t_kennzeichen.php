<?php
declare(strict_types=1);
// Kennzeichenerkennung für das automatische Anlegen aus der Stein.APP
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

/* ---- THW-Kennzeichen ---- */
$check('THW mit Bindestrich', stein_plate_in_text('GKW 1 THW-84321') === 'THW-84321');
$check('THW mit Leerzeichen', stein_plate_in_text('THW 84321') === 'THW-84321');
$check('THW ohne Trenner', stein_plate_in_text('THW84321') === 'THW-84321');
$check('THW klein geschrieben', stein_plate_in_text('thw-84321') === 'THW-84321');
$check('THW dreistellig', stein_plate_in_text('THW-123') === 'THW-123');
$check('THW zu kurz zählt nicht', stein_plate_in_text('THW-12') === null);

/* ---- Gewöhnliche Kennzeichen ---- */
$check('Stadt mit einem Buchstaben', stein_plate_in_text('MTW B-XY 1234') === 'B-XY 1234');
$check('Stadt mit drei Buchstaben', stein_plate_in_text('OHZ-AB 12') === 'OHZ-AB 12');
$check('ohne Leerzeichen vor der Zahl', stein_plate_in_text('HH-AB123') === 'HH-AB 123');
$check('Elektro-Kennzeichen', stein_plate_in_text('M-EA 1E') === 'M-EA 1E');
$check('Umlaut im Kreis', stein_plate_in_text('LÖ-AB 12') === 'LÖ-AB 12');
$check('Kennzeichen mitten im Text', stein_plate_in_text('Bus (HB-XY 456) Kleinbus') === 'HB-XY 456');

/* ---- Was kein Kennzeichen ist ---- */
$check('Fahrzeugname ohne Kennzeichen', stein_plate_in_text('GKW 1') === null);
$check('Funkrufname', stein_plate_in_text('Heros Musterstadt 24/51') === null);
$check('Anhänger', stein_plate_in_text('Anhänger Lichtmast') === null);
$check('Aggregat mit Zahl', stein_plate_in_text('Stromerzeuger 50 kVA') === null);
$check('leerer Text', stein_plate_in_text('') === null && stein_plate_in_text('   ') === null);
$check('Fachgruppe mit Bindestrich', stein_plate_in_text('FGr N-Versorgung') === null);
$check('Typbezeichnung MLW-IV', stein_plate_in_text('MLW-IV 2') === null);
$check('Typbezeichnung LKW-K', stein_plate_in_text('LKW-K 7') === null);
$check('Typbezeichnung GKW-N', stein_plate_in_text('GKW-N 1') === null);
$check('echtes Kennzeichen neben Typbezeichnung',
    stein_plate_in_text('MLW-IV 2 · HB-XY 456') === 'HB-XY 456');
$check('Kleinschreibung zählt auch als Typbezeichnung', stein_plate_in_text('mlw-iv 2') === null);

/* ---- Ganzes Feld ist ein Kennzeichen (Feld "THW-Kennzeichen" der Stein.APP) ---- */
$check('THW mit Leerzeichen bleibt, wie es ist', stein_plate_exact('THW 99020') === 'THW 99020');
$check('THW mit Bindestrich bleibt', stein_plate_exact('THW-99020') === 'THW-99020');
$check('doppelte Leerzeichen werden eins', stein_plate_exact('THW  99020') === 'THW 99020');
$check('Rand-Leerzeichen weg', stein_plate_exact('  THW 99020 ') === 'THW 99020');
$check('gewöhnliches Kennzeichen als ganzer Wert', stein_plate_exact('HB-XY 456') === 'HB-XY 456');
$check('Text drumherum zählt nicht', stein_plate_exact('MTW THW 99020') === null);
$check('Fahrzeugname ist kein Kennzeichen', stein_plate_exact('MTW Zugtrupp (Sprinter)') === null);
$check('Funkrufname ist kein Kennzeichen', stein_plate_exact('21/10') === null);
$check('leeres Feld', stein_plate_exact('') === null);

/* ---- Ganzes Asset ---- */
$asset = ['id' => 'a1', 'label' => 'GKW 1', 'name' => '', 'radioName' => 'Heros 24/51', 'comment' => ''];
$check('Asset ohne Kennzeichen', stein_plate($asset) === null);
$check('Kennzeichen im eigenen Feld',
    stein_plate($asset + ['licensePlate' => 'THW-84321']) === 'THW-84321');
$check('Feld schlägt Text',
    stein_plate(array_merge($asset, ['label' => 'GKW HB-XY 1', 'numberPlate' => 'THW-999'])) === 'THW-999');
$check('leeres Feld wird übergangen',
    stein_plate(array_merge($asset, ['licensePlate' => '  ', 'label' => 'GKW THW-84321'])) === 'THW-84321');
$check('Kennzeichen in der Bezeichnung',
    stein_plate(array_merge($asset, ['label' => 'GKW 1 (THW-84321)'])) === 'THW-84321');
$check('Kennzeichen im Namen',
    stein_plate(array_merge($asset, ['name' => 'HB-XY 456'])) === 'HB-XY 456');
$check('Kennzeichen in der Bemerkung',
    stein_plate(array_merge($asset, ['comment' => 'steht auf THW-84321'])) === 'THW-84321');
$check('Reihenfolge: Bezeichnung vor Bemerkung',
    stein_plate(array_merge($asset, ['label' => 'THW-111', 'comment' => 'THW-222'])) === 'THW-111');
$check('sehr langes Feld wird gekürzt',
    mb_strlen((string)stein_plate($asset + ['plate' => str_repeat('X', 40)])) === 20);

/* ---- So sieht ein Fahrzeug in der Stein.APP aus ---- */
$mtw = ['id' => 206, 'label' => 'MTW Zugtrupp (Sprinter)', 'name' => 'THW 99020',
        'radioName' => '21/10', 'issi' => '7120087', 'status' => 'ready', 'comment' => ''];
$check('Kennzeichen aus dem Feld "name"', stein_plate($mtw) === 'THW 99020');
$check('Bezeichnung mit Kennzeichen darin',
    stein_plate(['label' => 'MTW (THW 99020)', 'name' => 'Zugtrupp']) === 'THW-99020');
$check('eigenes Feld hat Vorrang vor name',
    stein_plate($mtw + ['licensePlate' => 'THW-11111']) === 'THW-11111');
$check('name ohne Kennzeichen',
    stein_plate(['label' => 'Anhänger Lichtmast', 'name' => 'Lichtmast']) === null);

echo "$ok bestanden, $fail fehlgeschlagen\n";
