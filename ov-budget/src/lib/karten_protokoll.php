<?php
declare(strict_types=1);

/**
 * Protokoll einer Karte: wann welche Einheit oder welches Fahrzeug gesetzt,
 * versetzt oder herausgenommen wurde – von wo nach wo und von wem.
 *
 * Geschrieben wird beim Speichern auf der Karte (karten.php, karten_api).
 * Aus den Zeilen rechnet karten_einsatzzeiten(), wie lange jede Einheit und
 * jedes Fahrzeug im Einsatz war: vom ersten Setzen bis zum Herausnehmen –
 * oder bis jetzt, solange es noch steht.
 *
 * Fahrzeuge, die ihre Position selbst melden (Divera, QR-Code), zieht
 * niemand; sie kommen über „Fahrzeug aufnehmen" ins Protokoll und über
 * „Einsatz beendet" wieder heraus.
 */

const KARTEN_PROTOKOLL_AKTIONEN = [
    'gesetzt'  => 'gesetzt',
    'versetzt' => 'versetzt',
    'entfernt' => 'herausgenommen',
];
const KARTEN_PROTOKOLL_ARTEN = ['einheit' => 'Einheit', 'fahrzeug' => 'Fahrzeug'];

/** Luftlinie zwischen zwei Stellen in Metern – null, wenn eine fehlt. Reine Funktion. */
function karten_entfernung_m(mixed $lat1, mixed $lng1, mixed $lat2, mixed $lng2): ?int
{
    foreach ([$lat1, $lng1, $lat2, $lng2] as $v) {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
    }
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLng = deg2rad((float)$lng2 - (float)$lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLng / 2) ** 2;
    return (int)round(2 * 6371000 * asin(min(1.0, sqrt($a))));
}

/** „240 m" oder „3,4 km". Reine Funktion. */
function karten_strecke_text(?int $meter): string
{
    if ($meter === null) {
        return '';
    }
    return $meter < 1000 ? $meter . ' m' : number_format($meter / 1000, 1, ',', '.') . ' km';
}

/** Minuten als Dauer: „45 min", „3 h 25 min", „2 Tage 4 h". Reine Funktion. */
function karten_dauer_text(int $minuten): string
{
    $minuten = max(0, $minuten);
    if ($minuten < 60) {
        return $minuten . ' min';
    }
    if ($minuten < 1440) {
        return intdiv($minuten, 60) . ' h' . ($minuten % 60 > 0 ? ' ' . ($minuten % 60) . ' min' : '');
    }
    $tage = intdiv($minuten, 1440);
    $h = intdiv($minuten % 1440, 60);
    return $tage . ($tage === 1 ? ' Tag' : ' Tage') . ($h > 0 ? ' ' . $h . ' h' : '');
}

/** „53.079300, 8.801700" – leer ohne Stelle. Reine Funktion. */
function karten_stelle_text(mixed $lat, mixed $lng): string
{
    return $lat === null || $lng === null || $lat === '' || $lng === '' ? '' : sprintf('%.5f, %.5f', (float)$lat, (float)$lng);
}

/**
 * Eine Zeile ins Protokoll schreiben. $was: marker_id, vehicle_id, name, von_lat, von_lng,
 * lat, lng, abschnitt, staerke. Das Protokoll darf die Karte nie aufhalten – fehlt etwa die
 * Tabelle noch (vor der Wanderung), wird still übergangen.
 */
