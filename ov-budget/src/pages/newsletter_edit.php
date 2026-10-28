<?php
declare(strict_types=1);

/** Newsletter anlegen oder bearbeiten: PDF, Betreff und Text, Empfänger, Zeitpunkt */
$user = require_login();
if (!can('manage_newsletter')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('newsletter');
}

$id = get_int('id');
$n = $id ? newsletter_find($id) : null;
if ($id && !$n) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Newsletter gibt es nicht (mehr).']);
    return;
}
if ($n && !in_array((string)$n['status'], ['entwurf', 'geplant'], true)) {
    flash('warn', 'Ein Newsletter, der verschickt wird oder wurde, lässt sich nicht mehr ändern.');
    redirect_route('newsletter_detail', ['id' => (int)$n['id']]);
}

$errors = [];
$kontakteGewaehlt = $n ? newsletter_kontakt_ids((int)$n['id']) : [];
$verteilerGewaehlt = $n ? newsletter_verteiler_ids((int)$n['id']) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = newsletter_save_from_post($n, $user);
    if ($neu) {
        flash('success', $n ? 'Newsletter gespeichert.' : 'Newsletter angelegt – jetzt prüfen und planen.');
        redirect_route('newsletter_detail', ['id' => $neu]);
    }
    $n = array_merge($n ?? [], $_POST, ['id' => $n['id'] ?? null, 'status' => $n['status'] ?? 'entwurf', 'stored_name' => $n['stored_name'] ?? '', 'orig_name' => $n['orig_name'] ?? '']);
    $kontakteGewaehlt = wahl_ids('kontakte');
    $verteilerGewaehlt = wahl_ids('verteiler');
}
if (!$n) {
    $vorgabe = connector_for('newsletter');
    $n = ['id' => null, 'titel' => '', 'betreff' => '', 'text' => '', 'connector_id' => $vorgabe !== null ? (int)$vorgabe['id'] : null,
          'senden_am' => null, 'adressen' => '', 'status' => 'entwurf', 'stored_name' => '', 'orig_name' => ''];
}

$connectoren = connector_liste('newsletter');
$eingetragen = connector_find((int)($n['connector_id'] ?? 0));
if ($eingetragen !== null && !in_array((int)$eingetragen['id'], array_map(static fn($c) => (int)$c['id'], $connectoren), true)) {
    $connectoren[] = $eingetragen;
}
$kontaktOptionen = [];
foreach (contact_query(['nur_mit_email' => 1]) as $c) {
    $kontaktOptionen[] = ['id' => (int)$c['id'], 'text' => contact_name($c), 'sub' => (string)$c['email'], 'gruppe' => (string)($c['kategorie_label'] ?? '')];
}
$verteilerOptionen = [];
foreach (contact_groups_all(true) as $g) {
    $verteilerOptionen[] = ['id' => (int)$g['id'], 'text' => (string)$g['name'], 'sub' => (int)$g['anzahl'] . ' Kontakt(e)', 'gruppe' => ''];
}

render('newsletter_edit', [
    'title'             => $n['id'] ? 'Newsletter bearbeiten' : 'Newsletter anlegen',
    'n'                 => $n,
    'errors'            => $errors,
    'connectoren'       => $connectoren,
    'kontaktOptionen'   => $kontaktOptionen,
    'kontakteGewaehlt'  => $kontakteGewaehlt,
    'verteilerOptionen' => $verteilerOptionen,
    'verteilerGewaehlt' => $verteilerGewaehlt,
    'maxMb'             => (int)(upload_max_bytes() / 1024 / 1024),
]);
