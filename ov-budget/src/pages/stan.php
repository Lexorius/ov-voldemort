<?php
declare(strict_types=1);

/** STAN: Dokumentenpaket in Versionen – ansehen, Paket als neue Version hochladen, Dateien nachreichen */
$user = require_login();
$darf = in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$darf) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'STAN-Pakete pflegt die Leitung.']);
        return;
    }
    switch (post_str('action')) {
        case 'paket':
            [$id, $fehler, $hinweise] = stan_version_aus_zip('paket', post_str('bemerkung'), $user);
            foreach ($fehler as $f) {
                flash('error', e($f));
            }
            if ($id) {
                $v = stan_version_find($id);
                flash('success', e(sprintf('%s eingespielt – %d Datei(en).', stan_version_text($v ?? ['nummer' => 0]), count(stan_dateien($id)))));
                foreach ($hinweise as $h) {
                    flash('warn', e($h));
                }
            }
            break;

        case 'nachreichen':
            $v = stan_version_find(post_int('version_id'));
            if (!$v) {
                flash('error', 'Diese Version gibt es nicht.');
                break;
            }
            [$n, $fehler] = stan_dateien_nachreichen((int)$v['id'], 'dateien', post_str('ordner'), $user);
            if ($n > 0) {
                flash('success', sprintf('%d Datei(en) zu %s nachgereicht.', $n, stan_version_text($v)));
            }
            foreach ($fehler as $f) {
                flash('error', e($f));
            }
            break;

        case 'datei_weg':
            $d = stan_datei_find(post_int('datei_id'));
            if ($d) {
                stan_datei_loeschen($d);
                audit('stan.datei_geloescht', 'stan', (int)$d['version_id'], (string)$d['pfad']);
                flash('success', '„' . e((string)$d['name']) . '" entfernt.');
            }
            break;

        case 'version_weg':
            $v = stan_version_find(post_int('version_id'));
            if ($v) {
                stan_version_loeschen($v);
                flash('success', e(stan_version_text($v)) . ' samt Dateien entfernt.');
            }
            break;
    }
    redirect_route('stan', get_int('version') ? ['version' => get_int('version')] : []);
}

$versionen = stan_versionen();
$aktuell = $versionen[0] ?? null;
$gezeigt = null;
if (get_int('version')) {
    foreach ($versionen as $v) {
        if ((int)$v['id'] === get_int('version')) {
            $gezeigt = $v;
        }
    }
}
$gezeigt ??= $aktuell;

$q = trim(get_str('q'));
$alleVersionen = get_str('alle') === '1';
$dateien = $gezeigt ? stan_dateien((int)$gezeigt['id']) : [];
$treffer = $q !== '' ? stan_suche($q, $alleVersionen || !$gezeigt ? null : (int)$gezeigt['id']) : [];
$kiIds = array_merge(array_column($dateien, 'id'), array_column($treffer, 'id'));

render('stan', [
    'q'         => $q,
    'alleVersionen' => $alleVersionen,
    'treffer'   => $treffer,
    'kiDaten'   => function_exists('ki_fuer_dateien') && $kiIds ? ki_fuer_dateien('stan', $kiIds) : ['ergebnisse' => [], 'auftraege' => []],
    'textOffen' => stan_text_offen(),
    'title'     => (string)setting('stan_modul_name', 'STAN'),
    'versionen' => $versionen,
    'aktuell'   => $aktuell,
    'gezeigt'   => $gezeigt,
    'dateien'   => $dateien,
    'darf'      => $darf,
    'maxMb'     => (int)(stan_max_bytes() / 1024 / 1024),
]);
