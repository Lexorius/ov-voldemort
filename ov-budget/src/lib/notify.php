<?php
declare(strict_types=1);

/**
 * Benachrichtigungen über Home Assistant.
 *
 * Die Anwendung ruft den Dienst notify.<ziel> in Home Assistant auf; auf dem
 * Handy erscheint die Meldung über die Companion-App. Je Benutzer wird das
 * Ziel einmal hinterlegt (Profil oder Benutzerverwaltung).
 *
 * Gesendet wird nicht sofort: Ereignisse landen in einer Warteschlange
 * (Tabelle notifications), der Minutenlauf schickt sie los. So wartet niemand
 * im Browser auf Home Assistant, und fehlgeschlagene Versuche sind nachlesbar.
 */

/** Ereignisse, über die benachrichtigt werden kann */
function notify_ereignisse(): array
{
    return [
        'aufgabe_neu'        => ['label' => 'Neue Aufgabe für mich',
                                 'text'  => 'Wenn eine Aufgabe für mich, meine Fachgruppe oder meine Funktion angelegt wird.'],
        'aufgabe_faellig'    => ['label' => 'Aufgabe wird heute fällig', 'taeglich' => true,
                                 'text'  => 'Einmal am Tag: eigene Aufgaben, die heute fällig sind oder überfällig.'],
        'wunsch_freigabe'    => ['label' => 'Neuer Wunsch, den ich freigeben darf',
                                 'text'  => 'Sobald ein Wunsch eingetragen wird, an alle, die ihn in dieser Höhe zur Bestellung freigeben dürfen.'],
        'wunsch_freigegeben' => ['label' => 'Mein Wunsch wurde freigegeben oder bestellt',
                                 'text'  => 'An die Person, die den Wunsch eingetragen hat.'],
        'auftrag_neu'        => ['label' => 'Neue Instandsetzungsmeldung',
                                 'text'  => 'An die Leitung, sobald ein Auftrag angelegt wird.'],
        'fahrzeug_ausfall'   => ['label' => 'Fahrzeug fällt aus',
                                 'text'  => 'An die Leitung, wenn eine Meldung das Fahrzeug stilllegt.'],
        'fristen'            => ['label' => 'Fristen laufen ab (HU, SP, UVV)', 'taeglich' => true,
                                 'text'  => 'Einmal am Tag an die Leitung, solange eine Frist bald fällig oder abgelaufen ist.'],
        'besprechung'        => ['label' => 'Besprechung am nächsten Tag', 'taeglich' => true,
                                 'text'  => 'Einmal am Tag an alle mit Benachrichtigungen, wenn morgen eine Besprechung ansteht.'],
    ];
}

function notify_enabled(): bool
{
    return setting_bool('ha_benachrichtigung_aktiv', false) || setting_bool('push_aktiv', false);
}

/** Ist dieses Ereignis eingeschaltet? */
function notify_ereignis_aktiv(string $key): bool
{
    return notify_enabled() && setting_bool('notify_' . $key, true);
}

/* ==================================================================== */
/* Home Assistant                                                        */
/* ==================================================================== */

const HA_TOKEN_DATEI = '/data/ha_token';
/** s6-overlay legt die Umgebung des Containers hier als Dateien ab */
const HA_S6_UMGEBUNG = ['/run/s6/container_environment', '/var/run/s6/container_environment'];

/**
 * Zugangstoken des Supervisors.
 * In der Umgebung steht es nur dem Startskript und dem Minutenlauf zur
 * Verfügung; der Webserver-Prozess bekommt es nicht immer mit. Deshalb legt
 * der Start es zusätzlich in einer Datei ab, die nur der Webserver lesen darf.
 * Rückgabe: [Token, Quelle] – Token leer, wenn es keins gibt.
 */
