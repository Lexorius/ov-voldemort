<?php
declare(strict_types=1);

/*
 * Tanken und Verbrauch: Tankquittungen je Fahrzeug, daraus der Verbrauch
 * je 100 km. Beträge sind freiwillig – mit einer Tankkarte (DKV) kommt die
 * Rechnung gesammelt, die Quittung nennt oft nur Liter und Kilometerstand.
 *
 * Gerechnet wird von Volltankung zu Volltankung: alles, was dazwischen
 * nachgefüllt wurde, zählt zur Strecke bis zur nächsten vollen Tankung.
 * Teiltankungen allein ergeben keinen Verbrauch, AdBlue zählt nie mit.
 * Strom (Laden eines E-Fahrzeugs) wird in kWh geführt und genauso
 * gerechnet – von Vollladung zu Vollladung, getrennt vom Kraftstoff.
 */

const TANK_ARTEN = ['kraftstoff' => 'Kraftstoff', 'strom' => 'Strom (Laden)', 'adblue' => 'AdBlue'];
/** Arten, für die ein Verbrauch je 100 km gerechnet wird, mit ihrer Einheit */
const TANK_VERBRAUCH_ARTEN = ['kraftstoff' => 'l', 'strom' => 'kWh'];
/** So weit darf eine Tankung vom Mittel abweichen, bevor sie als auffällig gilt (Prozent) */
const TANK_AUFFAELLIG_PROZENT = 25;
/** Mehr Liter passen in keinen Tank des OV – und mehr kWh in keinen Akku */
const TANK_LITER_MAX = 2000;
/** Ab so vielen Volltankungen mit Verbrauch wird Auffälliges markiert */
const TANK_AUFFAELLIG_AB = 3;

/** Einheit einer Tankung: Liter, bei Strom kWh. Reine Funktion. */
function tank_einheit(string $art): string
{
    return TANK_VERBRAUCH_ARTEN[$art] ?? 'l';
}

/* ==================================================================== */
/*  Lesen                                                                 */
/* ==================================================================== */

function tank_find(int $id): ?array
{
    return db_row(
        'SELECT t.*, u.display_name AS erfasst_von
         FROM vehicle_tankungen t
         LEFT JOIN users u ON u.id = t.user_id
         WHERE t.id = ?',
        [$id]
    );
}

/** Alle Tankungen eines Fahrzeugs, älteste zuerst – so rechnet die Auswertung */
function tank_query(int $vehicleId): array
{
    return db_all(
        'SELECT t.*, u.display_name AS erfasst_von,
                k.nummer AS karte_nummer, ka.label AS karte_anbieter,
                (SELECT COUNT(*) FROM vehicle_files f WHERE f.tank_id = t.id) AS belege
         FROM vehicle_tankungen t
         LEFT JOIN users u ON u.id = t.user_id
         LEFT JOIN tankkarten k ON k.id = t.tankkarte_id
         LEFT JOIN list_items ka ON ka.id = k.anbieter_id
         WHERE t.vehicle_id = ?
         ORDER BY t.datum, t.km_stand, t.id',
        [$vehicleId]
    );
}

/** Auswertung eines Fahrzeugs in einem Rutsch */
function tank_uebersicht(int $vehicleId): array
{
    return tank_auswerten(tank_query($vehicleId));
}

/* ==================================================================== */
/*  Rechnen – reine Funktionen                                            */
/* ==================================================================== */

/** Reihenfolge, in der gerechnet wird: Datum, dann Kilometerstand, dann Eingabe */
function tank_sortieren(array $tankungen): array
{
    usort($tankungen, static function (array $a, array $b): int {
        return [(string)$a['datum'], $a['km_stand'] === null ? PHP_INT_MAX : (int)$a['km_stand'], (int)$a['id']]
            <=> [(string)$b['datum'], $b['km_stand'] === null ? PHP_INT_MAX : (int)$b['km_stand'], (int)$b['id']];
    });
    return $tankungen;
}

/**
 * Verbrauch einer Art (Kraftstoff oder Strom) über die sortierten
 * Tankungen. Schreibt strecke, verbrauch und auffaellig in die Einträge
 * dieser Art und gibt die Summen zurück. Reine Funktion.
 */
