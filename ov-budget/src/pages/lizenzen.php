<?php
declare(strict_types=1);

/* Bibliotheken und Lizenzen: was OV-Multitool von anderen mitbringt – mit Urheber, Lizenz und Quelle */
require_login();

render('lizenzen', [
    'title'        => 'Bibliotheken und Lizenzen',
    'bibliotheken' => lizenzen_bibliotheken(),
    'dienste'      => LIZENZEN_DIENSTE,
    'symbole'      => can('view_karten') ? lizenzen_symbolquellen() : [],
]);
