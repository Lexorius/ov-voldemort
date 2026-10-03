<?php
declare(strict_types=1);

$filters = [
    'status' => get_str('status'),
    'q'      => get_str('q'),
    'jahr'   => get_int('jahr'),
    'offen'  => get_str('status') === '' && get_str('alle') !== '1' ? 1 : 0,
];
$liste = bestellung_query($filters);

render('bestellungen', [
    'title'   => 'Bestellungen',
    'liste'   => $liste,
    'filters' => $filters,
    'summe'   => array_sum(array_map(static fn($b) => (float)$b['summe'], $liste)),
    'zuBestellen' => can('order_wish') ? wish_query(['status_slug' => 'freigegeben', 'sort' => 'prio']) : [],
]);
