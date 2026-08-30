<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('wishes');
}

$user = current_user();
$id = post_int('id', 0) ?? 0;
$wish = wish_find($id);
if (!$wish) {
    flash('error', 'Der Wunsch wurde nicht gefunden.');
    redirect_route('wishes');
}

$back = post_str('back');
$backUrl = ($back !== '' && str_starts_with($back, '/')) ? $back : url('wish', ['id' => $id]);

switch (post_str('action')) {

    case 'vote':
        if (!can('vote')) {
            flash('warn', 'Die Abstimmung ist derzeit deaktiviert.');
            break;
        }
        $has = db_val('SELECT points FROM wish_votes WHERE wish_id = ? AND user_id = ?', [$id, $user['id']]);
        if ($has !== null) {
            db_exec('DELETE FROM wish_votes WHERE wish_id = ? AND user_id = ?', [$id, $user['id']]);
        } else {
            $max = setting_int('wunsch_voting_punkte', 0);
            if ($max > 0) {
                $used = (int)db_val('SELECT COUNT(*) FROM wish_votes WHERE user_id = ?', [$user['id']], 0);
                if ($used >= $max) {
                    flash('warn', sprintf('Du hast bereits alle %d Stimmen vergeben. Nimm zuerst eine Stimme zurück.', $max));
                    break;
                }
            }
            db_exec('INSERT INTO wish_votes (wish_id, user_id, points) VALUES (?,?,1)', [$id, $user['id']]);
        }
        break;

    case 'comment':
        $body = post_str('body');
        if ($body !== '') {
            db_insert('wish_comments', ['wish_id' => $id, 'user_id' => (int)$user['id'], 'body' => $body]);
            flash('success', 'Kommentar gespeichert.');
        }
        break;

    case 'status':
        if (!can('change_status')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $data = ['status_id' => post_int('status_id'), 'updated_by' => (int)$user['id']];
        if (can('manage_wishes') && isset($_POST['prioritaet'])) {
            $data['prioritaet'] = post_int('prioritaet', 0);
        }
        db_update('wishes', $data, 'id = ?', [$id]);
        audit('wunsch.status', 'wish', $id, list_label($data['status_id']));
        flash('success', 'Status aktualisiert.');
        break;

    case 'attachment_delete':
        if (!can('edit_wish', $wish)) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $att = db_row('SELECT * FROM wish_attachments WHERE id = ? AND wish_id = ?', [post_int('attachment_id', 0), $id]);
        if ($att) {
            attachment_delete($att);
            audit('wunsch.anlage.geloescht', 'wish', $id, $att['orig_name']);
            flash('success', 'Anlage gelöscht.');
        }
        break;

    case 'delete':
        if (!can('delete_wish', $wish)) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        foreach (wish_attachments($id) as $att) {
            attachment_delete($att);
        }
        db_exec('DELETE FROM wishes WHERE id = ?', [$id]);
        audit('wunsch.geloescht', 'wish', $id, $wish['bezeichnung']);
        flash('success', 'Der Wunsch wurde gelöscht.');
        redirect_route('wishes');
        // no break – redirect beendet den Ablauf

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($backUrl);