function karten_protokoll_schreiben(int $karteId, string $art, string $aktion, array $was, ?array $user = null): void
{
    if ($karteId <= 0 || !isset(KARTEN_PROTOKOLL_ARTEN[$art]) || !isset(KARTEN_PROTOKOLL_AKTIONEN[$aktion])) {
        return;
    }
    $zahl = static fn($v): ?float => $v === null || $v === '' ? null : round((float)$v, 6);
    $user ??= function_exists('current_user') ? current_user() : null;
    try {
        db_insert('karten_protokoll', [
            'karte_id'   => $karteId,
            'art'        => $art,
            'marker_id'  => !empty($was['marker_id']) ? (int)$was['marker_id'] : null,
            'vehicle_id' => !empty($was['vehicle_id']) ? (int)$was['vehicle_id'] : null,
            'name'       => mb_substr(trim((string)($was['name'] ?? '')), 0, 200),
            'aktion'     => $aktion,
            'von_lat'    => $zahl($was['von_lat'] ?? null),
            'von_lng'    => $zahl($was['von_lng'] ?? null),
            'lat'        => $zahl($was['lat'] ?? null),
            'lng'        => $zahl($was['lng'] ?? null),
            'meter'      => karten_entfernung_m($was['von_lat'] ?? null, $was['von_lng'] ?? null, $was['lat'] ?? null, $was['lng'] ?? null),
            'abschnitt'  => mb_substr(trim((string)($was['abschnitt'] ?? '')), 0, 100),
            'staerke'    => mb_substr(trim((string)($was['staerke'] ?? '')), 0, 24),
            'user_id'    => !empty($user['id']) ? (int)$user['id'] : null,
        ]);
    } catch (Throwable $ex) {
        error_log('OV-Multitool Kartenprotokoll: ' . $ex->getMessage());
    }
}

/** Wie eine Einheit im Protokoll heißt: ihre Beschriftung, sonst der Name des Symbols. Reine Funktion. */
function karten_protokoll_einheit_name(array $marker): string
{
    $text = trim((string)($marker['beschriftung'] ?? ''));
    return $text !== '' ? $text : (trim((string)($marker['symbol_name'] ?? '')) ?: 'Einheit');
}

/**
 * Eine Einheit (gesetztes Symbol) protokollieren. $marker ist die Zeile nach der Änderung,
 * $vorher bei „versetzt" die Stelle davor (lat, lng).
 */
function karten_protokoll_einheit(array $marker, string $aktion, ?array $vorher = null): void
{
    $abschnitt = '';
    if (!empty($marker['abschnitt_id']) && function_exists('karten_abschnitt_find')) {
        $abschnitt = (string)(karten_abschnitt_find((int)$marker['abschnitt_id'])['name'] ?? '');
    }
    $st = function_exists('karten_staerke_daten') ? karten_staerke_daten($marker) : ['gesamt' => 0, 'text' => ''];
    karten_protokoll_schreiben((int)($marker['karte_id'] ?? 0), 'einheit', $aktion, [
        'marker_id' => (int)($marker['id'] ?? 0),
        'name'      => karten_protokoll_einheit_name($marker),
        'von_lat'   => $vorher['lat'] ?? null,
        'von_lng'   => $vorher['lng'] ?? null,
        'lat'       => $aktion === 'entfernt' ? null : ($marker['lat'] ?? null),
        'lng'       => $aktion === 'entfernt' ? null : ($marker['lng'] ?? null),
        'abschnitt' => $abschnitt,
        'staerke'   => $st['gesamt'] > 0 ? $st['text'] : '',
    ]);
}

/** Ein Fahrzeug protokollieren: gesetzt, versetzt (mit der Stelle davor) oder herausgenommen */
function karten_protokoll_fahrzeug(int $karteId, array $vehicle, string $aktion, mixed $lat = null, mixed $lng = null, ?array $vorher = null): void
{
    $name = trim((string)($vehicle['bezeichnung'] ?? ''));
    $ruf = trim((string)($vehicle['funkrufname'] ?? ''));
    karten_protokoll_schreiben($karteId, 'fahrzeug', $aktion, [
        'vehicle_id' => (int)($vehicle['id'] ?? 0),
        'name'       => $name . ($ruf !== '' && $ruf !== $name ? ' (' . $ruf . ')' : ''),
        'von_lat'    => $vorher['lat'] ?? null,
        'von_lng'    => $vorher['lng'] ?? null,
        'lat'        => $lat,
        'lng'        => $lng,
    ]);
}

