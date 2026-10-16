<?php
declare(strict_types=1);

/**
 * Entfernungen – !! TEST !!
 *
 * Luftlinie und Fahrstrecke von der eigenen Unterkunft zu Adressen an
 * Terminen, Veranstaltungen und THW-Dienststellen. Adressen werden über
 * Nominatim (OpenStreetMap) in Koordinaten übersetzt, Strecken über den
 * öffentlichen OSRM-Demoserver gerechnet. Beides sind öffentliche Dienste
 * ohne Gewähr – deshalb Testbetrieb, standardmäßig aus.
 *
 * Schonend: Jede Adresse und jede Strecke wird genau einmal angefragt und
 * dann gemerkt. Die Anfragen laufen über eine Warteschlange, die der
 * minütliche Abruf abarbeitet – höchstens EINE Anfrage je Abruf und nur,
 * wenn seit der letzten Anfrage die eingestellte Pause vergangen ist.
 */

class GeoException extends RuntimeException
{
}

const GEO_VERSUCHE_MAX = 3;
const GEO_QUELLE_ADRESSE = 'nominatim';
const GEO_QUELLE_ROUTE = 'osrm-demo';

/* ==================================================================== */
/* Schalter, Heimat, reine Rechnung                                      */
/* ==================================================================== */

function geo_aktiv(): bool
{
    return setting_bool('geo_aktiv', false);
}

function geo_pause_sek(): int
{
    return max(30, min(3600, setting_int('geo_pause_sek', 120)));
}

/** Die Unterkunft als Ausgangspunkt: ein Platz mit GPS-Koordinaten. null = nicht gewählt oder ohne Koordinaten. */
function geo_heim(): ?array
{
    $id = (int)state_get('geo_heim_standort_id', '0');
    if ($id <= 0) {
        return null;
    }
    $s = standort_find($id);
    if (!$s || $s['geo_lat'] === null || $s['geo_lng'] === null) {
        return null;
    }
    return ['lat' => (float)$s['geo_lat'], 'lng' => (float)$s['geo_lng'], 'name' => (string)$s['name'], 'id' => $id];
}

/** Luftlinie in Kilometern (Haversine). Reine Funktion. */
function geo_luftlinie_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return round(2 * $r * asin(min(1.0, sqrt($a))), 1);
}

/** Adresse als Schlüssel: Leerraum geglättet, Kleinschreibung. Reine Funktion. */
function geo_adresse_normal(string $adresse): string
{
    $a = trim((string)preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $adresse)));
    return mb_substr(mb_strtolower($a), 0, 255);
}

/** „49.142345,9.218765" → [lat, lng] oder null. Reine Funktion. */
function geo_koordinaten_aus_text(string $s): ?array
{
    if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $s, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
        if (abs($lat) <= 90 && abs($lng) <= 180) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }
    return null;
}

/** Fahrzeit lesbar: 45 → „45 min", 95 → „1 h 35 min". Reine Funktion. */
function geo_minuten_text(int $min): string
{
    if ($min < 60) {
        return $min . ' min';
    }
    $rest = $min % 60;
    return intdiv($min, 60) . ' h' . ($rest ? ' ' . $rest . ' min' : '');
}

/**
 * Eine Zeile für die Anzeige. Reine Funktion.
 * $e wie aus geo_entfernung(): luftlinie_km, km, minuten, stand (fertig|wartet|fehler|aus)
 */
function geo_entfernung_text(?array $e): string
{
    if ($e === null) {
        return '';
    }
    $teile = [];
    if ($e['km'] !== null) {
        $teile[] = number_format((float)$e['km'], 0, ',', '.') . ' km';
        if ($e['minuten'] !== null) {
            $teile[] = 'etwa ' . geo_minuten_text((int)$e['minuten']);
        }
        if ($e['luftlinie_km'] !== null) {
            $teile[] = '(Luftlinie ' . number_format((float)$e['luftlinie_km'], 0, ',', '.') . ' km)';
        }
        return implode(' · ', $teile);
    }
    if ($e['luftlinie_km'] !== null) {
        $teile[] = 'Luftlinie ' . number_format((float)$e['luftlinie_km'], 0, ',', '.') . ' km';
    }
    $teile[] = match ($e['stand']) {
        'wartet' => $e['luftlinie_km'] !== null ? 'Fahrstrecke wird ermittelt …' : 'Adresse wird gesucht …',
        'fehler' => $e['luftlinie_km'] !== null ? 'Fahrstrecke nicht ermittelbar' : 'Adresse nicht gefunden',
        'aus'    => 'Entfernungen sind ausgeschaltet',
        default  => '',
    };
    return implode(' · ', array_filter($teile, 'strlen'));
}