function ha_token(): array
{
    foreach (['SUPERVISOR_TOKEN', 'HASSIO_TOKEN'] as $name) {
        $wert = trim((string)getenv($name));
        if ($wert !== '') {
            return [$wert, $name];
        }
    }
    $datei = defined('OVB_HA_TOKEN_DATEI') ? OVB_HA_TOKEN_DATEI : HA_TOKEN_DATEI;
    $roh = @file_get_contents($datei);
    $wert = is_string($roh) ? trim($roh) : '';
    if ($wert !== '') {
        return [$wert, 'Datei'];
    }
    // Letzter Versuch: die von s6-overlay abgelegte Umgebung
    foreach (defined('OVB_HA_S6') ? [OVB_HA_S6] : HA_S6_UMGEBUNG as $ordner) {
        foreach (['SUPERVISOR_TOKEN', 'HASSIO_TOKEN'] as $name) {
            $roh = @file_get_contents($ordner . '/' . $name);
            $wert = is_string($roh) ? trim($roh) : '';
            if ($wert !== '') {
                return [$wert, 's6'];
            }
        }
    }
    return ['', ''];
}

/**
 * Aufruf der Home-Assistant-Kernschnittstelle über den Supervisor.
 * $body = null bedeutet GET.
 */
function ha_api(string $pfad, ?array $body = null, int $timeout = 10): array
{
    [$token] = ha_token();
    if ($token === '') {
        throw new RuntimeException('Kein Zugang zu Home Assistant: Der Supervisor hat kein Token '
            . 'hinterlegt. Läuft die Anwendung als Add-on, hilft meist ein Neustart des Add-ons – '
            . 'dabei wird das Token neu abgelegt.');
    }
    $url = 'http://supervisor/core/api/' . ltrim($pfad, '/');
    $ch = curl_init($url);
    $header = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
    ];
    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Passiert, wenn ein Text keine gültige UTF-8-Folge ist
            throw new RuntimeException('Die Nachricht ließ sich nicht als JSON verpacken: '
                . json_last_error_msg());
        }
        $header[] = 'Content-Type: application/json';
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = $json;
    }
    $opt[CURLOPT_HTTPHEADER] = $header;
    curl_setopt_array($ch, $opt);
    $antwort = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fehler = curl_error($ch);
    curl_close($ch);

    if ($antwort === false) {
        throw new RuntimeException('Home Assistant nicht erreichbar: ' . ($fehler ?: 'Zeitüberschreitung'));
    }
    if ($code >= 400) {
        // Home Assistant schickt den Grund meist als JSON ("message"), sonst nur "400: Bad Request"
        $grund = trim((string)$antwort);
        $alsJson = json_decode($grund, true);
        if (is_array($alsJson) && trim((string)($alsJson['message'] ?? '')) !== '') {
            $grund = (string)$alsJson['message'];
        }
        throw new RuntimeException(sprintf('Home Assistant antwortete auf %s mit HTTP %d: %s',
            $pfad, $code, mb_substr($grund, 0, 200)));
    }
    $daten = json_decode((string)$antwort, true);
    return is_array($daten) ? $daten : [];
}

/** Verfügbare notify-Dienste, z. B. mobile_app_pixel_8 */
function ha_notify_dienste(bool $frisch = false): array
{
    if (!$frisch) {
        $zwischen = json_decode((string)state_get('ha_notify_dienste', ''), true);
        if (is_array($zwischen) && $zwischen && (int)state_get('ha_notify_dienste_stand', '0') > time() - 3600) {
            return $zwischen;
        }
    }
    $out = [];
    foreach (ha_api('services') as $domain) {
        if (($domain['domain'] ?? '') !== 'notify') {
            continue;
        }
        foreach (array_keys((array)($domain['services'] ?? [])) as $dienst) {
            $out[] = (string)$dienst;
        }
    }
    sort($out);
    state_save('ha_notify_dienste', (string)json_encode($out));
    state_save('ha_notify_dienste_stand', (string)time());
    return $out;
}

/**
 * Eine Nachricht an einen notify-Dienst schicken.
 *
 * Zwei Stolperstellen sind eingebaut behandelt: Ein Ziel, das es in Home
 * Assistant gar nicht gibt, wird vorher erkannt; und Dienste, die mit dem
 * Feld "data" nichts anfangen können, bekommen die Nachricht ohne den Link.
 */
