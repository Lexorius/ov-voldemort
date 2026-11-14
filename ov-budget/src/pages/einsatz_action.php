<?php
declare(strict_types=1);

/**
 * Dokumente am Einsatz:
 *   POST action=upload     einsatz_id, dateien[], [titel]      – vom Formular, leitet zurück
 *   POST action=datei_weg  datei_id                            – vom Formular, leitet zurück
 *   POST action=pdf        einsatz_id, herkunft (auftrag|karte), pdf[], [daten], [karte_id], [titel]
 *                          – ein im Browser erzeugtes PDF (Einsatzauftrag, Lagekarte) ablegen; Antwort als JSON
 */
$user = require_login();
$action = post_str('action');

if ($action === 'pdf') {
    header('Content-Type: application/json; charset=UTF-8');
    $antwort = static function (array $d, int $code = 200): never {
        http_response_code($code);
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $antwort(['ok' => false, 'fehler' => 'Unbekannte Anfrage.'], 400);
    }
    if (!can('manage_einsaetze')) {
        $antwort(['ok' => false, 'fehler' => 'Dokumente an einem Einsatz ablegen darfst du nicht.'], 403);
    }
    $einsatz = einsatz_find(post_int('einsatz_id'));
    if (!$einsatz) {
        $antwort(['ok' => false, 'fehler' => 'Diesen Einsatz gibt es nicht (mehr).'], 404);
    }
    $herkunft = post_str('herkunft') === 'karte' ? 'karte' : 'auftrag';
    $daten = null;
    $karteId = null;
    if ($herkunft === 'auftrag') {
        $daten = einsatz_auftrag_daten(post_str('daten'));
        $titel = einsatz_auftrag_titel($daten);
    } else {
        $karte = can('view_karten') ? karte_find(post_int('karte_id')) : null;
        if (!$karte) {
            $antwort(['ok' => false, 'fehler' => 'Diese Karte gibt es nicht (mehr).'], 404);
        }
        $karteId = (int)$karte['id'];
        $titel = mb_substr('Lagekarte ' . (string)$karte['name'] . ' – Stand ' . date('d.m.Y H:i'), 0, 200);
    }
    [$anzahl, $fehler, $ids] = einsatz_dateien_ablegen((int)$einsatz['id'], 'pdf', $titel, $user, $herkunft, $daten, $karteId);
    if ($anzahl < 1) {
        $antwort(['ok' => false, 'fehler' => $fehler ? implode(' ', $fehler) : 'Es kam kein PDF an.'], 422);
    }
    $antwort(['ok' => true, 'titel' => $titel, 'datei' => url('einsatz_datei', ['id' => $ids[0]]),
              'einsatz' => url('einsatz', ['id' => (int)$einsatz['id']]) . '#dateien']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('einsaetze');
}
if (!can('manage_einsaetze')) {
    flash('error', 'Dokumente an einem Einsatz pflegt die Leitung.');
    redirect_route('einsaetze');
}

if ($action === 'datei_weg') {
    $datei = einsatz_datei_find(post_int('datei_id'));
    if (!$datei) {
        flash('error', 'Dieses Dokument gibt es nicht (mehr).');
        redirect_route('einsaetze');
    }
    einsatz_datei_delete($datei);
    flash('success', '„' . (string)($datei['titel'] ?: $datei['orig_name']) . '" entfernt.');
    redirect(url('einsatz', ['id' => (int)$datei['einsatz_id']]) . '#dateien');
}

if ($action === 'upload') {
    $einsatz = einsatz_find(post_int('einsatz_id'));
    if (!$einsatz) {
        flash('error', 'Diesen Einsatz gibt es nicht (mehr).');
        redirect_route('einsaetze');
    }
    [$anzahl, $fehler] = einsatz_dateien_ablegen((int)$einsatz['id'], 'dateien', post_str('titel'), $user);
    foreach ($fehler as $f) {
        flash('error', $f);
    }
    if ($anzahl > 0) {
        flash('success', ($anzahl === 1 ? 'Ein Dokument' : $anzahl . ' Dokumente') . ' abgelegt.');
    }
    redirect(url('einsatz', ['id' => (int)$einsatz['id']]) . '#dateien');
}

redirect_route('einsaetze');