/* ==================================================================== */
/* Zwischenspeicher und Warteschlange                                    */
/* ==================================================================== */

/**
 * Koordinaten zu einer Adresse aus dem Zwischenspeicher. Unbekannte Adressen
 * werden vorgemerkt (nur bei eingeschalteter Entfernungsrechnung).
 * Rückgabe: ['lat', 'lng', 'stand' => fertig] oder ['stand' => wartet|fehler|aus].
 */
function geo_koordinaten(string $adresse, bool $vormerken = true): array
{
    $k = geo_koordinaten_aus_text($adresse);
    if ($k !== null) {
        return $k + ['stand' => 'fertig'];
    }
    $key = geo_adresse_normal($adresse);
    if ($key === '') {
        return ['stand' => 'fehler'];
    }
    $r = db_row('SELECT lat, lng, status FROM geo_adressen WHERE adresse = ?', [$key]);
    if ($r && $r['status'] === 'fertig' && $r['lat'] !== null) {
        return ['lat' => (float)$r['lat'], 'lng' => (float)$r['lng'], 'stand' => 'fertig'];
    }
    if ($r) {
        return ['stand' => $r['status'] === 'fehler' ? 'fehler' : 'wartet'];
    }
    if (!$vormerken || !geo_aktiv()) {
        return ['stand' => 'aus'];
    }
    db_exec('INSERT IGNORE INTO geo_adressen (adresse, text) VALUES (?, ?)', [$key, mb_substr(trim($adresse), 0, 255)]);
    return ['stand' => 'wartet'];
}

/** Strecke zwischen zwei Punkten aus dem Zwischenspeicher; unbekannte werden vorgemerkt. */
function geo_route(array $von, array $nach, bool $vormerken = true): array
{
    $p = [round((float)$von['lat'], 5), round((float)$von['lng'], 5), round((float)$nach['lat'], 5), round((float)$nach['lng'], 5)];
    $r = db_row('SELECT km, minuten, status FROM geo_routen WHERE von_lat = ? AND von_lng = ? AND nach_lat = ? AND nach_lng = ?', $p);
    if ($r && $r['status'] === 'fertig' && $r['km'] !== null) {
        return ['km' => (float)$r['km'], 'minuten' => (int)$r['minuten'], 'stand' => 'fertig'];
    }
    if ($r) {
        return ['stand' => $r['status'] === 'fehler' ? 'fehler' : 'wartet'];
    }
    if (!$vormerken || !geo_aktiv()) {
        return ['stand' => 'aus'];
    }
    db_exec('INSERT IGNORE INTO geo_routen (von_lat, von_lng, nach_lat, nach_lng) VALUES (?, ?, ?, ?)', $p);
    return ['stand' => 'wartet'];
}

/**
 * Entfernung von der Unterkunft zu einem Ziel. $ziel wie aus kalender_navi_ziel():
 * ['ziel' => „lat,lng" oder Adresse, 'koordinaten' => bool]. null = keine Unterkunft gewählt.
 * Rückgabe: luftlinie_km, km, minuten, stand, heim.
 */
function geo_entfernung(array $ziel): ?array
{
    $heim = geo_heim();
    if ($heim === null) {
        return null;
    }
    $out = ['luftlinie_km' => null, 'km' => null, 'minuten' => null, 'stand' => 'aus', 'heim' => $heim['name']];
    $k = geo_koordinaten((string)$ziel['ziel']);
    $out['stand'] = $k['stand'];
    if ($k['stand'] !== 'fertig') {
        return $out;
    }
    $out['luftlinie_km'] = geo_luftlinie_km($heim['lat'], $heim['lng'], $k['lat'], $k['lng']);
    $r = geo_route($heim, $k);
    $out['stand'] = $r['stand'];
    if ($r['stand'] === 'fertig') {
        $out['km'] = $r['km'];
        $out['minuten'] = $r['minuten'];
    }
    return $out;
}