function ha_notify_send(string $dienst, string $titel, string $text, string $url = ''): void
{
    $dienst = trim($dienst);
    if ($dienst === '' || !preg_match('/^[a-z0-9_]+$/', $dienst)) {
        throw new RuntimeException('Ungültiges Benachrichtigungsziel: „' . $dienst . '". '
            . 'Erlaubt sind Kleinbuchstaben, Ziffern und _, also z. B. mobile_app_pixel_8.');
    }

    $daten = ['title' => $titel, 'message' => $text];
    if ($url !== '') {
        // Die Companion-App öffnet damit direkt die passende Seite
        $daten['data'] = ['url' => $url, 'clickAction' => $url];
    }

    try {
        ha_api('services/notify/' . $dienst, $daten);
        return;
    } catch (Throwable $ex) {
        if (isset($daten['data'])) {
            // Manche notify-Dienste lehnen unbekannte Felder ab – dann eben ohne Link
            unset($daten['data']);
            try {
                ha_api('services/notify/' . $dienst, $daten);
                return;
            } catch (Throwable $zweiter) {
                $ex = $zweiter;
            }
        }
        throw new RuntimeException(ha_notify_hinweis($dienst, $ex->getMessage()), 0, $ex);
    }
}

/**
 * Fehlermeldung verständlich machen: Meist stimmt der Name des Ziels nicht.
 * Die Liste wird nur zur Erklärung herangezogen – gesendet wird immer erst.
 */
function ha_notify_hinweis(string $dienst, string $meldung): string
{
    try {
        $bekannt = ha_notify_dienste(true);
    } catch (Throwable $ex) {
        return $meldung;
    }
    if ($bekannt && !in_array($dienst, $bekannt, true)) {
        return sprintf('%s – Home Assistant kennt kein notify.%s. Vorhanden sind: %s.',
            $meldung, $dienst,
            implode(', ', array_map(static fn($d) => 'notify.' . $d, array_slice($bekannt, 0, 12))));
    }
    return $meldung;
}

/* ==================================================================== */
/* Warteschlange                                                         */
/* ==================================================================== */

/** Benutzer, die Benachrichtigungen bekommen können */
function notify_users(array $userIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    // Erreichbar ist, wer ein Ziel in Home Assistant hat oder einen Browser angemeldet hat
    return db_all(
        "SELECT u.id, u.display_name, u.username, u.ha_notify,
                (SELECT COUNT(*) FROM push_subscriptions p WHERE p.user_id = u.id) AS browser
         FROM users u
         WHERE u.id IN ($in) AND u.is_active = 1 AND u.notify_aktiv = 1
           AND (u.ha_notify <> '' OR EXISTS (SELECT 1 FROM push_subscriptions p2 WHERE p2.user_id = u.id))",
        $ids
    );
}

/**
 * Ereignis in die Warteschlange legen. Rückgabe: Anzahl der Empfänger.
 * $url ist ein Pfad innerhalb der Anwendung (z. B. "?p=todo&id=5").
 */
function notify_queue(array $userIds, string $ereignis, string $titel, string $text, string $url = ''): int
{
    if (!notify_ereignis_aktiv($ereignis)) {
        return 0;
    }
    $n = 0;
    foreach (notify_users($userIds) as $u) {
        db_insert('notifications', [
            'user_id'  => (int)$u['id'],
            'ereignis' => mb_substr($ereignis, 0, 40),
            'titel'    => mb_substr($titel, 0, 150),
            'text'     => mb_substr($text, 0, 500),
            'url'      => mb_substr($url, 0, 255),
            'status'   => 'offen',
        ]);
        $n++;
    }
    return $n;
}

/**
 * Vollständige Adresse für die Meldung. Reine Funktion (bis auf die Einstellung).
 *
 * Zeigt die Adresse auf das Ingress-Panel von Home Assistant, wird der Pfad
 * weggelassen: Home Assistant reicht angehängte Parameter nicht in den Rahmen
 * weiter, der Link führt also ohnehin auf die Startseite. Enthält die Adresse
 * {pfad}, wird dort eingesetzt.
 */
function notify_url(string $pfad, ?string $basis = null): string
{
    $basis = trim($basis ?? (string)setting('ha_benachrichtigung_basis_url', ''));
    if ($basis === '') {
        return '';
    }
    if (str_contains($basis, '{pfad}')) {
        return str_replace('{pfad}', ltrim($pfad, '/'), $basis);
    }
    // Panel-Link (…/hassio/ingress/<add-on>) oder App-Verweis: ohne Anhängsel
    if ($pfad === '' || preg_match('#(^homeassistant://|/hassio/ingress/)#', $basis)) {
        return rtrim($basis, '/');
    }
    return rtrim($basis, '/') . '/' . ltrim($pfad, '/');
}

