<?php
declare(strict_types=1);

/** Anfrage an die Regionalstelle: Welche Abrechnungen warten – als E-Mail-Vorlage */

if (!can('view_expenses')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Buchungen sind nur für die Leitung sichtbar.']);
    return;
}

$user = current_user();
$jahr = get_int('jahr') ?: haushaltsjahr();
$ab = get_str('ab') === '0' ? 0 : 30;   // Vorgabe: nur, was länger als 30 Tage wartet

// Wartende Abrechnungen dieses und des Vorjahres – alte Fälle sind die wichtigsten
$wartend = array_merge(einnahmen_wartend($jahr)['liste'], einnahmen_wartend($jahr - 1)['liste']);
usort($wartend, static fn($a, $b) => $b['alter']['tage'] <=> $a['alter']['tage']);
$kandidaten = array_values(array_filter($wartend, static fn($e) => $e['alter']['tage'] >= $ab));

// Auswahl: ohne Angabe alle Kandidaten
$gewaehlt = isset($_GET['sel']) ? array_map('intval', (array)$_GET['sel']) : array_column($kandidaten, 'id');
$ausgewaehlt = array_values(array_filter($kandidaten, static fn($e) => in_array((int)$e['id'], $gewaehlt, true)));

$funktionen = array_values(array_filter(array_map(static fn($id) => list_label((int)$id, ''), $user['functions'] ?? user_functions((int)$user['id']))));
$text = einnahmen_anfrage_text($ausgewaehlt, [
    'ov'        => (string)setting('ov_name', ''),
    'name'      => (string)($user['display_name'] ?: $user['username']),
    'funktion'  => implode(', ', $funktionen),
    'empfaenger' => (string)setting('regionalstelle_name', ''),
    'email'     => (string)($user['email'] ?? ''),
    'telefon'   => (string)($user['phone'] ?? ''),
]);

render('einnahmen_anfrage', [
    'title'      => 'Anfrage an die Regionalstelle',
    'jahr'       => $jahr,
    'ab'         => $ab,
    'kandidaten' => $kandidaten,
    'gewaehlt'   => array_column($ausgewaehlt, 'id'),
    'text'       => $text,
    'betreff'    => einnahmen_anfrage_betreff((string)setting('ov_name', ''), count($ausgewaehlt)),
    'an'         => (string)setting('regionalstelle_email', ''),
    'summe'      => array_sum(array_map(static fn($e) => (float)$e['betrag_brutto'], $ausgewaehlt)),
]);
