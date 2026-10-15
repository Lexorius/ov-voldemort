<?php
declare(strict_types=1);

/** Dateisuche über die KI-Schlagwörter und Zusammenfassungen – jeder sieht nur, was er auch sonst sehen darf */
$user = require_login();
$q = trim(get_str('q'));

render('ki_suche', [
    'title'   => 'Dateisuche',
    'q'       => $q,
    'treffer' => $q !== '' ? ki_suche($q, $user) : [],
    'tags'    => ki_haeufige_tags(40),
    'aktiv'   => ki_aktiv(),
]);