function tank_rechnen(array &$sortiert, string $art): array
{
    $kmVoll = null;        // Stand der letzten Volltankung
    $mengeSeit = 0.0;      // nachgetankt seit der letzten Volltankung
    $mengeGezaehlt = 0.0;  // Menge, die in einen Verbrauch eingegangen ist
    $strecke = 0;
    $menge = 0.0;
    $betrag = 0.0;
    $betragFehlt = 0;
    $anzahl = 0;
    $jahre = [];
    $punkte = [];

    foreach ($sortiert as &$t) {
        if ((string)$t['art'] !== $art) {
            continue;
        }
        $anzahl++;
        $jahr = substr((string)$t['datum'], 0, 4);
        $jahre[$jahr] ??= ['anzahl' => 0, 'menge' => 0.0, 'betrag' => 0.0, 'betrag_fehlt' => 0, 'strecke' => 0, 'menge_gezaehlt' => 0.0, 'verbrauch' => null];
        $jahre[$jahr]['anzahl']++;
        $jahre[$jahr]['menge'] += (float)$t['liter'];
        $menge += (float)$t['liter'];
        if ($t['betrag'] !== null) {
            $betrag += (float)$t['betrag'];
            $jahre[$jahr]['betrag'] += (float)$t['betrag'];
        } else {
            $betragFehlt++;
            $jahre[$jahr]['betrag_fehlt']++;
        }

        $km = $t['km_stand'] !== null ? (int)$t['km_stand'] : null;
        if ($kmVoll === null) {
            // Noch kein Bezugspunkt – erst eine Volltankung mit Kilometerstand setzt ihn
            if ((int)$t['voll'] === 1 && $km !== null) {
                $kmVoll = $km;
                $mengeSeit = 0.0;
            }
            continue;
        }

        $mengeSeit += (float)$t['liter'];
        if ((int)$t['voll'] === 1 && $km !== null) {
            if ($km > $kmVoll) {
                $t['strecke'] = $km - $kmVoll;
                $t['verbrauch'] = $mengeSeit / $t['strecke'] * 100;
                $mengeGezaehlt += $mengeSeit;
                $strecke += $t['strecke'];
                $jahre[$jahr]['strecke'] += $t['strecke'];
                $jahre[$jahr]['menge_gezaehlt'] += $mengeSeit;
                $punkte[] = ['id' => (int)$t['id'], 'datum' => (string)$t['datum'], 'verbrauch' => $t['verbrauch'],
                    'strecke' => $t['strecke'], 'liter' => $mengeSeit, 'auffaellig' => false];
            }
            // Kleinerer oder gleicher Stand: neuer Bezug, gerechnet wird nichts
            $kmVoll = $km;
            $mengeSeit = 0.0;
        }
    }
    unset($t);

    $verbrauch = $strecke > 0 ? $mengeGezaehlt / $strecke * 100 : null;
    foreach ($jahre as &$j) {
        $j['verbrauch'] = $j['strecke'] > 0 ? $j['menge_gezaehlt'] / $j['strecke'] * 100 : null;
        unset($j['menge_gezaehlt']);
    }
    unset($j);
    krsort($jahre);

    // Ausreißer: nur, wenn genug Volltankungen da sind, um ein Mittel zu haben
    if ($verbrauch !== null && count($punkte) >= TANK_AUFFAELLIG_AB) {
        $auffaellig = [];
        foreach ($punkte as &$p) {
            $p['auffaellig'] = abs($p['verbrauch'] - $verbrauch) / $verbrauch * 100 > TANK_AUFFAELLIG_PROZENT;
            if ($p['auffaellig']) {
                $auffaellig[$p['id']] = true;
            }
        }
        unset($p);
        foreach ($sortiert as &$t) {
            if (isset($auffaellig[(int)$t['id']])) {
                $t['auffaellig'] = true;
            }
        }
        unset($t);
    }

    return [
        'anzahl'       => $anzahl,
        'menge'        => $menge,
        'betrag'       => $betrag,
        'betrag_fehlt' => $betragFehlt,
        'strecke'      => $strecke,
        'verbrauch'    => $verbrauch,
        'punkte'       => $punkte,
        'jahre'        => $jahre,
        'einheit'      => tank_einheit($art),
    ];
}

/**
 * Verbrauch je Tankung und in Summe. Reine Funktion.
 *
 * Liefert: eintraege (neueste zuerst, je Zeile strecke, verbrauch,
 * preis_je_liter, auffaellig), punkte (chronologisch, Volltankungen mit
 * Verbrauch – Kraftstoff), liter, betrag (alle Arten), betrag_fehlt,
 * strecke, verbrauch, anzahl, letzte, jahre (Kraftstoff: anzahl, liter,
 * betrag, betrag_fehlt, strecke, verbrauch), adblue_liter, km_von, km_bis –
 * und strom: dieselbe Auswertung für das Laden in kWh (anzahl, menge,
 * betrag, betrag_fehlt, strecke, verbrauch, punkte, jahre, einheit).
 */
