<?php
declare(strict_types=1);

/**
 * Der Menübaum: Gruppen anlegen, umbenennen und auflösen, Module umhängen und verschieben.
 * Jede Änderung ist ein eigener Schritt (POST) und gilt sofort für alle.
 *   action=hoch | runter      knoten, [modul]   – ein Knoten der obersten Ebene oder ein Modul in seiner Gruppe
 *   action=umhaengen          modul, ziel       – ziel: Stelle einer Gruppe, -1 = oberste Ebene
 *   action=gruppe_neu         name, [icon]
 *   action=gruppe_aendern     knoten, name, [icon]
 *   action=gruppe_aufloesen   knoten
 *   action=flach              alles auf eine Ebene
 *   action=grundvorschlag     zurück zum Grundvorschlag
 */
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baum = nav_baum();
    $action = post_str('action');
    $knoten = (int)post_int('knoten', -1);
    switch ($action) {
        case 'hoch':
        case 'runter':
            $baum = nav_baum_verschieben($baum, $knoten, post_str('modul'), $action === 'hoch' ? -1 : 1);
            break;
        case 'umhaengen':
            $baum = nav_baum_umhaengen($baum, post_str('modul'), (int)post_int('ziel', -1));
            break;
        case 'gruppe_neu':
            if (nav_gruppe_name(post_str('name')) === '') {
                flash('error', 'Bitte einen Namen für die Gruppe angeben.');
                redirect_route('admin_nav');
            }
            $baum = nav_baum_gruppe_neu($baum, post_str('name'), post_str('icon'));
            flash('success', 'Gruppe „' . nav_gruppe_name(post_str('name')) . '" angelegt – jetzt Module hineinhängen.');
            break;
        case 'gruppe_aendern':
            $baum = nav_baum_gruppe_aendern($baum, $knoten, post_str('name'), post_str('icon'));
            break;
        case 'gruppe_aufloesen':
            $baum = nav_baum_gruppe_aufloesen($baum, $knoten);
            flash('success', 'Gruppe aufgelöst – ihre Module stehen jetzt für sich.');
            break;
        case 'flach':
            $baum = nav_baum_flach($baum);
            flash('success', 'Alle Module stehen auf einer Ebene.');
            break;
        case 'grundvorschlag':
            state_save('nav_baum', '');
            audit('menue.baum', '', null, 'Grundvorschlag');
            flash('success', 'Das Menü steht wieder im Grundvorschlag.');
            redirect_route('admin_nav');
        default:
            redirect_route('admin_nav');
    }
    nav_baum_speichern($baum);
    audit('menue.baum', '', null, $action);
    redirect_route('admin_nav');
}

render('admin/nav', [
    'title'  => 'Menü',
    'baum'   => nav_baum(),
    'module' => nav_module(),
    'eigen'  => nav_baum_eigen(),
]);
