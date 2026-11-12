<?php
declare(strict_types=1);

/**
 * Die Karte im Browser spricht hierüber mit der Anwendung – Antworten als JSON.
 *   GET  action=fahrzeuge                       Fahrzeuge mit gemeldeter Position
 *   POST action=marker_neu     karte_id, symbol_id, lat, lng
 *   POST action=marker_aendern id, [lat, lng], [groesse], [drehung], [beschriftung]
 *   POST action=marker_weg     id
 *   POST action=ausschnitt     karte_id, lat, lng, zoom
 */
$user = require_login();
header('Content-Type: application/json; charset=UTF-8');
$antwort = static function (array $d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (!can('view_karten')) {
    $antwort(['ok' => false, 'fehler' => 'Kein Zugriff auf die Karten.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (get_str('action') === 'fahrzeuge') {
        if (!can('view_vehicles')) {
            $antwort(['ok' => false, 'fehler' => 'Die Fahrzeuge sind für dich nicht sichtbar.'], 403);
        }
        $antwort(['ok' => true, 'fahrzeuge' => karten_fahrzeuge(), 'stand' => date('H:i')]);
    }
    $antwort(['ok' => false, 'fehler' => 'Unbekannte Anfrage.'], 400);
}

$action = post_str('action');
if ($action === 'ausschnitt') {
    $karte = karte_find(post_int('karte_id'));
    if (!can('manage_karten')) {
        $antwort(['ok' => false, 'fehler' => 'Den Ausschnitt einer Karte legt die Leitung fest.'], 403);
    }
    if (!$karte || !karten_koordinate_ok(post('lat'), post('lng'))) {
        $antwort(['ok' => false, 'fehler' => 'Diese Karte oder diese Stelle gibt es nicht.'], 404);
    }
    karte_ausschnitt_setzen($karte, (float)post('lat'), (float)post('lng'), (int)post_int('zoom', (int)$karte['zoom']));
    audit('karten.ausschnitt', 'karte', (int)$karte['id'], (string)$karte['name']);
    $antwort(['ok' => true]);
}

if (!can('mark_karten')) {
    $antwort(['ok' => false, 'fehler' => 'Symbole setzen und ändern darfst du nicht.'], 403);
}
switch ($action) {
    case 'marker_neu':
        $karte = karte_find(post_int('karte_id'));
        $symbol = karten_symbol_find(post_int('symbol_id'));
        if (!$karte || !$symbol) {
            $antwort(['ok' => false, 'fehler' => 'Die Karte oder das Symbol gibt es nicht (mehr).'], 404);
        }
        $marker = karten_marker_neu($karte, $symbol, post('lat'), post('lng'), $user);
        if (is_string($marker)) {
            $antwort(['ok' => false, 'fehler' => $marker], 422);
        }
        $antwort(['ok' => true, 'marker' => karten_marker_daten($marker)]);
        // kein break – $antwort beendet

    case 'marker_aendern':
        $marker = karten_marker_find(post_int('id'));
        if (!$marker) {
            $antwort(['ok' => false, 'fehler' => 'Dieses Symbol sitzt nicht mehr auf der Karte.'], 404);
        }
        $neu = array_intersect_key($_POST, array_flip(['lat', 'lng', 'groesse', 'drehung', 'beschriftung']));
        $geaendert = karten_marker_aendern($marker, $neu);
        if (is_string($geaendert)) {
            $antwort(['ok' => false, 'fehler' => $geaendert], 422);
        }
        $antwort(['ok' => true, 'marker' => karten_marker_daten($geaendert + $marker)]);

    case 'marker_weg':
        $marker = karten_marker_find(post_int('id'));
        if ($marker) {
            karten_marker_delete($marker);
        }
        $antwort(['ok' => true]);
}
$antwort(['ok' => false, 'fehler' => 'Unbekannte Anfrage.'], 400);