function tank_auswerten(array $tankungen): array
{
    $sortiert = tank_sortieren($tankungen);
    $adblue = 0.0;
    $adblueBetrag = 0.0;
    $kmVon = null;
    $kmBis = null;

    foreach ($sortiert as &$t) {
        $t['strecke'] = null;
        $t['verbrauch'] = null;
        $t['auffaellig'] = false;
        $t['einheit'] = tank_einheit((string)$t['art']);
        $t['preis_je_liter'] = $t['betrag'] !== null && (float)$t['betrag'] > 0 && (float)$t['liter'] > 0
            ? (float)$t['betrag'] / (float)$t['liter'] : null;
        if ((string)$t['art'] === 'adblue') {
            $adblue += (float)$t['liter'];
            $adblueBetrag += $t['betrag'] !== null ? (float)$t['betrag'] : 0.0;
        }
        if ($t['km_stand'] !== null) {
            $km = (int)$t['km_stand'];
            $kmVon = $kmVon === null ? $km : min($kmVon, $km);
            $kmBis = $kmBis === null ? $km : max($kmBis, $km);
        }
    }
    unset($t);

    $kraftstoff = tank_rechnen($sortiert, 'kraftstoff');
    $strom = tank_rechnen($sortiert, 'strom');

    $jahre = [];
    foreach ($kraftstoff['jahre'] as $jahr => $j) {
        $j['liter'] = $j['menge'];
        unset($j['menge']);
        $jahre[$jahr] = $j;
    }

    $eintraege = array_reverse($sortiert);
    return [
        'eintraege'    => $eintraege,
        'punkte'       => $kraftstoff['punkte'],
        'anzahl'       => count($sortiert),
        'liter'        => $kraftstoff['menge'],
        'adblue_liter' => $adblue,
        'betrag'       => $kraftstoff['betrag'] + $strom['betrag'] + $adblueBetrag,
        'betrag_fehlt' => $kraftstoff['betrag_fehlt'] + $strom['betrag_fehlt'],
        'strecke'      => $kraftstoff['strecke'],
        'verbrauch'    => $kraftstoff['verbrauch'],
        'letzte'       => $eintraege[0] ?? null,
        'jahre'        => $jahre,
        'km_von'       => $kmVon,
        'km_bis'       => $kmBis,
        'strom'        => $strom,
    ];
}

/** Zeilen für die CSV-Ausgabe: Kopf zuerst, älteste Tankung zuerst. Reine Funktion. */
function tank_csv_zeilen(array $auswertung): array
{
    $zeilen = [['Datum', 'Kilometerstand', 'Menge', 'Einheit', 'Art', 'Volltankung', 'Strecke seit Volltankung (km)', 'Verbrauch je 100 km',
                'Betrag', 'Preis je Einheit', 'Tankkarte', 'Tankstelle', 'Belegnummer', 'Notiz', 'Erfasst von']];
    foreach (array_reverse($auswertung['eintraege']) as $t) {
        $zeilen[] = [
            (string)$t['datum'],
            $t['km_stand'] !== null ? (string)(int)$t['km_stand'] : '',
            number_format((float)$t['liter'], 2, ',', ''),
            tank_einheit((string)$t['art']),
            TANK_ARTEN[(string)$t['art']] ?? (string)$t['art'],
            (int)$t['voll'] === 1 ? 'ja' : 'nein',
            $t['strecke'] !== null ? (string)$t['strecke'] : '',
            $t['verbrauch'] !== null ? number_format($t['verbrauch'], 2, ',', '') : '',
            $t['betrag'] !== null ? number_format((float)$t['betrag'], 2, ',', '') : '',
            $t['preis_je_liter'] !== null ? number_format($t['preis_je_liter'], 3, ',', '') : '',
            tank_karte_text($t),
            (string)($t['tankstelle'] ?? ''),
            (string)($t['beleg_nr'] ?? ''),
            (string)($t['notiz'] ?? ''),
            (string)($t['erfasst_von'] ?? ''),
        ];
    }
    return $zeilen;
}

/* ==================================================================== */
/*  Anzeige                                                               */
/* ==================================================================== */

function tank_liter_text(float|string|null $liter, int $stellen = 2, string $einheit = 'l'): string
{
    return $liter === null ? '' : number_format((float)$liter, $stellen, ',', '.') . ' ' . $einheit;
}

/** Menge einer Tankung mit ihrer Einheit: „62,40 l" oder „41,20 kWh". Reine Funktion. */
function tank_menge_text(array $t, int $stellen = 2): string
{
    return tank_liter_text((float)$t['liter'], $stellen, tank_einheit((string)($t['art'] ?? 'kraftstoff')));
}

