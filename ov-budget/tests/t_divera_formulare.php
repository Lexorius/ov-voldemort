<?php
declare(strict_types=1);
/*
 * Wünsch dir was gegen die Divera-Formular-Schnittstelle
 * (reporttypes / reports, siehe divera_fixture.php).
 */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system',
    'divera_personal_key' => 'persoenlich', 'mwst_satz' => '19'];
require __DIR__ . '/stub_db.php';
require __DIR__ . '/divera_fixture.php';
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';

// Die echte divera.php ohne ihre Anfragefunktion laden
@mkdir(__DIR__ . '/alt');
$quelle = (string)file_get_contents($app . '/src/lib/divera.php');
$quelle = (string)preg_replace('/function divera_request\(.*?\n}\n/s', '', $quelle, 1);
file_put_contents(__DIR__ . '/alt/divera_neu_ohne_request.php', $quelle);

$GLOBALS['anfragen'] = [];
$GLOBALS['eintraege'] = fixture_eintraege();
function divera_request(string $path, array $query = [], ?string $key = null): array {
    $GLOBALS['anfragen'][] = [$path, $query, $key];
    if (preg_match('#/v2/reporttypes/(\d+)/reports$#', $path)) {
        return fixture_seite($GLOBALS['eintraege'], (int)($query['offset'] ?? 0));
    }
    if (preg_match('#/v2/reporttypes/(\d+)$#', $path)) {
        return ['success' => true, 'data' => fixture_formulare()['data']['items']['654']];
    }
    if (str_ends_with($path, '/v2/reporttypes')) {
        return fixture_formulare();
    }
    throw new RuntimeException('HTTP 404 für ' . $path);
}
require __DIR__ . '/alt/divera_neu_ohne_request.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Formulare ---------- */
$formulare = divera_fetch_forms();
$check('Vorgabepfad reporttypes', $GLOBALS['anfragen'][0][0] === '/v2/reporttypes');
$check('mit dem persönlichen Schlüssel', $GLOBALS['anfragen'][0][2] === 'persoenlich');
$check('zwei Formulare', count($formulare) === 2);
$check('Name und id', $formulare[0]['id'] === '654' && $formulare[0]['name'] === 'Wünsch dir was');

/* ---------- Felder eines Eintrags ---------- */
$GLOBALS['anfragen'] = [];
$e = divera_fetch_entries('654');
$check('Vorgabepfad reports', $GLOBALS['anfragen'][0][0] === '/v2/reporttypes/654/reports');
$check('zwei Einträge', count($e) === 2);
$f = $e[0]['fields'];
$check('Bezeichnung', ($f['Bezeichnung'] ?? null) === 'Tauchpumpe TP 8/1');
$check('Anzahl', ($f['Anzahl'] ?? null) === '2');
$check('Betrag', ($f['Nettobetrag'] ?? null) === '1249,90');
$check('Auswahl: Text statt id', ($f['Dringlichkeit'] ?? null) === 'hoch');
$check('Liste: Text statt id', ($f['Fachgruppe'] ?? null) === 'Bergungsgruppe');
$check('Mehrfachauswahl mit Komma', ($f['Einsatzzweck'] ?? null) === 'Einsatz, Ausbildung');
$check('leere Checkbox', ($f['Nice to have'] ?? null) === '');
$check('Datum', ($f['Benötigt bis'] ?? null) === '2026-11-30');
$check('Überschrift fällt weg', !array_key_exists('Angaben zum Wunsch', $f));
$check('keine Technikfelder', !array_key_exists('cluster_id', $f) && !array_key_exists('id', $f));
$f2 = $e[1]['fields'];
$check('Mehrfachauswahl als JSON', ($f2['Einsatzzweck'] ?? null) === 'Jugend');
$check('angehakte Checkbox', ($f2['Nice to have'] ?? null) === 'ja');
$check('Eintrags-id', $e[0]['id'] === '9001');
$check('Anhänge gezählt', $e[0]['anhaenge'] === 1 && $e[1]['anhaenge'] === 0);
$check('Zeitpunkt', (int)$e[0]['date'] === 1758200000);

/* ---------- Blättern ---------- */
$viele = [];
for ($i = 1; $i <= 120; $i++) {
    $viele[] = fixture_eintrag((string)(10000 + $i), ['bez' => 'Artikel ' . $i, 'anz' => '1']);
}
$GLOBALS['eintraege'] = $viele;
$GLOBALS['anfragen'] = [];
$alle = divera_fetch_entries('654');
$check('alle 120 Einträge', count($alle) === 120);
$check('drei Seiten', count($GLOBALS['anfragen']) === 3);
$check('mit offset 50 und 100', ($GLOBALS['anfragen'][1][1]['offset'] ?? null) === 50
    && ($GLOBALS['anfragen'][2][1]['offset'] ?? null) === 100);
