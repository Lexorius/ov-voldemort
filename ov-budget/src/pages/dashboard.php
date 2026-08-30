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
$budgetSumme = array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $budgets));
$budgetVerplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));

// Meine Aufgaben
$meineTodos = todo_query(['mine' => $user, 'offen' => 1]);
$ueberfaellig = array_filter(
    $meineTodos,
    static fn($t) => $t['faellig_am'] && $t['faellig_am'] < date('Y-m-d')
);

render('dashboard', [
    'title'          => 'Übersicht',
    'user'           => $user,
    'jahr'           => $jahr,
    'wuensche'       => array_slice($offeneWuensche, 0, 6),
    'statsW'         => $statsW,
    'budgets'        => $budgets,
    'budgetSumme'    => $budgetSumme,
    'budgetVerplant' => $budgetVerplant,
    'todos'          => array_slice($meineTodos, 0, 8),
    'todosGesamt'    => count($meineTodos),
    'ueberfaellig'   => count($ueberfaellig),
]);