/**
 * Warteschlange abarbeiten. Fehler werden am Eintrag vermerkt; nach
 * mehreren Versuchen bleibt er als "fehler" liegen.
 */
function notify_flush(int $max = 25): array
{
    $res = ['gesendet' => 0, 'fehler' => 0, 'meldung' => ''];
    if (!notify_enabled()) {
        return $res;
    }
    $offen = db_all(
        "SELECT n.*, u.ha_notify FROM notifications n
         JOIN users u ON u.id = n.user_id
         WHERE n.status = 'offen' AND u.is_active = 1 AND u.notify_aktiv = 1
         ORDER BY n.id LIMIT " . max(1, $max)
    );
    foreach ($offen as $n) {
        $wege = 0;
        $fehler = [];

        // Weg 1: Home Assistant (Companion-App)
        if (trim((string)$n['ha_notify']) !== '') {
            try {
                ha_notify_send((string)$n['ha_notify'], (string)$n['titel'], (string)$n['text'],
                    notify_url((string)$n['url']));
                $wege++;
            } catch (Throwable $ex) {
                $fehler[] = $ex->getMessage();
            }
        }

        // Weg 2: angemeldete Browser derselben Person
        if (webpush_enabled()) {
            $push = push_to_user((int)$n['user_id'], (string)$n['titel'], (string)$n['text'],
                notify_url((string)$n['url']));
            $wege += $push['gesendet'];
            if ($push['fehler'] !== '') {
                $fehler[] = $push['fehler'];
            }
        }

        if ($wege > 0) {
            db_update('notifications', ['status' => 'gesendet', 'sent_at' => date('Y-m-d H:i:s'),
                'fehler' => $fehler ? mb_substr('Teilweise: ' . implode(' / ', $fehler), 0, 300) : ''],
                'id = ?', [(int)$n['id']]);
            $res['gesendet']++;
            continue;
        }

        $versuche = (int)$n['versuche'] + 1;
        db_update('notifications', [
            'versuche' => $versuche,
            'status'   => $versuche >= 3 ? 'fehler' : 'offen',
            'fehler'   => mb_substr($fehler ? implode(' / ', $fehler) : 'Kein Weg zum Empfänger.', 0, 300),
        ], 'id = ?', [(int)$n['id']]);
        $res['fehler']++;
        $res['meldung'] = $fehler ? $fehler[0] : 'Kein Weg zum Empfänger.';
        break;   // meist ist der Zugang gestört – nicht weiter hämmern
    }
    return $res;
}

