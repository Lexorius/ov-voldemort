<?php
declare(strict_types=1);

/**
 * Standortübersicht für alle: durch Gebäude, Stockwerke, Räume klicken und
 * sehen, was dort steht oder liegt. Gezeigt wird nur, was die Person auch
 * in den jeweiligen Modulen sehen darf.
 */

$alle = standort_all();
$nachId = array_column($alle, null, 'id');
$id = get_int('id', 0);
$s = $id ? ($nachId[$id] ?? null) : null;
if ($id && !$s) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Platz gibt es nicht (mehr).']);
    return;
}

$fahrzeuge = can('view_vehicles')
    ? vehicle_query(['mit_platz' => 1, 'user_id' => (int)(current_user()['id'] ?? 0)])
    : [];
$zaehlerAlle = can('view_verbrauch')
    ? db_all('SELECT id, name, art, rolle, standort_id, bereich_id, is_active FROM meters WHERE standort_id IS NOT NULL OR bereich_id IS NOT NULL ORDER BY name')
    : [];
$funkgruppen = can('view_radios')
    ? db_all('SELECT id, name, standort_id, is_active FROM radio_groups WHERE standort_id IS NOT NULL ORDER BY name')
    : [];
$funk = can('view_radios')
    ? db_all('SELECT r.id, r.bezeichnung, r.funkrufname, r.is_active, r.standort_id AS eigener_standort_id,
                     COALESCE(r.standort_id, g.standort_id) AS standort_id, g.name AS gruppe_name
              FROM radios r LEFT JOIN radio_groups g ON g.id = r.group_id
              WHERE r.standort_id IS NOT NULL OR g.standort_id IS NOT NULL ORDER BY r.bezeichnung')
    : [];

$inventar = standort_inventar($fahrzeuge, $zaehlerAlle, $funkgruppen, $funk);
$baum = standort_baum($alle);
$kinder = [];
if ($s) {
    foreach ($alle as $r) {
        if ((int)($r['parent_id'] ?? 0) === (int)$s['id']) {
            $kinder[] = $r;
        }
    }
} else {
    foreach ($baum as $r) {
        unset($r['kinder'], $r['tiefe']);
        $kinder[] = $r;
    }
}
$summen = [];
foreach ($kinder as $k) {
    $summen[(int)$k['id']] = standort_inventar_summe((int)$k['id'], $alle, $inventar);
}

$crumbs = [];
if ($s) {
    foreach (array_reverse(standort_vorfahren((int)$s['id'], $alle)) as $vid) {
        $crumbs[] = $nachId[$vid];
    }
}

// Für die Fahrzeugkacheln am Platz: Titelbilder und Fristen
$hierFahrzeuge = $s ? ($inventar[(int)$s['id']]['fahrzeuge'] ?? []) : [];
$warnTage = setting_int('fahrzeug_frist_warnung_tage', 30);
$fzFristen = [];
foreach ($hierFahrzeuge as $v) {
    $fzFristen[(int)$v['id']] = vehicle_deadlines($v, $warnTage);
}

render('standorte', [
    'title'      => $s ? (string)$s['name'] : (string)setting('standort_modul_name', 'Standorte'),
    's'          => $s,
    'alle'       => $alle,
    'crumbs'     => $crumbs,
    'kinder'     => $kinder,
    'summen'     => $summen,
    'hier'       => $s ? ($inventar[(int)$s['id']] ?? []) : [],
    'darunter'   => $s ? standort_inventar_summe((int)$s['id'], $alle, $inventar) : [],
    'zustaendig' => $s && $zaehlerAlle ? standort_zustaendige_zaehler((int)$s['id'], $alle, $zaehlerAlle) : [],
    'fzTitelbilder' => $hierFahrzeuge ? vfile_covers(array_column($hierFahrzeuge, 'id')) : [],
    'fzFristen'  => $fzFristen,
    'titelbilder' => standort_titelbilder(array_merge($s ? [(int)$s['id']] : [], array_column($kinder, 'id'))),
    'zaehlung'   => standort_zaehlung($alle),
    'kinderZahl' => array_count_values(array_map(static fn($r) => (int)($r['parent_id'] ?? 0), $alle)),
    'darfPflegen' => in_array((string)(current_user()['role'] ?? ''), ['admin', 'leitung'], true),
]);