/** Entfernungen zu allen Dienststellen mit Anschrift: [id => Entfernung]. Leer, wenn aus oder keine Unterkunft. */
function geo_dienststellen_entfernungen(array $dienststellen): array
{
    if (geo_heim() === null) {
        return [];
    }
    $out = [];
    foreach ($dienststellen as $d) {
        $adresse = thw_anschrift($d);
        if ($adresse === '' || !str_contains($adresse, ' ')) {
            continue;   // nur ein Ortsname ist zu ungenau
        }
        $e = geo_entfernung(['ziel' => $adresse, 'koordinaten' => false]);
        if ($e !== null) {
            $out[(int)$d['id']] = $e;
        }
    }
    return $out;
}

/** Zähler: Adressen und Strecken je Stand, Zeitpunkt der letzten Anfrage */
function geo_status(): array
{
    $out = ['adressen' => ['offen' => 0, 'fertig' => 0, 'fehler' => 0], 'routen' => ['offen' => 0, 'fertig' => 0, 'fehler' => 0]];
    foreach (db_all('SELECT status, COUNT(*) AS n FROM geo_adressen GROUP BY status') as $r) {
        $out['adressen'][(string)$r['status']] = (int)$r['n'];
    }
    foreach (db_all('SELECT status, COUNT(*) AS n FROM geo_routen GROUP BY status') as $r) {
        $out['routen'][(string)$r['status']] = (int)$r['n'];
    }
    $out['letzte_anfrage'] = (int)state_get('geo_letzte_anfrage', '0');
    $out['naechste_in'] = max(0, $out['letzte_anfrage'] + geo_pause_sek() - time());
    return $out;
}

function geo_fehler_liste(int $limit = 15): array
{
    return db_all("SELECT text, fehler, versuche, erledigt_am FROM geo_adressen WHERE status = 'fehler' ORDER BY erledigt_am DESC, id DESC LIMIT " . max(1, $limit));
}

function geo_erneut_versuchen(): int
{
    return db_exec("UPDATE geo_adressen SET status = 'offen', versuche = 0, fehler = '' WHERE status = 'fehler'")
        + db_exec("UPDATE geo_routen SET status = 'offen', versuche = 0, fehler = '' WHERE status = 'fehler'");
}

/** Alle Dienststellen mit Anschrift vormerken. Gibt die Zahl der neu vorgemerkten Adressen. */
function geo_dienststellen_vormerken(): int
{
    $n = 0;
    foreach (thw_all(true) as $d) {
        $adresse = thw_anschrift($d);
        if ($adresse === '' || !str_contains($adresse, ' ')) {
            continue;
        }
        $vorher = db_row('SELECT id FROM geo_adressen WHERE adresse = ?', [geo_adresse_normal($adresse)]);
        if ($vorher === null && geo_koordinaten($adresse)['stand'] === 'wartet') {
            $n++;
        }
    }
    return $n;
}

/** Ist die Pause seit der letzten Anfrage vorbei? */
function geo_pause_vorbei(?int $jetzt = null): bool
{
    $jetzt ??= time();
    return (int)state_get('geo_letzte_anfrage', '0') + geo_pause_sek() <= $jetzt;
}

/**
 * Genau EINE Anfrage abarbeiten – Adressen vor Strecken – und nur, wenn
 * eingeschaltet und die Pause vorbei ist. Vom minütlichen Abruf.
 * Rückgabe: ['art' => adresse|route|null, 'ok' => bool, 'text' => …]
 */
