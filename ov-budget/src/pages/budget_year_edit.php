<?php
declare(strict_types=1);

require_role('admin', 'leitung');

$jahr = get_int('jahr') ?: setting_int('haushaltsjahr', (int)date('Y'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jahr = post_int('jahr', (int)date('Y')) ?? (int)date('Y');
    $betrag = post_dec('betrag');

    if ($jahr < 2000 || $jahr > 2100) {
        $errors[] = 'Bitte ein gültiges Haushaltsjahr angeben.';
    }
    if ($betrag < 0) {
        $errors[] = 'Der Betrag darf nicht negativ sein.';
    }

    if (!$errors) {
        budget_year_save($jahr, $betrag, post_str('beschreibung'), post_bool('is_active'));
        flash('success', 'Jahresbudget gespeichert.');
        redirect_route('budget', ['jahr' => $jahr]);
    }
}

$eintrag = budget_year($jahr) ?? [
    'jahr' => $jahr, 'betrag' => '', 'beschreibung' => '', 'is_active' => 1,
];

render('budget_year_edit', [
    'title'    => 'Jahresbudget',
    'eintrag'  => $eintrag,
    'errors'   => $errors,
    'ausgaben' => expense_total($jahr),
]);
