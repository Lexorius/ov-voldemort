<?php
declare(strict_types=1);

$user = current_user();
$jahr = haushaltsjahr();

// Offene Wünsche
$offeneWuensche = wish_query(['offen' => 1, 'sort' => 'prio']);
$statsW = wish_stats($offeneWuensche);

// Budgets des Haushaltsjahres inkl. Verplanung
$budgets = db_all(
    'SELECT b.*, k.label AS kategorie_label, f.label AS fachgruppe_label,
            (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM wishes w
              LEFT JOIN list_items s ON s.id = w.status_id
             WHERE w.budget_id = b.id AND COALESCE(s.is_final,0) = 0) AS verplant,
            (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM wishes w
              LEFT JOIN list_items s ON s.id = w.status_id
             WHERE w.budget_id = b.id AND s.slug = \'beschafft\') AS ausgegeben
     FROM budgets b
     LEFT JOIN list_items k ON k.id = b.kategorie_id
     LEFT JOIN list_items f ON f.id = b.fachgruppe_id
     WHERE b.jahr = ? AND b.is_active = 1
     ORDER BY b.name',
    [$jahr]
);
$budgetVerplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));
$zahlen = budget_jahr_zahlen($jahr);

// Meine Aufgaben
$meineTodos = todo_query(['mine' => $user, 'offen' => 1]);
$ueberfaellig = array_filter(
    $meineTodos,
    static fn($t) => $t['faellig_am'] && $t['faellig_am'] < date('Y-m-d')
);

// Fahrzeuge mit Handlungsbedarf: Frist abgelaufen oder bald fällig, oder Ausfall
$fahrzeugWarnungen = [];
if (can('view_vehicles')) {
    $warnTage = setting_int('fahrzeug_frist_warnung_tage', 30);
    foreach (vehicle_query(['nur_aktive' => 1]) as $v) {
        $offen = array_values(array_filter(vehicle_deadlines($v, $warnTage), static fn($f) => $f['status'] !== 'ok'));
        $ausfall = (int)db_val(
            'SELECT COUNT(*) FROM vehicle_orders o LEFT JOIN list_items s ON s.id = o.status_id
             WHERE o.vehicle_id = ? AND o.ausfall = 1 AND COALESCE(s.is_final,0) = 0',
            [(int)$v['id']],
            0
        );
        if ($offen || $ausfall > 0) {
            $fahrzeugWarnungen[] = ['fahrzeug' => $v, 'fristen' => $offen, 'ausfall' => $ausfall];
        }
    }
}

$zweiWochenBis = date('Y-m-d', time() + 13 * 86400);
$alleEintraege = kalender_sammeln(date('Y-m-d'), date('Y-m-d', time() + 14 * 86400), $user);
$termine = array_values(array_filter($alleEintraege, static fn($e) => $e['quelle'] !== 'feiertag'));

// Fahrzeuge, die jetzt oder noch diese Woche in Terminen stecken – Vergangenes interessiert auf der Startseite nicht
$woche = kalender_woche(date('Y-m-d'));
$woche[0] = date('Y-m-d');
$fahrzeugeWoche = can('view_vehicles')
    ? kalender_konflikte_markieren(kalender_fahrzeuge_ab(kalender_fahrzeuge_zeitraum($woche[0], $woche[1], $user), date('Y-m-d H:i:s')))
    : [];

render('dashboard', [
    'fahrzeugeWoche' => $fahrzeugeWoche,
    'woche'          => $woche,
    'fzBilder'       => $fahrzeugeWoche && function_exists('vfile_covers') ? vfile_covers(array_map(static fn($g) => (int)$g['fahrzeug']['id'], $fahrzeugeWoche)) : [],
    'zweiWochen'     => ['von' => date('Y-m-d'), 'bis' => $zweiWochenBis, 'jeTag' => kalender_je_tag($alleEintraege, date('Y-m-d'), $zweiWochenBis)],
    'darfTermin'     => can('create_kalender'),
    'title'          => 'Übersicht',
    'termine'        => array_slice($termine, 0, 6),
    'termineGesamt'  => count($termine),
    'user'           => $user,
    'jahr'           => $jahr,
    'wuensche'       => array_slice($offeneWuensche, 0, 6),
    'statsW'         => $statsW,
    'budgets'        => $budgets,
    'zahlen'         => $zahlen,
    'budgetVerplant' => $budgetVerplant,
    'todos'          => array_slice($meineTodos, 0, 8),
    'todosGesamt'    => count($meineTodos),
    'ueberfaellig'   => count($ueberfaellig),
    'zuBestellen'    => wish_query(['status_slug' => 'freigegeben']),
    'fahrzeuge'      => $fahrzeugWarnungen,
]);
