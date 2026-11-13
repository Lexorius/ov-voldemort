<?php
declare(strict_types=1);

/*
 * Symbole aus einem GitHub-Repository holen – in drei Schritten, jeder nur auf Knopfdruck:
 * Repository nennen und prüfen, Paket wählen und Lizenz bestätigen, Ordner wählen und übernehmen.
 * Zwischen den Schritten liegt der Stand in der Sitzung; das Paket selbst liegt in der Ablage
 * und wird nach dem Übernehmen oder Verwerfen gelöscht.
 */
$user = require_login();
if (!can('manage_karten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Symbolbibliothek pflegt die Leitung.']);
    return;
}

karten_github_aufraeumen();
$stand = is_array($_SESSION['karten_github'] ?? null) ? $_SESSION['karten_github'] : null;
// Das Paket der Sitzung ist weg (aufgeräumt, anderer Server): von vorn
if ($stand && ($stand['schritt'] ?? '') === 'auswahl' && karten_github_zip_pfad((string)($stand['zip'] ?? '')) === null) {
    unset($_SESSION['karten_github']);
    $stand = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Holen und Übernehmen dauern länger als eine gewöhnliche Seite
    @set_time_limit(900);
    try {
        switch (post_str('action')) {
            case 'pruefen':
                $repo = karten_github_repo(post_str('repo'));
                if ($repo === null) {
                    flash('error', 'Bitte ein Repository in der Form „eigner/name" oder seine GitHub-Adresse angeben.');
                    break;
                }
                $_SESSION['karten_github'] = ['schritt' => 'quelle', 'info' => karten_github_info($repo)];
                break;

            case 'laden':
                $quelle = $stand['info']['quellen'][(int)post_int('quelle', -1)] ?? null;
                if (!$stand || !$quelle) {
                    flash('error', 'Bitte ein Paket wählen.');
                    break;
                }
                if (!post_bool('lizenz_ok')) {
                    flash('error', 'Bitte erst bestätigen, dass ihr die Lizenz geprüft habt – ohne das wird nichts geholt.');
                    break;
                }
                $zip = karten_github_laden($quelle);
                $inhalt = karten_github_zip_inhalt((string)karten_github_zip_pfad($zip));
                if (!$inhalt['ordner']) {
                    karten_github_zip_weg($zip);
                    flash('error', 'In diesem Paket liegt keine SVG und kein Bild.'
                        . ($quelle['art'] === 'zweig' ? ' Manche Repositorys führen im Quelltext nur Vorlagen – die fertigen Zeichen stecken dann im Release-Paket.' : ''));
                    break;
                }
                $_SESSION['karten_github'] = [
                    'schritt' => 'auswahl', 'info' => $stand['info'], 'zip' => $zip, 'quelle' => $quelle, 'inhalt' => $inhalt,
                    'lizenz'  => mb_substr(trim(post_str('lizenz')), 0, 80),
                ];
                audit('karten.github_geholt', 'karten_symbol', null, (string)$stand['info']['name'] . ' · ' . (string)$quelle['name']);
                break;

            case 'importieren':
                $pfad = $stand ? karten_github_zip_pfad((string)($stand['zip'] ?? '')) : null;
                if (!$stand || $pfad === null) {
                    flash('error', 'Das Paket liegt nicht mehr vor – bitte noch einmal holen.');
                    break;
                }
                $alle = array_keys($stand['inhalt']['ordner']);
                $gewaehlt = [];
                foreach ((array)($_POST['ordner'] ?? []) as $nr) {
                    if (is_scalar($nr) && isset($alle[(int)$nr])) {
                        $gewaehlt[] = (string)$alle[(int)$nr];
                    }
                }
                if (!$gewaehlt) {
                    flash('error', 'Bitte mindestens einen Ordner ankreuzen.');
                    break;
                }
                $typen = in_array(post_str('typen'), ['svg', 'bild', 'beides'], true) ? post_str('typen') : 'svg';
                $r = karten_github_importieren($pfad, $gewaehlt, $typen, post_str('vorsatz'), [
                    'repo' => (string)$stand['info']['name'], 'ref' => (string)$stand['quelle']['ref'], 'lizenz' => (string)$stand['lizenz'],
                ], $user);
                karten_github_zip_weg((string)$stand['zip']);
                unset($_SESSION['karten_github']);
                flash($r['neu'] > 0 ? 'success' : 'info', sprintf('%d Symbol(e) in die Bibliothek gelegt%s.', $r['neu'],
                    $r['doppelt'] > 0 ? sprintf(', %d waren schon da', $r['doppelt']) : ''));
                if ($r['abgelehnt']) {
                    $gruende = [];
                    foreach ($r['abgelehnt'] as $grund => $anzahl) {
                        $gruende[] = $anzahl . '× ' . $grund;
                    }
                    flash('warn', e('Nicht übernommen: ' . implode('; ', $gruende) . '. Zum Beispiel ' . implode(' · ', $r['beispiele'])));
                }
                if ($r['rest'] > 0) {
                    flash('warn', sprintf('%d weitere passen nicht in einen Durchgang (höchstens %d) – das Paket noch einmal holen und weniger Ordner wählen.',
                        $r['rest'], KARTEN_GITHUB_MAX_IMPORT));
                }
                redirect_route('karten_symbole');
                // kein break

            case 'verwerfen':
                if ($stand && !empty($stand['zip'])) {
                    karten_github_zip_weg((string)$stand['zip']);
                }
                unset($_SESSION['karten_github']);
                break;
        }
    } catch (KartenGithubException $ex) {
        flash('error', e($ex->getMessage()));
    }
    redirect_route('karten_github');
}

render('karten_github', [
    'title'  => 'Symbole von GitHub holen',
    'stand'  => $stand,
    'maxMb'  => (int)round(KARTEN_GITHUB_MAX_BYTES / 1048576),
]);
