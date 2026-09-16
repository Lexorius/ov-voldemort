<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('meetings');
}

$user = require_role('admin', 'leitung');
$meetingId = post_int('meeting_id', 0) ?? 0;
$meeting = $meetingId ? meeting_find($meetingId) : null;
$zurueck = $meeting ? url('meeting', ['id' => $meetingId]) : url('talking_points');

switch (post_str('action')) {

    case 'assign':
        // Aus dem Themenspeicher auf eine Besprechung setzen, auch mehrere auf einmal
        if (!$meeting || $meeting['status'] !== 'geplant') {
            flash('error', 'Themen lassen sich nur auf geplante Besprechungen setzen.');
            break;
        }
        $anzahl = 0;
        foreach ((array)post('tp_ids', []) as $tpId) {
            if ((int)$tpId > 0 && tp_assign((int)$tpId, $meetingId)) {
                $anzahl++;
            }
        }
        flash('success', sprintf('%d Thema/Themen auf die Tagesordnung gesetzt.', $anzahl));
        break;

    case 'unassign':
        $tp = tp_find(post_int('tp_id', 0) ?? 0);
        if ($tp && (int)$tp['meeting_id'] === $meetingId) {
            db_update('talking_points', ['meeting_id' => null, 'sort_order' => 0], 'id = ?', [$tp['id']]);
            flash('success', 'Thema zurück in den Themenspeicher gelegt.');
        }
        break;

    case 'move':
        tp_move(post_int('tp_id', 0) ?? 0, post_str('richtung') === 'hoch' ? 'hoch' : 'runter');
        $zurueck .= '#tp' . (post_int('tp_id', 0) ?? 0);
        break;

    case 'result':
        $tp = tp_find(post_int('tp_id', 0) ?? 0);
        if ($tp && (int)$tp['meeting_id'] === $meetingId) {
            db_update('talking_points', [
                'status_id'      => post_int('status_id') ?: $tp['status_id'],
                'ergebnis'       => post_str('ergebnis'),
                'verantwortlich' => mb_substr(post_str('verantwortlich'), 0, 150),
            ], 'id = ?', [$tp['id']]);
            audit('tp.ergebnis', 'talking_point', (int)$tp['id'], list_label(post_int('status_id')));
            flash('success', 'Ergebnis festgehalten.');
            $zurueck .= '#tp' . (int)$tp['id'];
        }
        break;

    case 'todo':
        $tp = tp_find(post_int('tp_id', 0) ?? 0);
        if ($tp && !$tp['todo_id']) {
            $todoId = tp_create_todo($tp, $user);
            flash('success', 'Aufgabe angelegt: <a href="' . e(url('todo', ['id' => $todoId])) . '">'
                . e((string)$tp['titel']) . '</a>');
            $zurueck .= '#tp' . (int)$tp['id'];
        }
        break;

    case 'notes':
        if ($meeting) {
            db_update('meetings', [
                'notizen'       => post_str('notizen'),
                'teilnehmer'    => post_str('teilnehmer'),
                'protokoll_von' => mb_substr(post_str('protokoll_von'), 0, 150),
            ], 'id = ?', [$meetingId]);
            flash('success', 'Protokollangaben gespeichert.');
            $zurueck .= '#protokoll';
        }
        break;

    case 'close':
        if ($meeting) {
            // Noch offene Punkte gelten beim Abschluss als vertagt
            $offen = list_id_by_slug('tp_status', 'offen');
            $vertagt = list_id_by_slug('tp_status', 'vertagt');
            $n = 0;
            if ($offen && $vertagt) {
                $n = db_exec('UPDATE talking_points SET status_id = ? WHERE meeting_id = ? AND status_id = ?',
                    [$vertagt, $meetingId, $offen]);
            }
            db_update('meetings', ['status' => 'abgeschlossen'], 'id = ?', [$meetingId]);
            audit('besprechung.abgeschlossen', 'meeting', $meetingId, $meeting['titel']);
            flash('success', 'Besprechung abgeschlossen.'
                . ($n > 0 ? sprintf(' %d offene(s) Thema/Themen als vertagt markiert – sie stehen wieder im Themenspeicher.', $n) : ''));
        }
        break;

    case 'reopen':
        if ($meeting) {
            db_update('meetings', ['status' => 'geplant'], 'id = ?', [$meetingId]);
            audit('besprechung.wieder_geoeffnet', 'meeting', $meetingId, $meeting['titel']);
            flash('info', 'Besprechung wieder geöffnet.');
        }
        break;

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
