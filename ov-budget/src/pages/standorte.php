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

// Für die Fahrzeugkacheln am Platz: Titelbilder und Fristen – und die
// Fahrzeuge direkt an den Unterplätzen, damit die Kästen sie gleich zeigen
$hierFahrzeuge = $s ? ($inventar[(int)$s['id']]['fahrzeuge'] ?? []) : [];
$kindFahrzeuge = [];
foreach ($kinder as $k) {
    if (!empty($inventar[(int)$k['id']]['fahrzeuge'])) {
        $kindFahrzeuge[(int)$k['id']] = $inventar[(int)$k['id']]['fahrzeuge'];
    }
}
$bildIds = array_column($hierFahrzeuge, 'id');
foreach ($kindFahrzeuge as $liste) {
    $bildIds = array_merge($bildIds, array_column($liste, 'id'));
}
$warnTage = setting_int('fahrzeug_frist_warnung_tage', 30);
$fzFristen = [];
foreach ($hierFahrzeuge as $v) {
    $fzFristen[(int)$v['id']] = vehicle_deadlines($v, $warnTage);
}

// Energie und Kosten dieses Bereichs – aus den zuständigen Zählern, auch geerbt vom Gebäude
$energie = null;
if ($s && can('view_verbrauch') && function_exists('verbrauch_bereiche_jahr') && $zaehlerAlle) {
    $jahr = (int)date('Y');
    $b = verbrauch_bereiche_jahr($jahr);
    $treffer = $b['nach_id'][(int)$s['id']] ?? null;
    $geerbtVon = null;
    if ($treffer === null || !$treffer['eigen']) {
        foreach (standort_vorfahren((int)$s['id'], $alle) as $vid) {
            if (!empty($b['nach_id'][$vid]['eigen'])) {
                $treffer = $b['nach_id'][$vid];
                $geerbtVon = (string)$treffer['name'];
                break;
            }
        }
    }
    if ($treffer !== null && $treffer['eigen']) {
        $teile = [];
        foreach (METER_ARTEN as $key => $a) {
            $w = $treffer['je_art'][$key];
            if ($w['menge'] > 0 || $w['kosten'] > 0) {
                $teile[] = ['label' => $a['label'], 'text' => menge($w['menge'], $w['einheit'], 0) . ($w['kosten'] > 0 ? ' · ' . money($w['kosten']) : '')];
            }
        }
        $energie = ['jahr' => $jahr, 'teile' => $teile, 'kosten' => $treffer['kosten'] > 0 ? money($treffer['kosten']) : '',
                    'geerbt_von' => $geerbtVon, 'anteil' => $treffer['anteil'] !== null ? number_format($treffer['anteil'], 1, ',', '.') . ' % von ' . $treffer['anteil_von'] : ''];
    }
}

render('standorte', [
    'energie'    => $energie,
    'title'      => $s ? (string)$s['name'] : (string)setting('standort_modul_name', 'Standorte'),
    's'          => $s,
    'alle'       => $alle,
    'crumbs'     => $crumbs,
    'kinder'     => $kinder,
    'summen'     => $summen,
    'hier'       => $s ? ($inventar[(int)$s['id']] ?? []) : [],
    'darunter'   => $s ? standort_inventar_summe((int)$s['id'], $alle, $inventar) : [],
    'zustaendig' => $s && $zaehlerAlle ? standort_zustaendige_zaehler((int)$s['id'], $alle, $zaehlerAlle) : [],
    'fzTitelbilder' => $bildIds ? vfile_covers($bildIds) : [],
    'kindFahrzeuge' => $kindFahrzeuge,
    'fzFristen'  => $fzFristen,
    'titelbilder' => standort_titelbilder(array_merge($s ? [(int)$s['id']] : [], array_column($kinder, 'id'))),
    'plan'       => $s && !empty($s['plan_bild_id']) ? standort_plan_bild($s, standort_bilder((int)$s['id'])) : null,
    'planMarker' => $s ? standort_plan_marker($kinder) : [],
    'zaehlung'   => standort_zaehlung($alle),
    'kinderZahl' => array_count_values(array_map(static fn($r) => (int)($r['parent_id'] ?? 0), $alle)),
    'darfPflegen' => in_array((string)(current_user()['role'] ?? ''), ['admin', 'leitung'], true),
]);