function geo_warteschlange_abarbeiten(?int $jetzt = null): array
{
    $jetzt ??= time();
    $r = ['art' => null, 'ok' => true, 'text' => ''];
    if (!geo_aktiv() || !geo_pause_vorbei($jetzt) || !db_lock('geo_warteschlange')) {
        return $r;
    }
    try {
        $a = db_row("SELECT * FROM geo_adressen WHERE status = 'offen' AND versuche < ? ORDER BY id LIMIT 1", [GEO_VERSUCHE_MAX]);
        if ($a) {
            $r['art'] = 'adresse';
            state_save('geo_letzte_anfrage', (string)$jetzt);
            db_exec('UPDATE geo_adressen SET versuche = versuche + 1 WHERE id = ?', [(int)$a['id']]);
            try {
                $t = geo_nominatim((string)$a['text']);
                if ($t === null) {
                    // Nicht gefunden ist endgültig – ein zweiter Versuch brächte dasselbe
                    db_exec("UPDATE geo_adressen SET status = 'fehler', versuche = ?, fehler = 'Adresse nicht gefunden', erledigt_am = NOW() WHERE id = ?", [GEO_VERSUCHE_MAX, (int)$a['id']]);
                    $r['ok'] = false;
                    $r['text'] = 'Adresse nicht gefunden: ' . $a['text'];
                } else {
                    db_exec("UPDATE geo_adressen SET status = 'fertig', lat = ?, lng = ?, treffer = ?, quelle = ?, fehler = '', erledigt_am = NOW() WHERE id = ?",
                        [$t['lat'], $t['lng'], mb_substr($t['treffer'], 0, 255), GEO_QUELLE_ADRESSE, (int)$a['id']]);
                    $r['text'] = 'Adresse gefunden: ' . $a['text'];
                }
            } catch (Throwable $ex) {
                $endgueltig = (int)$a['versuche'] + 1 >= GEO_VERSUCHE_MAX;
                db_exec("UPDATE geo_adressen SET status = ?, fehler = ?, erledigt_am = NOW() WHERE id = ?",
                    [$endgueltig ? 'fehler' : 'offen', mb_substr($ex->getMessage(), 0, 255), (int)$a['id']]);
                $r['ok'] = false;
                $r['text'] = 'Adresse „' . $a['text'] . '": ' . $ex->getMessage();
            }
            return $r;
        }
        $s = db_row("SELECT * FROM geo_routen WHERE status = 'offen' AND versuche < ? ORDER BY id LIMIT 1", [GEO_VERSUCHE_MAX]);
        if ($s) {
            $r['art'] = 'route';
            state_save('geo_letzte_anfrage', (string)$jetzt);
            db_exec('UPDATE geo_routen SET versuche = versuche + 1 WHERE id = ?', [(int)$s['id']]);
            try {
                $t = geo_osrm(['lat' => (float)$s['von_lat'], 'lng' => (float)$s['von_lng']], ['lat' => (float)$s['nach_lat'], 'lng' => (float)$s['nach_lng']]);
                db_exec("UPDATE geo_routen SET status = 'fertig', km = ?, minuten = ?, quelle = ?, fehler = '', erledigt_am = NOW() WHERE id = ?",
                    [$t['km'], $t['minuten'], GEO_QUELLE_ROUTE, (int)$s['id']]);
                $r['text'] = sprintf('Strecke gerechnet: %s km, %d min', number_format($t['km'], 1, ',', '.'), $t['minuten']);
            } catch (Throwable $ex) {
                $endgueltig = (int)$s['versuche'] + 1 >= GEO_VERSUCHE_MAX || $ex instanceof GeoException && str_starts_with($ex->getMessage(), 'Keine Route');
                db_exec("UPDATE geo_routen SET status = ?, versuche = ?, fehler = ?, erledigt_am = NOW() WHERE id = ?",
                    [$endgueltig ? 'fehler' : 'offen', $endgueltig ? GEO_VERSUCHE_MAX : (int)$s['versuche'] + 1, mb_substr($ex->getMessage(), 0, 255), (int)$s['id']]);
                $r['ok'] = false;
                $r['text'] = 'Strecke: ' . $ex->getMessage();
            }
        }
    } finally {
        db_unlock('geo_warteschlange');
    }
    return $r;
}

/* ==================================================================== */
/* Die öffentlichen Dienste                                              */
/* ==================================================================== */

