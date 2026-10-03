<?php
declare(strict_types=1);

$user = current_user();
$b = bestellung_find((int)get_int('id', 0));
if (!$b) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Bestellung gibt es nicht (mehr).']);
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can('order_wish')) {
        http_response_code(403);
        render('error', ['title' => 'Kein Zugriff', 'message' => 'Bestellungen darf nur bearbeiten, wer bestellen darf.']);
        return;
    }
    switch (post_str('action')) {
        case 'status':
            $fehler = bestellung_status_setzen($b, post_str('status'), $user);
            flash($fehler ? 'error' : 'success', $fehler ?? 'Stand gesetzt: ' . explode(' –', BESTELLUNG_STATUS[bestellung_status(post_str('status'))])[0] . '.');
            redirect_route('bestellung', ['id' => $b['id']]);
            // redirect beendet
        case 'delete':
            bestellung_delete($b, $user);
            flash('success', 'Bestellung ' . e((string)$b['nummer']) . ' gelöscht.');
            redirect_route('bestellungen');
    }
}

$wuensche = bestellung_wuensche((int)$b['id']);
$buchungen = bestellung_buchungen((int)$b['id']);

render('bestellung', [
    'title'     => 'Bestellung ' . $b['nummer'],
    'b'         => $b,
    'wuensche'  => $wuensche,
    'buchungen' => $buchungen,
    'gebucht'   => expenses_summe($buchungen),
    'darf'      => can('order_wish'),
    'buchen'    => can('manage_budget'),
]);
