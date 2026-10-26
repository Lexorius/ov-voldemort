<?php
declare(strict_types=1);

/** Getränke: Bedarf, Lager, Bestellungen, Getränkearten und Lieferanten auf einer Seite */
$user = require_login();
if (!can('view_getraenke')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Das Getränkemodul ist für dich nicht freigegeben.']);
    return;
}

$getraenke = getraenke_alle(false);
$aktive = array_values(array_filter($getraenke, static fn($g) => (int)$g['is_active'] === 1));
$bestand = getraenke_bestand_alle();
$meldungen = getraenke_meldungen_offen();
$lieferanten = getraenke_lieferanten(false);
$nachLieferant = [];
foreach ($lieferanten as $l) {
    $nachLieferant[(int)$l['id']] = $l;
}

// Bedarf je Lieferant: offene Meldungen und Mindestbestand
$summe = getraenke_bestand_summe($bestand);
$bedarf = [];
foreach ($nachLieferant as $lid => $l) {
    $vorschlag = getraenke_bestellvorschlag($lid, $aktive, $summe, $meldungen);
    if ($vorschlag) {
        $bedarf[$lid] = $vorschlag;
    }
}

render('getraenke', [
    'title'        => (string)setting('getraenke_modul_name', 'Getränke'),
    'getraenke'    => $getraenke,
    'gruppen'      => getraenke_nach_lieferant($getraenke),
    'lieferanten'  => $nachLieferant,
    'matrix'       => getraenke_bestand_matrix($aktive, $bestand),
    'summe'        => $summe,
    'meldungen'    => $meldungen,
    'bedarf'       => $bedarf,
    'offen'        => getraenke_bestellungen(['entwurf', 'bestellt', 'geliefert']),
    'erledigt'     => array_slice(getraenke_bestellungen(['abgerechnet']), 0, 10),
    'lagerplaetze' => getraenke_lagerplaetze(),
    'plaetzeAlle'  => standort_all(true),
    'darf'         => can('manage_getraenke'),
    'mailAktiv'    => mail_eingerichtet(),
    'connectoren'  => connector_liste('getraenke'),
]);
