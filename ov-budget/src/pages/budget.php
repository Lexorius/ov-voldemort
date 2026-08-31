<?php
declare(strict_types=1);

$jahre = budget_years_known();
$jahr = get_int('jahr') ?: ($jahre[0] ?? setting_int('haushaltsjahr', (int)date('Y')));
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

// Budgettöpfe samt Verplanung aus Wünschen und tatsächlichen Ausgaben
$budgets = db_all(
    'SELECT b.*, k.label AS kategorie_label, f.label AS fachgruppe_label,
            (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM wishes w
              LEFT JOIN list_items s ON s.id = w.status_id
             WHERE w.budget_id = b.id AND COALESCE(s.is_final,0) = 0) AS verplant,
            (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM wishes w
              LEFT JOIN list_items s ON s.id = w.status_id
             WHERE w.budget_id = b.id AND COALESCE(s.is_final,0) = 1) AS abgeschlossen,
            (SELECT COUNT(*) FROM wishes w WHERE w.budget_id = b.id) AS wuensche
     FROM budgets b
     LEFT JOIN list_items k ON k.id = b.kategorie_id
     LEFT JOIN list_items f ON f.id = b.fachgruppe_id
     WHERE b.jahr = ?
     ORDER BY b.is_active DESC, b.name',
    [$jahr]
);

// Offene Wünsche ohne Topf – die Lücke in der Planung
$ohneTopf = db_all(
    'SELECT w.id, w.bezeichnung, w.netto_gesamt, f.label AS fachgruppe_label,
            d.label AS dring_label, d.color AS dring_color
     FROM wishes w
     LEFT JOIN list_items s ON s.id = w.status_id
     LEFT JOIN list_items f ON f.id = w.fachgruppe_id
     LEFT JOIN list_items d ON d.id = w.dringlichkeit_id
     WHERE w.budget_id IS NULL AND COALESCE(s.is_final,0) = 0
     ORDER BY d.weight DESC, w.netto_gesamt DESC'
);

render('budget', [
    'title'          => (string)setting('budget_modul_name', 'Budget'),
    'jahr'           => $jahr,
    'jahre'          => $jahre,
    'budgets'        => $budgets,
    'ohneTopf'       => $ohneTopf,
    'jahresbudget'    => budget_year($jahr),
    'ausgabenBrutto'  => expense_total($jahr),
    'ausgabenNetto'   => expense_total($jahr, 'betrag_netto'),
    'einnahmenBrutto' => income_total($jahr),
    'einnahmenNetto'  => income_total($jahr, 'betrag_netto'),
    'kategorien'      => expense_by_category($jahr),
    'einnahmeKategorien' => expense_by_category($jahr, 'einnahme'),
    'monate'          => expense_by_month($jahr),
    'monateEin'       => expense_by_month($jahr, 'einnahme'),
    'jeTopf'          => expense_by_budget($jahr),
    'letzte'          => expense_query(['jahr' => $jahr, 'limit' => 6]),
]);
