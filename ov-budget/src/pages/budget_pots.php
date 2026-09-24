<?php
declare(strict_types=1);

/** Budgettöpfe verwalten: anlegen, ändern, stilllegen, aus dem Vorjahr übernehmen */

$user = require_role('admin', 'leitung');

$jahre = budget_years_known();
$jahr = get_int('jahr') ?: ($jahre[0] ?? setting_int('haushaltsjahr', (int)date('Y')));
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'kopieren':
            $von = post_int('von', $jahr - 1) ?? $jahr - 1;
            $anzahl = budget_pot_copy($von, $jahr);
            flash($anzahl > 0 ? 'success' : 'info', $anzahl > 0
                ? sprintf('%d Topf/Töpfe aus %d übernommen. Bitte die Beträge prüfen.', $anzahl, $von)
                : sprintf('Aus %d gab es nichts zu übernehmen – gleichnamige Töpfe bleiben unberührt.', $von));
            redirect_route('budget_pots', ['jahr' => $jahr]);
            break;

        case 'aktiv':
            $topf = db_row('SELECT * FROM budgets WHERE id = ?', [post_int('id')]);
            if ($topf) {
                $neu = (int)$topf['is_active'] === 1 ? 0 : 1;
                db_update('budgets', ['is_active' => $neu], 'id = ?', [(int)$topf['id']]);
                audit('budget.bearbeitet', 'budget', (int)$topf['id'],
                    $neu === 1 ? 'wieder aktiv' : 'stillgelegt');
                flash('success', $neu === 1
                    ? sprintf('„%s" ist wieder aktiv.', (string)$topf['name'])
                    : sprintf('„%s" ist stillgelegt – der Topf lässt sich nicht mehr auswählen.', (string)$topf['name']));
            }
            redirect_route('budget_pots', ['jahr' => $jahr]);
            break;
    }
}

$toepfe = budget_pots($jahr);

render('budget_pots', [
    'title'     => 'Budgettöpfe',
    'jahr'      => $jahr,
    'jahre'     => $jahre,
    'toepfe'    => $toepfe,
    'summe'     => array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $toepfe)),
    'gesamt'    => budget_year_betrag($jahr),
    'vorjahr'   => (int)db_val('SELECT COUNT(*) FROM budgets WHERE jahr = ? AND is_active = 1', [$jahr - 1], 0),
]);
