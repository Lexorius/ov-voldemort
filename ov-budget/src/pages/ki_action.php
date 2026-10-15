<?php
declare(strict_types=1);

/** KI-Auswertung einer Datei: von Hand anpassen, neu auswerten lassen, entfernen. Nur per POST. */
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('dashboard');
}

$typ = post_str('typ');
$id = post_int('datei_id');
$l = isset(KI_TYPEN[$typ]) ? ki_datei_laden($typ, $id) : null;
if (!$l) {
    flash('error', 'Diese Datei gibt es nicht (mehr).');
    redirect_route('dashboard');
}
if (!ki_darf_bearbeiten($typ, $l['datei'], $user)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die KI-Auswertung dieser Datei darfst du nicht ändern.']);
    return;
}

$name = (string)($l['datei']['titel'] ?: $l['datei']['orig_name']);
switch (post_str('action')) {
    case 'neu':
        if (!ki_modul_aktiv($typ)) {
            flash('warn', 'Die KI-Auswertung ist für diese Ablage ausgeschaltet.');
        } else {
            ki_einreihen($typ, $id);
            audit('ki.neu', $typ, $id, $name);
            flash('success', '„' . $name . '" ist zur Auswertung eingereiht und wird in den nächsten Minuten bearbeitet.');
        }
        break;
    case 'loeschen':
        ki_vergessen($typ, $id);
        audit('ki.entfernt', $typ, $id, $name);
        flash('success', 'Zusammenfassung und Schlagwörter zu „' . $name . '" entfernt.');
        break;
    default:
        ki_hand_speichern($typ, $id, post_str('zusammenfassung'), post_str('tags'));
        audit('ki.hand', $typ, $id, $name);
        flash('success', 'Zusammenfassung und Schlagwörter zu „' . $name . '" gespeichert.');
}
header('Location: ' . $l['eltern']['url']);
exit;
