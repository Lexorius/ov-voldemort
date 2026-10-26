<?php
declare(strict_types=1);

/** Bestellung als Entwurf anlegen oder ändern – vorgefüllt aus Bedarf und Meldungen */
$user = require_login();
if (!can('manage_getraenke')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('getraenke');
}

$id = get_int('id');
$b = $id ? getraenke_bestellung_find($id) : null;
if ($id && !$b) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Bestellung gibt es nicht (mehr).']);
    return;
}
if ($b && (string)$b['status'] !== 'entwurf') {
    flash('warn', 'Nur ein Entwurf lässt sich ändern.');
    redirect_route('getraenke_bestellung', ['id' => (int)$b['id']]);
}

$getraenke = getraenke_alle(true);
$errors = [];
$pos = [];
$lieferantId = $b ? (int)$b['lieferant_id'] : get_int('lieferant_id');
$standortId = $b ? (int)($b['standort_id'] ?? 0) : 0;
$notiz = $b ? (string)($b['notiz'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = getraenke_bestellung_save_from_post($b, $user);
    if ($neu) {
        flash('success', $b ? 'Bestellung gespeichert.' : 'Bestellung als Entwurf angelegt – jetzt prüfen und verschicken.');
        redirect_route('getraenke_bestellung', ['id' => $neu]);
    }
    $lieferantId = post_int('lieferant_id');
    $standortId = post_int('standort_id');
    $notiz = post_str('notiz');
    foreach ((array)($_POST['pos'] ?? []) as $gid => $n) {
        $pos[(int)$gid] = (int)$n;
    }
} elseif ($b) {
    foreach (getraenke_positionen((int)$b['id']) as $p) {
        $pos[(int)$p['getraenk_id']] = (int)$p['anzahl'];
    }
} elseif ($lieferantId > 0) {
    // Vorschlag aus Mindestbestand und offenen Meldungen
    $pos = getraenke_bestellvorschlag($lieferantId, $getraenke, getraenke_bestand_summe(getraenke_bestand_alle()), getraenke_meldungen_offen());
}

render('getraenke_bestellung_edit', [
    'title'       => $b ? 'Bestellung ' . (string)$b['nummer'] . ' bearbeiten' : 'Bestellung anlegen',
    'b'           => $b,
    'errors'      => $errors,
    'lieferanten' => getraenke_lieferanten(true),
    'lieferantId' => $lieferantId,
    'standortId'  => $standortId,
    'notiz'       => $notiz,
    'getraenke'   => $getraenke,
    'pos'         => $pos,
    'summe'       => getraenke_bestand_summe(getraenke_bestand_alle()),
    'plaetze'     => standort_all(true),
]);
