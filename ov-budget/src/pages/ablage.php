<?php
declare(strict_types=1);

/*
 * Dateiablage: links der Baum der Kategorien, rechts die gewählte Kategorie mit
 * ihren Unterkategorien und Dokumenten. ?k=… wählt die Kategorie, ?q=… sucht
 * über alles. Hochladen, umbenennen, verschieben und entfernen gibt es nur in
 * den eigenen Kategorien – die gesperrten kommen aus den Modulen.
 */
$user = require_login();
if (!can('view_ablage')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Dateiablage ist nur für die Leitung sichtbar.']);
    return;
}

$verwalten = can('manage_ablage');
$hochladen = can('upload_ablage');
$eigene = ablage_kategorien();
$baum = ablage_baum_fuer($user, $eigene);

$key = get_str('k');
$knoten = $key !== '' ? ($baum['knoten'][$key] ?? null) : null;
if ($key !== '' && !$knoten) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Kategorie gibt es nicht (mehr) – oder sie ist dir nicht zugänglich.']);
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ziel = $knoten ? ['k' => $key] : [];
    switch (post_str('action')) {
        case 'hochladen':
            if (!$knoten || $knoten['auto'] || !$hochladen) {
                flash('error', $knoten && $knoten['auto']
                    ? 'Diese Kategorie kommt aus einem Modul – dort wird hochgeladen.'
                    : 'Zum Ablegen fehlen dir die Rechte.');
                break;
            }
            [$anzahl, $fehler] = afile_store_uploads((int)$knoten['id'], 'dateien', post_str('titel'), $user);
            foreach ($fehler as $f) {
                flash('error', $f);
            }
            if ($anzahl > 0) {
                flash('success', ($anzahl === 1 ? 'Ein Dokument' : $anzahl . ' Dokumente') . ' abgelegt.'
                    . (function_exists('ki_modul_aktiv') && ki_modul_aktiv('ablage') ? ' Zusammenfassung und Schlagwörter kommen in den nächsten Minuten.' : ''));
            }
            break;

        case 'datei_aendern':
        case 'datei_loeschen':
            $datei = afile_find(post_int('datei_id'));
            // Es gibt die Datei nur, wenn ihre Kategorie im Baum der Person hängt
            if (!$datei || !isset($baum['knoten']['k' . (int)$datei['kategorie_id']])) {
                flash('error', 'Dieses Dokument gibt es nicht (mehr).');
                break;
            }
            if (!afile_darf_aendern($datei, $user)) {
                flash('error', 'Dieses Dokument darfst du nicht ändern – das kann, wer es abgelegt hat, und die Leitung.');
                break;
            }
            $name = (string)($datei['titel'] ?: $datei['orig_name']);
            if (post_str('action') === 'datei_loeschen') {
                afile_delete($datei);
                flash('success', '„' . $name . '" entfernt.');
                break;
            }
            $neu = (int)post_int('kategorie_id', (int)$datei['kategorie_id']);
            if (!isset($baum['knoten']['k' . $neu])) {
                flash('error', 'Die gewählte Kategorie gibt es nicht (mehr).');
                break;
            }
            afile_aendern($datei, post_str('titel'), $neu);
            flash('success', $neu !== (int)$datei['kategorie_id']
                ? '„' . $name . '" nach „' . ablage_pfad_text('k' . $neu, $baum) . '" verschoben.'
                : 'Gespeichert.');
            $ziel = ['k' => 'k' . $neu];
            break;
    }
    redirect_route('ablage', $ziel);
}

$q = trim(get_str('q'));
if ($q !== '') {
    $dateien = ablage_suche($q, $baum);
} elseif ($knoten) {
    $dateien = ablage_im_baum(ablage_dateien_von($knoten), $baum);
} else {
    $dateien = ablage_neueste($baum);
}
$dateien = ablage_rechte_setzen($dateien, $user);

render('ablage', [
    'title'     => $knoten ? (string)$knoten['name'] : (string)setting('ablage_modul_name', 'Dateiablage'),
    'baum'      => $baum,
    'knoten'    => $knoten,
    'key'       => $knoten ? $key : '',
    'q'         => $q,
    'dateien'   => $dateien,
    'kiDaten'   => ablage_ki_daten($dateien),
    'eigene'    => $eigene,
    'verwalten' => $verwalten,
    'hochladen' => $hochladen,
    'tags'      => $q === '' && !$knoten && function_exists('ki_haeufige_tags') ? ki_haeufige_tags(30) : [],
    'maxMb'     => (int)round(upload_max_bytes() / 1048576),
    'typen'     => array_values(array_unique(array_merge(vfile_document_types(), VFILE_BILD_TYPEN))),
]);