/** Das Protokoll einer Karte, älteste Zeile zuerst */
function karten_protokoll(int $karteId, int $limit = 5000): array
{
    return db_all('SELECT p.*, u.display_name AS wer FROM karten_protokoll p LEFT JOIN users u ON u.id = p.user_id
                   WHERE p.karte_id = ? ORDER BY p.created_at, p.id LIMIT ' . max(1, $limit), [$karteId]);
}

/** Woran eine Protokollzeile hängt: „einheit:12" oder „fahrzeug:3". Reine Funktion. */
function karten_protokoll_schluessel(array $p): string
{
    return (string)$p['art'] === 'fahrzeug' ? 'fahrzeug:' . (int)($p['vehicle_id'] ?? 0) : 'einheit:' . (int)($p['marker_id'] ?? 0);
}

/**
 * Was in einer Zeile geschah, in Worten: „gesetzt", „versetzt um 240 m", „in die Liste genommen",
 * „herausgenommen". Reine Funktion.
 */
function karten_protokoll_text(array $p): string
{
    $aktion = (string)$p['aktion'];
    if ($aktion === 'versetzt') {
        if (karten_stelle_text($p['lat'] ?? null, $p['lng'] ?? null) === '') {
            return 'von der Karte genommen, bleibt in der Liste';
        }
        $m = $p['meter'] ?? null;
        return $m !== null && $m !== '' ? 'versetzt um ' . karten_strecke_text((int)$m) : 'auf die Karte gesetzt';
    }
    if ($aktion === 'gesetzt') {
        return karten_stelle_text($p['lat'] ?? null, $p['lng'] ?? null) === '' ? 'aufgenommen (ohne Stelle)' : 'gesetzt';
    }
    return KARTEN_PROTOKOLL_AKTIONEN[$aktion] ?? $aktion;
}

/**
 * Wie lange jede Einheit und jedes Fahrzeug im Einsatz war. Aus dem Protokoll (älteste Zeile
 * zuerst): Setzen oder Versetzen beginnt die Zeit, Herausnehmen beendet sie; wer wieder
 * gesetzt wird, sammelt weiter. Wer noch steht, zählt bis $jetzt.
 * Je Eintrag: schluessel, art, name, marker_id, vehicle_id, seit, bis (null = noch im Einsatz),
 * aktiv, minuten, bewegungen, meter, abschnitt, staerke. Aktive zuerst, dann nach Beginn.
 * Reine Funktion.
 */
function karten_einsatzzeiten(array $protokoll, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $out = [];
    $laeuft = [];   // schluessel → Beginn des laufenden Abschnitts
    foreach ($protokoll as $p) {
        $key = karten_protokoll_schluessel($p);
        $zeit = strtotime((string)$p['created_at']) ?: $jetzt;
        $out[$key] ??= ['schluessel' => $key, 'art' => (string)$p['art'], 'name' => '', 'marker_id' => (int)($p['marker_id'] ?? 0),
            'vehicle_id' => (int)($p['vehicle_id'] ?? 0), 'seit' => (string)$p['created_at'], 'bis' => null, 'aktiv' => false,
            'minuten' => 0, 'sekunden' => 0, 'bewegungen' => 0, 'meter' => 0, 'abschnitt' => '', 'staerke' => ''];
        $e = &$out[$key];
        if (trim((string)($p['name'] ?? '')) !== '') {
            $e['name'] = (string)$p['name'];
        }
        if ((string)($p['abschnitt'] ?? '') !== '') {
            $e['abschnitt'] = (string)$p['abschnitt'];
        }
        if ((string)($p['staerke'] ?? '') !== '') {
            $e['staerke'] = (string)$p['staerke'];
        }
        if ((string)$p['aktion'] === 'entfernt') {
            if (isset($laeuft[$key])) {
                $e['sekunden'] += max(0, $zeit - $laeuft[$key]);
                unset($laeuft[$key]);
            }
            $e['bis'] = (string)$p['created_at'];
        } else {
            $laeuft[$key] ??= $zeit;
            $e['bis'] = null;
            if ((string)$p['aktion'] === 'versetzt' && ($p['meter'] ?? null) !== null && $p['meter'] !== '') {
                $e['bewegungen']++;
                $e['meter'] += (int)$p['meter'];
            }
        }
        unset($e);
    }
    foreach ($out as $key => &$e) {
        if (isset($laeuft[$key])) {
            $e['aktiv'] = true;
            $e['sekunden'] += max(0, $jetzt - $laeuft[$key]);
        }
        $e['minuten'] = (int)floor($e['sekunden'] / 60);
        unset($e['sekunden']);
    }
    unset($e);
    $liste = array_values($out);
    usort($liste, static fn($a, $b) => [$b['aktiv'], $a['seit'], $a['name']] <=> [$a['aktiv'], $b['seit'], $b['name']]);
    return $liste;
}

/**
 * Einsatzzeiten und Protokoll einer Karte, fertig beschriftet – für die Seite, den Abruf
 * aus dem Browser und das PDF der Lage.
 */
function karten_protokoll_daten(int $karteId, ?int $jetzt = null): array
{
    $protokoll = karten_protokoll($karteId);
    $zeiten = [];
    foreach (karten_einsatzzeiten($protokoll, $jetzt) as $z) {
        $zeiten[] = $z + [
            'art_text'   => KARTEN_PROTOKOLL_ARTEN[$z['art']] ?? $z['art'],
            'seit_text'  => de_datetime($z['seit']),
            'bis_text'   => $z['aktiv'] ? 'noch im Einsatz' : de_datetime((string)$z['bis']),
            'dauer_text' => karten_dauer_text($z['minuten']),
            'strecke_text' => $z['bewegungen'] > 0 ? karten_strecke_text($z['meter']) : '',
        ];
    }
    $zeilen = [];
    foreach ($protokoll as $p) {
        $zeilen[] = [
            'id'      => (int)$p['id'],
            'zeit'    => de_datetime((string)$p['created_at']),
            'art'     => KARTEN_PROTOKOLL_ARTEN[(string)$p['art']] ?? (string)$p['art'],
            'name'    => (string)$p['name'],
            'was'     => karten_protokoll_text($p),
            'von'     => karten_stelle_text($p['von_lat'] ?? null, $p['von_lng'] ?? null),
            'nach'    => karten_stelle_text($p['lat'] ?? null, $p['lng'] ?? null),
            'abschnitt' => (string)($p['abschnitt'] ?? ''),
            'staerke' => (string)($p['staerke'] ?? ''),
            'wer'     => (string)($p['wer'] ?? ''),
        ];
    }
    return ['zeiten' => $zeiten, 'protokoll' => $zeilen];
}

/** Fahrzeuge, die gerade auf dieser Karte im Einsatz stehen: [vehicle_id => true]. */
function karten_fahrzeuge_im_einsatz(array $zeiten): array
{
    $out = [];
    foreach ($zeiten as $z) {
        if ($z['art'] === 'fahrzeug' && $z['aktiv'] && $z['vehicle_id'] > 0) {
            $out[$z['vehicle_id']] = true;
        }
    }
    return $out;
}

/** Karten, die zu einem Einsatz gehören – mit der Zahl ihrer Einheiten */
function karten_fuer_einsatz(int $einsatzId): array
{
    return db_all('SELECT k.*, (SELECT COUNT(*) FROM karten_marker m WHERE m.karte_id = k.id) AS marker,
                          (SELECT COUNT(*) FROM karten_protokoll p WHERE p.karte_id = k.id) AS protokoll
                   FROM karten k WHERE k.einsatz_id = ? ORDER BY k.name, k.id', [$einsatzId]);
}