function tank_km_text(int|string|null $km): string
{
    return $km === null || $km === '' ? '' : number_format((float)$km, 0, ',', '.') . ' km';
}

function tank_verbrauch_text(?float $verbrauch, string $einheit = 'l'): string
{
    return $verbrauch === null ? '' : number_format($verbrauch, 1, ',', '.') . ' ' . $einheit . '/100 km';
}

/** Welche Karte benutzt wurde, kurz: „DKV …4711". Reine Funktion. */
function tank_karte_text(array $t): string
{
    $nummer = trim((string)($t['karte_nummer'] ?? ''));
    if ($nummer === '') {
        return '';
    }
    $kurz = mb_strlen($nummer) > 6 ? '…' . mb_substr($nummer, -4) : $nummer;
    return trim((string)($t['karte_anbieter'] ?? '') . ' ' . $kurz);
}

/** Darf diese Person den Eintrag ändern oder löschen? Leitung immer, sonst wer ihn erfasst hat. */
function tank_darf_aendern(array $t, ?array $user): bool
{
    if (can('manage_vehicles')) {
        return true;
    }
    return $user !== null && can('tank_vehicle') && (int)($t['user_id'] ?? 0) === (int)($user['id'] ?? -1);
}

/* ==================================================================== */
/*  Schreiben                                                             */
/* ==================================================================== */

/** Kurztext für Journal und Meldungen: „62,40 l Kraftstoff bei 123.456 km" */
function tank_kurztext(array $d): string
{
    $art = (string)($d['art'] ?? 'kraftstoff');
    $s = tank_liter_text((float)$d['liter'], 2, tank_einheit($art)) . ' ' . (TANK_ARTEN[$art] ?? 'Kraftstoff');
    if ($d['km_stand'] !== null) {
        $s .= ' bei ' . tank_km_text((int)$d['km_stand']);
    }
    if ((int)$d['voll'] !== 1 && isset(TANK_VERBRAUCH_ARTEN[$art])) {
        $s .= $art === 'strom' ? ' (nicht voll geladen)' : ' (Teiltankung)';
    }
    return $s;
}

/**
 * Tankung aus dem Formular prüfen und speichern. Gibt [id, fehler] zurück.
 * Der Kilometerstand muss zu den Nachbarn passen: nicht unter dem einer
 * früheren, nicht über dem einer späteren Tankung. Liegt er über dem Stand
 * des Fahrzeugs, wird der nachgezogen – mit Journaleintrag.
 */
