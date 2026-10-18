<?php
declare(strict_types=1);

/** Hochrechnung aufs Jahresende: wiederkehrende Ausgaben je Kategorie plus Energie und Wasser aus den Zählern */
if (!can('view_expenses')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Buchungen sind nur für die Leitung sichtbar.']);
    return;
}

$jahre = budget_years_known();
$jahr = get_int('jahr') ?: haushaltsjahr();
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can('manage_budget')) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Hochrechnung stellt nur die Leitung ein.']);
        return;
    }
    $n = 0;
    foreach (prognose_aus_post($_POST) as $id => $e) {
        prognose_einstellung_speichern($jahr, $id, $e['art'], $e['betrag'], $e['notiz']);
        $n++;
    }
    audit('prognose.gespeichert', 'budget', $jahr, $n . ' Kategorie(n)');
    flash('success', 'Hochrechnung gespeichert.');
    redirect_route('budget_prognose', ['jahr' => $jahr]);
}

render('budget_prognose', [
    'title'    => 'Hochrechnung ' . $jahr,
    'jahr'     => $jahr,
    'jahre'    => $jahre,
    'prognose' => prognose_jahr($jahr),
    'darf'     => can('manage_budget'),
]);
