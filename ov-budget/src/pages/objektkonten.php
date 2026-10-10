<?php
declare(strict_types=1);

/** Objektkonten pflegen: anlegen, Kategorien zuordnen, stilllegen */
$user = require_role('admin', 'leitung');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'aktiv') {
    $k = objektkonto_find((int)post_int('id', 0));
    if ($k) {
        $neu = (int)$k['is_active'] === 1 ? 0 : 1;
        db_update('objektkonten', ['is_active' => $neu], 'id = ?', [(int)$k['id']]);
        audit('objektkonto.bearbeitet', 'objektkonto', (int)$k['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
        flash('success', $neu ? sprintf('„%s" ist wieder aktiv.', objektkonto_titel($k)) : sprintf('„%s" ist stillgelegt.', objektkonto_titel($k)));
    }
    redirect_route('objektkonten');
}

$kategorien = objektkonto_kategorien();

render('objektkonten', [
    'title'      => 'Objektkonten',
    'jahr'       => haushaltsjahr(),
    'konten'     => objektkonto_all(),
    'jeKonto'    => objektkonto_kategorien_je_konto($kategorien),
]);
