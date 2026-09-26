<?php
declare(strict_types=1);

$user = current_user();
$jahr = setting_int('haushaltsjahr', (int)date('Y'));

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

render('dashboard', [
    'title'          => 'Übersicht',
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