$check('keine Dubletten', count(array_unique(array_column($alle, 'id'))) === 120);
$GLOBALS['eintraege'] = array_slice($viele, 0, 50);
$GLOBALS['anfragen'] = [];
$check('genau 50: eine Seite reicht', count(divera_fetch_entries('654')) === 50 && count($GLOBALS['anfragen']) === 1);
$GLOBALS['eintraege'] = [];
$check('leeres Formular', divera_fetch_entries('654') === []);
$GLOBALS['eintraege'] = fixture_eintraege();

/* ---------- Felddefinition und Vorschlag ---------- */
$namen = divera_fetch_form_fields('654');
$check('Felder aus der Definition', in_array('Bezeichnung', $namen, true) && !in_array('Angaben zum Wunsch', $namen, true));
$vorschlag = divera_suggest_map($namen);
$check('Vorschlag Bezeichnung', ($vorschlag['bezeichnung'] ?? '') === 'Bezeichnung');
$check('Vorschlag Anzahl', ($vorschlag['anzahl'] ?? '') === 'Anzahl');
$check('Vorschlag Nettobetrag als Stückpreis', ($vorschlag['netto_einzel'] ?? '') === 'Nettobetrag');
$check('Vorschlag Dringlichkeit', ($vorschlag['dringlichkeit'] ?? '') === 'Dringlichkeit');
$check('Vorschlag Fachgruppe', ($vorschlag['fachgruppe'] ?? '') === 'Fachgruppe');
$check('Vorschlag Nice to have', ($vorschlag['nice_to_have'] ?? '') === 'Nice to have');
$check('Vorschlag Datum', ($vorschlag['benoetigt_bis'] ?? '') === 'Benötigt bis');
$check('Vorschlag Begründung', ($vorschlag['begruendung'] ?? '') === 'Begründung');
$check('jedes Feld nur einmal vergeben', count($vorschlag) === count(array_unique($vorschlag)));

/* ---------- Vom Eintrag zum Wunsch ---------- */
$form = ['form_id' => '654', 'default_status_id' => null, 'default_fachgruppe_id' => null];
$w = divera_entry_to_wish($e[0], $vorschlag, $form);
$check('Wunsch: Bezeichnung', $w['bezeichnung'] === 'Tauchpumpe TP 8/1');
$check('Wunsch: Anzahl', (float)$w['anzahl'] === 2.0);
$check('Wunsch: Einzelpreis', abs($w['netto_einzel'] - 1249.90) < 0.001);
$check('Wunsch: Gesamt gerechnet', abs($w['netto_gesamt'] - 2499.80) < 0.001);
$check('Wunsch: Frist', $w['benoetigt_bis'] === '2026-11-30');
$check('Wunsch: Begründung', $w['begruendung'] === 'Alte Pumpe ist defekt.');
$check('Wunsch: nicht zugeordnetes Feld bleibt erhalten', str_contains($w['beschreibung'], 'Einsatzzweck: Einsatz, Ausbildung'));
$check('Wunsch: Hinweis auf den Anhang', str_contains($w['beschreibung'], '1 Datei(en)'));
$check('Wunsch: Herkunft', $w['source'] === 'divera' && $w['divera_entry_id'] === '9001');
$w2 = divera_entry_to_wish($e[1], $vorschlag, $form);
$check('Wunsch 2: nice to have', $w2['nice_to_have'] === 1);
$check('Wunsch 2: Punkt als Dezimalzeichen', abs($w2['netto_einzel'] - 39.50) < 0.001 && abs($w2['netto_gesamt'] - 474.0) < 0.001);
$check('Wunsch 2: ohne Frist', $w2['benoetigt_bis'] === null);
$check('Wunsch 2: kein Anhang-Hinweis', !str_contains($w2['beschreibung'], 'Datei(en)'));

/* ---------- Anderes Format bleibt lesbar ---------- */
$alt = divera_entry_from_row(['id' => 'x1', 'felder' => ['Bezeichnung' => 'Leiter', 'Anzahl' => 3]]);
$check('eigenes Format: flach gelesen', ($alt['fields']['felder.Bezeichnung'] ?? null) === 'Leiter');
$check('Liste ohne field-Objekte: kein Divera-Format',
    divera_report_fields(['fields' => [['name' => 'a', 'value' => 1]]]) === null);

echo "$ok bestanden, $fail fehlgeschlagen\n";