/** GET mit JSON-Antwort. Die Prüfungen hängen über $GLOBALS['geo_http_attrappe'] eine Attrappe ein. */
function geo_http_get(string $url): mixed
{
    if (isset($GLOBALS['geo_http_attrappe']) && is_callable($GLOBALS['geo_http_attrappe'])) {
        return ($GLOBALS['geo_http_attrappe'])($url);
    }
    $agent = 'OV-Multitool/' . app_version() . ' (+https://github.com/Lexorius/ov-voldemort)';
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: de'],
            CURLOPT_USERAGENT      => $agent,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $roh = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($roh === false) {
            throw new GeoException('Dienst nicht erreichbar: ' . ($fehler ?: 'Zeitüberschreitung'));
        }
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => "Accept: application/json\r\nAccept-Language: de\r\nUser-Agent: $agent\r\n", 'timeout' => 20, 'ignore_errors' => true]]);
        $roh = @file_get_contents($url, false, $ctx);
        if ($roh === false) {
            throw new GeoException('Dienst nicht erreichbar.');
        }
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', (string)$h, $m)) {
                $code = (int)$m[1];
            }
        }
    }
    if ($code === 429) {
        throw new GeoException('Der Dienst bremst (HTTP 429) – Pause verlängern.');
    }
    if ($code >= 400) {
        throw new GeoException('Der Dienst antwortet mit HTTP ' . $code . '.');
    }
    $d = json_decode((string)$roh, true);
    if ($d === null) {
        throw new GeoException('Der Dienst antwortet nicht mit JSON.');
    }
    return $d;
}

function geo_nominatim_url(): string
{
    return rtrim(trim((string)setting('geo_nominatim_url', 'https://nominatim.openstreetmap.org')), '/');
}

function geo_osrm_url(): string
{
    return rtrim(trim((string)setting('geo_osrm_url', 'https://router.project-osrm.org')), '/');
}

/** Nominatim-Antwort lesen: erster Treffer als ['lat','lng','treffer'] oder null. Reine Funktion. */
function geo_nominatim_parsen(mixed $d): ?array
{
    if (!is_array($d) || !isset($d[0]) || !is_array($d[0]) || !isset($d[0]['lat'], $d[0]['lon'])) {
        return null;
    }
    return ['lat' => round((float)$d[0]['lat'], 6), 'lng' => round((float)$d[0]['lon'], 6), 'treffer' => (string)($d[0]['display_name'] ?? '')];
}

/** Adresse bei Nominatim suchen. null = nichts gefunden. */
function geo_nominatim(string $adresse): ?array
{
    $url = geo_nominatim_url() . '/search?' . http_build_query([
        'q' => $adresse, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'de,at,ch', 'accept-language' => 'de',
    ]);
    return geo_nominatim_parsen(geo_http_get($url));
}

/** OSRM-Antwort lesen: ['km','minuten']. Wirft GeoException bei „NoRoute" oder Unfug. Reine Funktion. */
function geo_osrm_parsen(mixed $d): array
{
    if (!is_array($d) || ($d['code'] ?? '') !== 'Ok' || empty($d['routes'][0]) || !isset($d['routes'][0]['distance'], $d['routes'][0]['duration'])) {
        $code = is_array($d) ? (string)($d['code'] ?? '?') : '?';
        throw new GeoException($code === 'NoRoute' ? 'Keine Route zwischen den Punkten.' : 'OSRM meldet: ' . $code . (isset($d['message']) ? ' – ' . $d['message'] : ''));
    }
    return ['km' => round((float)$d['routes'][0]['distance'] / 1000, 1), 'minuten' => (int)round((float)$d['routes'][0]['duration'] / 60)];
}

/** Fahrstrecke beim OSRM-Demoserver rechnen lassen */
function geo_osrm(array $von, array $nach): array
{
    $url = sprintf('%s/route/v1/driving/%s,%s;%s,%s?overview=false', geo_osrm_url(),
        number_format((float)$von['lng'], 6, '.', ''), number_format((float)$von['lat'], 6, '.', ''),
        number_format((float)$nach['lng'], 6, '.', ''), number_format((float)$nach['lat'], 6, '.', ''));
    return geo_osrm_parsen(geo_http_get($url));
}
