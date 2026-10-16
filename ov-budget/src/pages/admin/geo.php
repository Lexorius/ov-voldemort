<?php
declare(strict_types=1);

/** Entfernungen (!! TEST !!): Unterkunft wählen, Warteschlange ansehen, Dienststellen vormerken */
$me = require_role('admin');

$fehler = '';
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'heim':
            $id = post_int('standort_id');
            $s = $id > 0 ? standort_find($id) : null;
            if ($id > 0 && (!$s || $s['geo_lat'] === null)) {
                $fehler = 'Dieser Platz hat keine GPS-Koordinaten.';
                break;
            }
            state_save('geo_heim_standort_id', (string)$id);
            $hinweis = $id > 0 ? 'Ausgangspunkt: ' . (string)$s['name'] : 'Kein Ausgangspunkt – Entfernungen werden nicht gerechnet.';
            audit('geo.heim', 'standort', $id > 0 ? $id : null, $hinweis);
            break;

        case 'anfrage':
            if (!geo_aktiv()) {
                $fehler = 'Die Entfernungsrechnung ist ausgeschaltet.';
                break;
            }
            if (!geo_pause_vorbei()) {
                $fehler = sprintf('Die Pause ist noch nicht vorbei – nächste Anfrage frühestens in %d Sekunden.', geo_status()['naechste_in']);
                break;
            }
            $r = geo_warteschlange_abarbeiten();
            if ($r['art'] === null) {
                $hinweis = 'Nichts offen.';
            } elseif ($r['ok']) {
                $hinweis = $r['text'];
            } else {
                $fehler = $r['text'];
            }
            audit('geo.anfrage', 'geo', null, $r['text']);
            break;

        case 'dienststellen':
            if (!geo_aktiv()) {
                $fehler = 'Die Entfernungsrechnung ist ausgeschaltet.';
                break;
            }
            $n = geo_dienststellen_vormerken();
            $hinweis = sprintf('%d Anschrift(en) vorgemerkt. Bei einer Anfrage je %d Sekunden dauert das etwa %d Minuten – plus die Strecken danach.', $n, geo_pause_sek(), (int)ceil($n * geo_pause_sek() / 60));
            audit('geo.dienststellen', 'geo', null, (string)$n);
            break;

        case 'erneut':
            $n = geo_erneut_versuchen();
            $hinweis = sprintf('%d Eintrag/Einträge werden erneut versucht.', $n);
            audit('geo.erneut', 'geo', null, (string)$n);
            break;
    }
}

$alle = standort_all();
$mitKoordinaten = [];
foreach ($alle as $s) {
    if ($s['geo_lat'] !== null && $s['geo_lng'] !== null) {
        $mitKoordinaten[] = ['id' => (int)$s['id'], 'pfad' => standort_pfad((int)$s['id'], $alle)];
    }
}

render('admin/geo', [
    'title'        => 'Entfernungen (!! TEST !!)',
    'fehler'       => $fehler,
    'hinweis'      => $hinweis,
    'aktiv'        => geo_aktiv(),
    'heim'         => geo_heim(),
    'plaetze'      => $mitKoordinaten,
    'status'       => geo_status(),
    'fehlerliste'  => geo_fehler_liste(15),
    'pause'        => geo_pause_sek(),
    'osrmUrl'      => geo_osrm_url(),
    'nominatimUrl' => geo_nominatim_url(),
]);
