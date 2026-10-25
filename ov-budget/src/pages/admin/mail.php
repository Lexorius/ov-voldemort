<?php
declare(strict_types=1);

/** E-Mail-Versand: Zustand ansehen, Testnachricht schicken */
$me = require_role('admin');

$fehler = '';
$hinweis = '';
$an = trim((string)($me['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'test') {
    $an = trim(post_str('an'));
    if (!mail_adresse_gueltig($an)) {
        $fehler = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } elseif (!mail_eingerichtet()) {
        $fehler = 'Der Versand ist nicht eingerichtet: Schalter, Server und Absender in den Einstellungen prüfen.';
    } else {
        try {
            mail_senden($an, 'Testnachricht aus OV-Multitool',
                "Diese Nachricht kommt aus OV-Multitool (Verwaltung → E-Mail).\n\nWenn sie ankommt, ist der Versand eingerichtet. "
                . "Berichte der Abstimmungen gehen denselben Weg.\n\nGesendet am " . date('d.m.Y \u\m H:i') . ' Uhr.');
            $hinweis = 'Testnachricht an ' . $an . ' verschickt. Bitte im Postfach nachsehen – auch im Spam-Ordner.';
            audit('mail.test', 'mail', null, $an);
        } catch (Throwable $ex) {
            $fehler = 'Der Versand ist fehlgeschlagen: ' . $ex->getMessage();
        }
    }
}

render('admin/mail', [
    'title'   => 'E-Mail',
    'fehler'  => $fehler,
    'hinweis' => $hinweis,
    'status'  => mail_status(),
    'an'      => $an,
]);