function tank_save_from_post(?array $existing, array $vehicle, array $user): array
{
    $art = post_str('art', 'kraftstoff');
    $d = [
        'vehicle_id'   => (int)$vehicle['id'],
        'datum'        => post_date('datum'),
        'km_stand'     => post_int('km_stand'),
        'liter'        => round(post_dec('liter'), 2),
        'betrag'       => post_str('betrag') === '' ? null : round(post_dec('betrag'), 2),
        'voll'         => post_bool('voll'),
        'art'          => array_key_exists($art, TANK_ARTEN) ? $art : 'kraftstoff',
        'tankkarte_id' => post_int('tankkarte_id'),
        'tankstelle'   => mb_substr(post_str('tankstelle'), 0, 150),
        'beleg_nr'     => mb_substr(post_str('beleg_nr'), 0, 80),
        'notiz'        => post_str('notiz'),
    ];
    if ($d['art'] === 'adblue') {
        $d['voll'] = 0;
    }

    $errors = [];
    if ($d['datum'] === null) {
        $errors[] = 'Bitte das Datum der Tankung angeben.';
    } elseif ($d['datum'] > date('Y-m-d', strtotime('+1 day'))) {
        $errors[] = 'Das Datum liegt in der Zukunft.';
    }
    if ($d['liter'] <= 0) {
        $errors[] = $d['art'] === 'strom' ? 'Bitte die geladenen Kilowattstunden angeben.' : 'Bitte die getankten Liter angeben.';
    } elseif ($d['liter'] > TANK_LITER_MAX) {
        $errors[] = sprintf('Mehr als %s auf einmal? Bitte die Zahl prüfen.', tank_liter_text(TANK_LITER_MAX, 0, tank_einheit($d['art'])));
    }
    if ($d['km_stand'] !== null && ($d['km_stand'] < 0 || $d['km_stand'] > 9999999)) {
        $errors[] = 'Der Kilometerstand ist nicht plausibel.';
    }
    if ($d['betrag'] !== null && $d['betrag'] < 0) {
        $errors[] = 'Der Betrag darf nicht negativ sein.';
    }

    $ausser = (int)($existing['id'] ?? 0);
    if ($d['datum'] !== null && $d['km_stand'] !== null) {
        $vorher = db_row(
            'SELECT datum, km_stand FROM vehicle_tankungen
             WHERE vehicle_id = ? AND km_stand IS NOT NULL AND datum < ? AND id <> ?
             ORDER BY datum DESC, km_stand DESC LIMIT 1',
            [$d['vehicle_id'], $d['datum'], $ausser]
        );
        if ($vorher && (int)$vorher['km_stand'] > $d['km_stand']) {
            $errors[] = sprintf('Der Kilometerstand liegt unter dem der Tankung vom %s (%s). Bitte Datum oder Stand prüfen.',
                de_date((string)$vorher['datum']), tank_km_text((int)$vorher['km_stand']));
        }
        $nachher = db_row(
            'SELECT datum, km_stand FROM vehicle_tankungen
             WHERE vehicle_id = ? AND km_stand IS NOT NULL AND datum > ? AND id <> ?
             ORDER BY datum ASC, km_stand ASC LIMIT 1',
            [$d['vehicle_id'], $d['datum'], $ausser]
        );
        if ($nachher && (int)$nachher['km_stand'] < $d['km_stand']) {
            $errors[] = sprintf('Der Kilometerstand liegt über dem der späteren Tankung vom %s (%s). Bitte Datum oder Stand prüfen.',
                de_date((string)$nachher['datum']), tank_km_text((int)$nachher['km_stand']));
        }
    }
    if ($errors) {
        return [null, $errors];
    }

    if ($existing) {
        $id = (int)$existing['id'];
        db_update('vehicle_tankungen', $d, 'id = ?', [$id]);
        journal_add((int)$vehicle['id'], [
            'art'      => 'tankung',
            'titel'    => 'Tankung geändert: ' . tank_kurztext($d),
            'text'     => tank_journal_text($d),
            'alt_wert' => tank_kurztext($existing),
            'neu_wert' => tank_kurztext($d),
            'ref_typ'  => 'tankung',
            'ref_id'   => $id,
        ], $user);
    } else {
        $d['user_id'] = (int)$user['id'];
        $d['created_at'] = date('Y-m-d H:i:s');
        $id = db_insert('vehicle_tankungen', $d);
        journal_add((int)$vehicle['id'], [
            'art'     => 'tankung',
            'titel'   => ($d['art'] === 'strom' ? 'Geladen: ' : 'Getankt: ') . tank_kurztext($d),
            'text'    => tank_journal_text($d),
            'ref_typ' => 'tankung',
            'ref_id'  => $id,
        ], $user);
    }

    // Kilometerstand des Fahrzeugs nachziehen, wenn die Tankung weiter ist
    $kmAlt = $vehicle['km_stand'] !== null ? (int)$vehicle['km_stand'] : null;
    if ($d['km_stand'] !== null && ($kmAlt === null || $d['km_stand'] > $kmAlt)) {
        db_update('vehicles', ['km_stand' => $d['km_stand']], 'id = ?', [(int)$vehicle['id']]);
        journal_changes((int)$vehicle['id'], ['km_stand' => $kmAlt], ['km_stand' => $d['km_stand']], $user);
    }
    return [$id, []];
}

/** Journaltext: Datum, Betrag, Tankstelle, Beleg – was da ist */
function tank_journal_text(array $d): string
{
    $teile = ['am ' . de_date((string)$d['datum'])];
    if ($d['betrag'] !== null) {
        $teile[] = money((float)$d['betrag']);
    }
    if ((string)($d['tankstelle'] ?? '') !== '') {
        $teile[] = (string)$d['tankstelle'];
    }
    if ((string)($d['beleg_nr'] ?? '') !== '') {
        $teile[] = 'Beleg ' . (string)$d['beleg_nr'];
    }
    return implode(' · ', $teile);
}

/** Tankung samt Belegen löschen; das Journal behält, dass es sie gab */
function tank_delete(array $t, array $user): void
{
    foreach (vfile_list_tank((int)$t['id']) as $datei) {
        vfile_delete($datei, $user);
    }
    db_exec('DELETE FROM vehicle_tankungen WHERE id = ?', [(int)$t['id']]);
    journal_add((int)$t['vehicle_id'], [
        'art'      => 'tankung',
        'titel'    => 'Tankung gelöscht: ' . tank_kurztext($t),
        'alt_wert' => tank_journal_text($t),
        'ref_typ'  => 'tankung',
        'ref_id'   => (int)$t['id'],
    ], $user);
}
