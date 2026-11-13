<?php
declare(strict_types=1);

/**
 * Dubletten im Kontaktmodul: Liste der Verdachtsgruppen, „keine Dublette"
 * merken, Zusammenführen mit Auswahl je Feld.
 */

$user = require_role('admin', 'leitung');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {

        case 'ignore':
            $ids = wahl_ids('ids');
            if (count($ids) >= 2) {
                $n = kontakt_dubletten_ignorieren($ids, $user);
                audit('kontakt.keine_dublette', 'contact', null, implode(', ', array_map(static fn($i) => '#' . $i, $ids)));
                flash('success', $n > 0 ? 'Gemerkt – diese Kontakte gelten nicht mehr als Dubletten.' : 'Das war schon gemerkt.');
            }
            redirect_route('contacts_dubletten');
            // no break

        case 'unignore':
            $a = post_int('a');
            $b = post_int('b');
            if ($a && $b) {
                kontakt_dubletten_wieder_pruefen($a, $b);
                flash('info', 'Das Paar wird wieder geprüft.');
            }
            redirect_route('contacts_dubletten');
            // no break

        case 'merge':
            $ids = wahl_ids('ids');
            $ziel = post_int('ziel') ?? 0;
            if (count($ids) < 2 || !in_array($ziel, $ids, true)) {
                flash('error', 'Bitte mindestens zwei Kontakte und den verbleibenden Kontakt wählen.');
                redirect_route('contacts_dubletten');
            }
            $wahl = [];
            foreach ((array)($_POST['feld'] ?? []) as $feld => $quelle) {
                $wahl[(string)$feld] = $quelle === 'alle' ? 'alle' : (int)$quelle;
            }
            try {
                $ergebnis = kontakt_dubletten_zusammenfuehren($ziel, $ids, $wahl, $user);
            } catch (Throwable $ex) {
                error_log('Kontakte zusammenführen fehlgeschlagen: ' . $ex->getMessage());
                flash('error', 'Das Zusammenführen ist fehlgeschlagen, es wurde nichts geändert. '
                    . (app_config('debug', false) ? $ex->getMessage() : 'Details stehen im Protokoll.'));
                redirect_route('contacts_dubletten');
            }
            flash('success', sprintf(
                '„%s" zusammengeführt: %d Kontakt%s gelöscht, %d Verweis%s umgehängt.',
                $ergebnis['name'],
                $ergebnis['geloescht'],
                $ergebnis['geloescht'] === 1 ? '' : 'e',
                $ergebnis['umgehaengt'],
                $ergebnis['umgehaengt'] === 1 ? '' : 'e'
            ));
            redirect_route('contacts_dubletten');
            // no break
    }
}

/* ---- Zusammenführen vorbereiten ---- */

$idsRoh = array_values(array_unique(array_filter(array_map('intval', explode(',', get_str('ids'))))));
if (count($idsRoh) >= 2) {
    $kontakte = [];
    foreach ($idsRoh as $id) {
        $c = contact_find($id);
        if ($c) {
            $kontakte[$id] = $c;
        }
    }
    if (count($kontakte) < 2) {
        flash('warn', 'Mindestens einer dieser Kontakte gibt es nicht mehr.');
        redirect_route('contacts_dubletten');
    }
    $verweise = kontakt_dubletten_verweise(array_keys($kontakte));
    $ziel = get_int('ziel');
    if (!$ziel || !isset($kontakte[$ziel])) {
        $ziel = dublette_ziel_vorschlag($kontakte, $verweise);
    }
    $extraFelder = contact_extra_fields();

    render('contacts_dubletten_merge', [
        'title'       => 'Kontakte zusammenführen',
        'kontakte'    => $kontakte,
        'ziel'        => $ziel,
        'verweise'    => $verweise,
        'vorschlag'   => dublette_vorschlag($kontakte, $ziel, $extraFelder),
        'extraFelder' => $extraFelder,
    ]);
    return;
}

/* ---- Liste ---- */

$ignoriert = kontakt_dubletten_ignoriert();
$gruppen = kontakt_dubletten_finden(kontakt_dubletten_kandidaten(), $ignoriert);

// Vollständige Zeilen samt Kategorie und Verteilerzahl für alle beteiligten Kontakte
$alleIds = [];
foreach ($gruppen as $g) {
    array_push($alleIds, ...$g['ids']);
}
foreach ($ignoriert as [$a, $b]) {
    array_push($alleIds, $a, $b);
}
$alleIds = array_values(array_unique($alleIds));
$zeilen = [];
if ($alleIds) {
    $platz = implode(',', array_fill(0, count($alleIds), '?'));
    foreach (db_all(
        'SELECT c.*, ka.label AS kategorie_label, ka.color AS kategorie_color,
                (SELECT COUNT(*) FROM contact_group_members m WHERE m.contact_id = c.id) AS verteiler
         FROM contacts c LEFT JOIN list_items ka ON ka.id = c.kategorie_id
         WHERE c.id IN (' . $platz . ')',
        $alleIds
    ) as $r) {
        $zeilen[(int)$r['id']] = $r;
    }
}

$zaehler = ['sicher' => 0, 'wahrscheinlich' => 0, 'moeglich' => 0, 'kontakte' => 0];
foreach ($gruppen as $g) {
    $zaehler[$g['stufe']]++;
    $zaehler['kontakte'] += count($g['ids']);
}

render('contacts_dubletten', [
    'title'     => 'Dubletten',
    'gruppen'   => $gruppen,
    'zeilen'    => $zeilen,
    'zaehler'   => $zaehler,
    'ignoriert' => $ignoriert,
    'gesamt'    => (int)db_val('SELECT COUNT(*) FROM contacts', [], 0),
]);