/** Alte Einträge aufräumen */
function notify_cleanup(int $tage = 30): void
{
    db_exec("DELETE FROM notifications WHERE status <> 'offen' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$tage]);
}

/* ==================================================================== */
/* Empfängerkreise                                                       */
/* ==================================================================== */

/** Leitung und Administration */
function notify_leitung(): array
{
    return array_map(static fn($r) => (int)$r['id'],
        db_all("SELECT id FROM users WHERE is_active = 1 AND role IN ('admin','leitung')"));
}

/** Alle, die Benachrichtigungen eingeschaltet haben */
function notify_alle(): array
{
    return array_map(static fn($r) => (int)$r['id'],
        db_all("SELECT id FROM users u WHERE is_active = 1 AND notify_aktiv = 1
                AND (ha_notify <> '' OR EXISTS (SELECT 1 FROM push_subscriptions p WHERE p.user_id = u.id))"));
}

/** Wer ist für diese Aufgabe zuständig? */
function notify_todo_users(array $todo): array
{
    $typ = (string)($todo['target_type'] ?? '');
    $id = (int)($todo['target_id'] ?? 0);
    return match ($typ) {
        'user'       => [$id],
        'fachgruppe' => array_map(static fn($r) => (int)$r['id'],
            db_all('SELECT id FROM users WHERE is_active = 1 AND fachgruppe_id = ?', [$id])),
        'funktion'   => array_map(static fn($r) => (int)$r['user_id'],
            db_all('SELECT user_id FROM user_functions WHERE function_id = ?', [$id])),
        default      => notify_leitung(),   // "ganzer Ortsverband" geht an die Leitung
    };
}

/** Wer darf diesen Wunsch freigeben? */
function notify_freigeber(float $betrag): array
{
    $out = [];
    foreach (order_rights_users() as $u) {
        $r = order_rights_combine(order_rights_matches((string)$u['role'], $u['funktion_ids'], order_rights_rows()));
        if ($r['freigeben'] && ($r['grenze'] === null || $betrag <= $r['grenze'] + 0.004)) {
            $out[] = (int)$u['id'];
        }
    }
    return $out;
}

/* ==================================================================== */
/* Tägliche Erinnerungen                                                 */
/* ==================================================================== */

/**
 * Einmal am Tag ab der eingestellten Stunde: fällige Aufgaben, Fristen und
 * die Besprechung von morgen. Rückgabe: Anzahl eingereihter Nachrichten.
 */
function notify_taeglich(?int $jetzt = null): int
{
    $jetzt ??= time();
    if (!notify_enabled()) {
        return 0;
    }
    $stunde = max(0, min(23, setting_int('ha_benachrichtigung_stunde', 7)));
    $heute = date('Y-m-d', $jetzt);
    if ((int)date('G', $jetzt) < $stunde || state_get('notify_taeglich_lauf', '') === $heute) {
        return 0;
    }
    state_save('notify_taeglich_lauf', $heute);
    $n = 0;

    // Eigene Aufgaben, die heute oder früher fällig sind
    if (notify_ereignis_aktiv('aufgabe_faellig')) {
        $faellig = db_all(
            "SELECT t.id, t.titel, t.faellig_am, t.target_type, t.target_id
             FROM todos t LEFT JOIN list_items s ON s.id = t.status_id
             WHERE COALESCE(s.is_final, 0) = 0 AND t.faellig_am IS NOT NULL AND t.faellig_am <= ?",
            [$heute]
        );
        $jeBenutzer = [];
        foreach ($faellig as $t) {
            foreach (notify_todo_users($t) as $uid) {
                $jeBenutzer[$uid][] = $t;
            }
        }
        foreach ($jeBenutzer as $uid => $liste) {
            $ueberfaellig = count(array_filter($liste, static fn($t) => $t['faellig_am'] < $heute));
            $text = count($liste) === 1
                ? sprintf('„%s" ist %s fällig.', $liste[0]['titel'],
                    $liste[0]['faellig_am'] < $heute ? 'seit ' . de_date($liste[0]['faellig_am']) : 'heute')
                : sprintf('%d Aufgaben sind fällig, davon %d überfällig: %s', count($liste), $ueberfaellig,
                    implode(', ', array_map(static fn($t) => (string)$t['titel'], array_slice($liste, 0, 4))));
            $n += notify_queue([$uid], 'aufgabe_faellig', 'Aufgaben fällig', $text,
                count($liste) === 1 ? '?p=todo&id=' . (int)$liste[0]['id'] : '?p=todos&offen=1');
        }
    }

    // Fristen der Fahrzeuge
    if (notify_ereignis_aktiv('fristen')) {
        $warn = setting_int('fahrzeug_warn_tage', 30);
        $treffer = [];
        foreach (vehicle_query(['nur_aktive' => 1]) as $v) {
            foreach (vehicle_deadlines($v, $warn) as $f) {
                if ($f['status'] !== 'ok') {
                    $treffer[] = sprintf('%s: %s %s %s', $v['bezeichnung'], $f['label'],
                        $f['status'] === 'abgelaufen' ? 'abgelaufen seit' : 'fällig am', de_date($f['datum']));
                }
            }
        }
        if ($treffer) {
            $n += notify_queue(notify_leitung(), 'fristen', 'Fristen bei den Fahrzeugen',
                implode("\n", array_slice($treffer, 0, 8)), '?p=vehicles');
        }
    }

    // Besprechung am nächsten Tag
    if (notify_ereignis_aktiv('besprechung')) {
        $morgen = date('Y-m-d', $jetzt + 86400);
        foreach (db_all("SELECT id, titel, beginn, ort FROM meetings WHERE datum = ? AND status = 'geplant'", [$morgen]) as $m) {
            $text = sprintf('Morgen %s%s', $m['beginn'] ? 'um ' . substr((string)$m['beginn'], 0, 5) . ' Uhr' : '',
                $m['ort'] ? ' in ' . $m['ort'] : '');
            $n += notify_queue(notify_alle(), 'besprechung', (string)$m['titel'], trim($text),
                '?p=meeting&id=' . (int)$m['id']);
        }
    }

    return $n;
}
