<?php
declare(strict_types=1);

/**
 * Newsletter: ein hochgeladenes PDF per E-Mail an eine Liste von Adressen.
 *
 * Das Modul schreibt keine Newsletter – es verschickt sie. Je Newsletter:
 * ein PDF, Betreff und Begleittext (bis zum Start änderbar), die Empfänger
 * aus Kontakten, Verteilern und freien Adressen, ein Zeitpunkt. Der
 * automatische Abruf verschickt ab dem Zeitpunkt in Portionen, jede Mail
 * mit Abmeldelink über den Connector. Wer sich abmeldet, steht auf der
 * Sperrliste und bekommt nichts mehr – egal aus welcher Liste.
 *
 * Die Kennung im Abmeldelink ist ein HMAC der Adresse mit einem Geheimnis
 * dieser Installation: Der Connector sieht nie eine Adresse, und niemand
 * kann aus einer Kennung eine Adresse abmelden, die er nicht kennt.
 *
 * Die Sperrliste speichert keine Adressen, nur ihre Kennungen (Prüfsummen):
 * Wer sich abgemeldet hat, steht dort nicht im Klartext. Geprüft wird, indem
 * die Kennung der anzuschreibenden Adresse nachgeschlagen wird.
 */

const NEWSLETTER_STATUS = ['entwurf' => 'Entwurf', 'geplant' => 'geplant', 'laeuft' => 'wird verschickt', 'gesendet' => 'verschickt'];
const NEWSLETTER_JE_SEITE = 50;

/* ==================================================================== */
/* Ablage                                                                */
/* ==================================================================== */

function newsletter_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'newsletter';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function newsletter_pfad(array $n): ?string
{
    if (trim((string)($n['stored_name'] ?? '')) === '') {
        return null;
    }
    $p = newsletter_dir() . DIRECTORY_SEPARATOR . basename((string)$n['stored_name']);
    return is_file($p) ? $p : null;
}

/* ==================================================================== */
/* Laden                                                                 */
/* ==================================================================== */

function newsletter_find(?int $id): ?array
{
    return $id ? db_row('SELECT n.*, c.name AS connector_name FROM newsletter n LEFT JOIN connectors c ON c.id = n.connector_id WHERE n.id = ?', [$id]) : null;
}

function newsletter_anzahl(): int
{
    return (int)db_val('SELECT COUNT(*) FROM newsletter', [], 0);
}

/** Die Liste, neueste zuerst, seitenweise – mit Versandzahlen */
function newsletter_liste(int $offset, int $anzahl): array
{
    return db_all('SELECT n.*,
                          (SELECT COUNT(*) FROM newsletter_versand v WHERE v.newsletter_id = n.id) AS versand_gesamt,
                          (SELECT COUNT(*) FROM newsletter_versand v WHERE v.newsletter_id = n.id AND v.gesendet_am IS NOT NULL) AS versand_fertig,
                          (SELECT COUNT(*) FROM newsletter_versand v WHERE v.newsletter_id = n.id AND v.fehler <> \'\') AS versand_fehler
                   FROM newsletter n ORDER BY COALESCE(n.senden_am, n.created_at) DESC, n.id DESC
                   LIMIT ' . max(1, $anzahl) . ' OFFSET ' . max(0, $offset));
}

/** Versandstand eines Newsletters: ['gesamt', 'fertig', 'fehler', 'offen'] */
function newsletter_versandstand(int $id): array
{
    $r = db_row('SELECT COUNT(*) AS gesamt, SUM(gesendet_am IS NOT NULL) AS fertig, SUM(fehler <> \'\') AS fehler
                 FROM newsletter_versand WHERE newsletter_id = ?', [$id]) ?? [];
    $gesamt = (int)($r['gesamt'] ?? 0);
    $fertig = (int)($r['fertig'] ?? 0);
    $fehler = (int)($r['fehler'] ?? 0);
    return ['gesamt' => $gesamt, 'fertig' => $fertig, 'fehler' => $fehler, 'offen' => max(0, $gesamt - $fertig - $fehler)];
}

/** Marke für den Status. Reine Funktion. */
function newsletter_status_badge(string $status): string
{
    $farbe = match ($status) {
        'geplant'  => '#0284c7',
        'laeuft'   => '#a16207',
        'gesendet' => '#15803d',
        default    => '#64748b',
    };
    return '<span class="badge" style="background:' . $farbe . '">' . e(NEWSLETTER_STATUS[$status] ?? $status) . '</span>';
}

/* ==================================================================== */
/* Kennung und Abmeldelink                                               */
/* ==================================================================== */

/** Das Geheimnis dieser Installation – einmal erzeugt, dann gemerkt */
function newsletter_geheimnis(): string
{
    $g = state_get('newsletter_geheimnis', '');
    if ($g === '') {
        $g = bin2hex(random_bytes(32));
        state_save('newsletter_geheimnis', $g);
    }
    return $g;
}

/** Adresse vereinheitlichen: Kleinbuchstaben, ohne Rand. Reine Funktion. */
function newsletter_adresse_norm(string $email): string
{
    return mb_strtolower(trim($email));
}

/** Kennung einer Adresse für den Abmeldelink – HMAC, nie die Adresse selbst. Reine Funktion bis aufs Geheimnis. */
function newsletter_kennung(string $email, ?string $geheimnis = null): string
{
    return hash_hmac('sha256', newsletter_adresse_norm($email), $geheimnis ?? newsletter_geheimnis());
}

/** Der Abmeldelink beim Connector. Reine Funktion. */
function newsletter_abmeldelink(array $connector, string $kennung): string
{
    return rtrim(trim((string)($connector['url'] ?? '')), '/') . '/index.php?p=abmelden&a=' . $kennung;
}

/**
 * Fußzeile jeder Mail mit dem Abmeldelink. Der Text kommt aus den
 * Einstellungen und enthält {link}; fehlt es, hängt der Link hinten an. Reine Funktion.
 */
function newsletter_fuss(string $vorlage, string $link, string $ovName): string
{
    $vorlage = trim(str_replace("\r", '', $vorlage));
    if ($vorlage === '') {
        $vorlage = "Du bekommst diesen Newsletter von {ov}. Abbestellen: {link}";
    }
    $text = str_replace(['{link}', '{ov}'], [$link, $ovName], $vorlage);
    if (!str_contains($vorlage, '{link}')) {
        $text .= "\nAbbestellen: " . $link;
    }
    return "\n\n--\n" . $text;
}

/* ==================================================================== */
/* Sperrliste                                                            */
/* ==================================================================== */

function newsletter_sperre_liste(): array
{
    return db_all('SELECT * FROM newsletter_sperre ORDER BY gesperrt_am DESC, id DESC');
}

/** Alle gesperrten Kennungen als Nachschlagetabelle – Adressen stehen dort nicht */
function newsletter_sperre_mengen(): array
{
    $kennungen = [];
    foreach (db_all('SELECT token FROM newsletter_sperre') as $s) {
        if (trim((string)$s['token']) !== '') {
            $kennungen[(string)$s['token']] = true;
        }
    }
    return ['kennungen' => $kennungen];
}

/** Ist die Adresse gesperrt? Prüfsumme bilden und nachschlagen. Reine Funktion über die Mengen. */
function newsletter_gesperrt(string $email, array $mengen, ?string $geheimnis = null): bool
{
    return isset($mengen['kennungen'][newsletter_kennung($email, $geheimnis)]);
}

/**
 * Sperren – von Hand über die Adresse oder über die Kennung aus der
 * Abmeldung. Gespeichert wird nur die Kennung. Rückgabe: neu angelegt?
 */
function newsletter_sperren(string $email, string $grund, ?string $kennung = null): bool
{
    $kennung = strtolower(trim((string)($kennung ?? '')));
    if ($kennung === '' && trim($email) !== '') {
        $kennung = newsletter_kennung($email);
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $kennung)) {
        return false;
    }
    if (db_val('SELECT id FROM newsletter_sperre WHERE token = ?', [$kennung])) {
        return false;
    }
    db_insert('newsletter_sperre', ['token' => $kennung, 'grund' => mb_substr($grund, 0, 200), 'gesperrt_am' => date('Y-m-d H:i:s')]);
    audit('newsletter.gesperrt', 'newsletter', null, 'Kennung ' . substr($kennung, 0, 12));
    return true;
}

function newsletter_entsperren(int $id): void
{
    $s = db_row('SELECT * FROM newsletter_sperre WHERE id = ?', [$id]);
    if ($s === null) {
        return;
    }
    db_exec('DELETE FROM newsletter_sperre WHERE id = ?', [$id]);
    audit('newsletter.entsperrt', 'newsletter', null, 'Kennung ' . substr((string)$s['token'], 0, 12));
}

/** Abgeholte Abmeldungen (Kennungen vom Connector) in die Sperrliste. Rückgabe: Zahl der neuen Sperren. */
function newsletter_abmeldungen_uebernehmen(array $abmeldungen): int
{
    $n = 0;
    foreach ($abmeldungen as $a) {
        if (newsletter_sperren('', 'Abmeldung über den Link' . (!empty($a['ts']) ? ' am ' . date('d.m.Y', (int)$a['ts']) : ''), (string)($a['kennung'] ?? ''))) {
            $n++;
        }
    }
    return $n;
}


/* ==================================================================== */
/* Empfänger                                                             */
/* ==================================================================== */

function newsletter_kontakt_ids(int $id): array
{
    return array_map('intval', array_column(db_all('SELECT contact_id FROM newsletter_kontakte WHERE newsletter_id = ?', [$id]), 'contact_id'));
}

function newsletter_verteiler_ids(int $id): array
{
    return array_map('intval', array_column(db_all('SELECT group_id FROM newsletter_verteiler WHERE newsletter_id = ?', [$id]), 'group_id'));
}

function newsletter_empfaenger_setzen(int $id, array $kontaktIds, array $groupIds): void
{
    db_exec('DELETE FROM newsletter_kontakte WHERE newsletter_id = ?', [$id]);
    db_exec('DELETE FROM newsletter_verteiler WHERE newsletter_id = ?', [$id]);
    foreach (array_unique(array_map('intval', $kontaktIds)) as $kid) {
        if ($kid > 0) {
            db_insert('newsletter_kontakte', ['newsletter_id' => $id, 'contact_id' => $kid]);
        }
    }
    foreach (array_unique(array_map('intval', $groupIds)) as $gid) {
        if ($gid > 0) {
            db_insert('newsletter_verteiler', ['newsletter_id' => $id, 'group_id' => $gid]);
        }
    }
}

/** Freie Adressen aus dem Textfeld: eine je Zeile oder durch Komma/Semikolon getrennt, "Name <adresse>" erlaubt. Reine Funktion. */
function newsletter_adressen_parsen(string $text): array
{
    $out = [];
    foreach (preg_split('/[\r\n,;]+/', $text) ?: [] as $zeile) {
        $zeile = trim($zeile);
        if ($zeile === '') {
            continue;
        }
        $name = '';
        $email = $zeile;
        if (preg_match('/^(.*?)<([^>]+)>$/', $zeile, $m)) {
            $name = trim($m[1], " \t\"'");
            $email = trim($m[2]);
        }
        if (mail_adresse_gueltig($email)) {
            $out[] = ['email' => $email, 'name' => $name];
        }
    }
    return $out;
}

/**
 * Alle Empfänger eines Newsletters: Kontakte, Verteilermitglieder, freie
 * Adressen – jede Adresse einmal, Gesperrte nicht. Reine Funktion über die
 * übergebenen Listen.
 */
function newsletter_empfaenger_zusammenfuehren(array $kontakte, array $frei, array $sperre, ?string $geheimnis = null): array
{
    $alle = [];
    $gesperrt = 0;
    foreach ($kontakte as $k) {
        $email = (string)($k['email'] ?? '');
        if (!mail_adresse_gueltig($email)) {
            continue;
        }
        $norm = newsletter_adresse_norm($email);
        if (isset($alle[$norm])) {
            continue;
        }
        if (newsletter_gesperrt($norm, $sperre, $geheimnis)) {
            $gesperrt++;
            continue;
        }
        $alle[$norm] = ['email' => trim($email), 'name' => function_exists('contact_name') ? contact_name($k) : trim((string)($k['vorname'] ?? '') . ' ' . (string)($k['nachname'] ?? ''))];
    }
    foreach ($frei as $f) {
        $norm = newsletter_adresse_norm((string)$f['email']);
        if (isset($alle[$norm])) {
            continue;
        }
        if (newsletter_gesperrt($norm, $sperre, $geheimnis)) {
            $gesperrt++;
            continue;
        }
        $alle[$norm] = ['email' => trim((string)$f['email']), 'name' => (string)($f['name'] ?? '')];
    }
    return ['empfaenger' => array_values($alle), 'gesperrt' => $gesperrt];
}

/** Empfänger eines Newsletters aus der Datenbank auflösen */
function newsletter_empfaenger_aufloesen(array $n): array
{
    $kontakte = [];
    foreach (newsletter_kontakt_ids((int)$n['id']) as $kid) {
        $k = function_exists('contact_find') ? contact_find($kid) : null;
        if ($k !== null) {
            $kontakte[] = $k;
        }
    }
    foreach (newsletter_verteiler_ids((int)$n['id']) as $gid) {
        foreach (function_exists('contact_group_members') ? contact_group_members($gid) : [] as $k) {
            $kontakte[] = $k;
        }
    }
    return newsletter_empfaenger_zusammenfuehren($kontakte, newsletter_adressen_parsen((string)($n['adressen'] ?? '')), newsletter_sperre_mengen());
}

/* ==================================================================== */
/* Speichern                                                             */
/* ==================================================================== */

/** Aus dem Formular anlegen oder ändern; nur Entwürfe und Geplantes lassen sich ändern. Rückgabe: [id, fehler[]] */
function newsletter_save_from_post(?array $n, array $user): array
{
    $fehler = [];
    if ($n && !in_array((string)$n['status'], ['entwurf', 'geplant'], true)) {
        return [null, ['Ein Newsletter, der verschickt wird oder wurde, lässt sich nicht mehr ändern.']];
    }
    $titel = mb_substr(trim(post_str('titel')), 0, 200);
    if ($titel === '') {
        $fehler[] = 'Bitte einen Titel angeben.';
    }
    $betreff = mb_substr(trim(post_str('betreff')), 0, 200);
    $connectorId = post_int('connector_id');
    $connector = $connectorId > 0 ? connector_find($connectorId) : null;
    if ($connectorId > 0 && $connector === null) {
        $fehler[] = 'Diesen Connector gibt es nicht.';
    }
    $sendenAm = null;
    if (trim(post_str('senden_am')) !== '') {
        $t = strtotime(str_replace('T', ' ', trim(post_str('senden_am'))));
        if ($t === false) {
            $fehler[] = 'Der Versandzeitpunkt ist kein gültiger Zeitpunkt.';
        } else {
            $sendenAm = date('Y-m-d H:i:00', $t);
        }
    }
    // PDF: Pflicht beim Anlegen, beim Ändern wahlweise ersetzen
    $datei = $_FILES['pdf'] ?? null;
    $neueDatei = null;
    if ($datei && (int)($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $neueDatei = newsletter_upload_pruefen($datei);
        if (is_string($neueDatei)) {
            $fehler[] = $neueDatei;
            $neueDatei = null;
        }
    } elseif (!$n) {
        $fehler[] = 'Bitte den Newsletter als PDF hochladen.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'titel'        => $titel,
        'betreff'      => $betreff !== '' ? $betreff : $titel,
        'text'         => trim(str_replace("\r", '', post_str('text'))),
        'connector_id' => $connector !== null ? (int)$connector['id'] : null,
        'senden_am'    => $sendenAm,
        'adressen'     => trim(post_str('adressen')),
    ];
    if ($n) {
        db_update('newsletter', $daten, 'id = ?', [(int)$n['id']]);
        $id = (int)$n['id'];
        audit('newsletter.bearbeitet', 'newsletter', $id, $titel);
    } else {
        $daten['status'] = 'entwurf';
        $daten['created_by'] = (int)($user['id'] ?? 0) ?: null;
        $id = db_insert('newsletter', $daten);
        audit('newsletter.angelegt', 'newsletter', $id, $titel);
    }
    if ($neueDatei !== null) {
        newsletter_datei_ablegen($id, $neueDatei, $n);
    }
    newsletter_empfaenger_setzen($id, wahl_ids('kontakte'), wahl_ids('verteiler'));
    return [$id, []];
}

/** Hochgeladene Datei prüfen: nur PDF, Größe. Rückgabe: Angaben oder Fehlertext. */
function newsletter_upload_pruefen(array $datei): array|string
{
    if ((int)($datei['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return 'Die Datei kam nicht an (Fehlercode ' . (int)$datei['error'] . ').';
    }
    $tmp = (string)($datei['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
        return 'Die Datei wurde nicht über das Formular hochgeladen.';
    }
    $name = basename((string)($datei['name'] ?? 'newsletter.pdf'));
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
        return 'Nur PDF-Dateien.';
    }
    $groesse = (int)($datei['size'] ?? 0);
    if ($groesse <= 0 || $groesse > upload_max_bytes()) {
        return 'Die Datei ist leer oder größer als ' . (int)(upload_max_bytes() / 1024 / 1024) . ' MB.';
    }
    $kopf = (string)@file_get_contents($tmp, false, null, 0, 5);
    if (!str_starts_with($kopf, '%PDF-')) {
        return 'Das ist kein PDF.';
    }
    return ['tmp' => $tmp, 'name' => mb_substr($name, 0, 255), 'size' => $groesse];
}

/** Datei in die Ablage legen, die alte wegwerfen, Eintrag nachführen, KI einreihen */
function newsletter_datei_ablegen(int $id, array $datei, ?array $bisher): void
{
    $stored = 'nl_' . $id . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $ziel = newsletter_dir() . DIRECTORY_SEPARATOR . $stored;
    if (!(defined('OVB_TEST_UPLOADS') ? copy($datei['tmp'], $ziel) : (move_uploaded_file($datei['tmp'], $ziel) || copy($datei['tmp'], $ziel)))) {
        throw new RuntimeException('Die Datei ließ sich nicht ablegen.');
    }
    if ($bisher !== null && trim((string)($bisher['stored_name'] ?? '')) !== '') {
        $alt = newsletter_dir() . DIRECTORY_SEPARATOR . basename((string)$bisher['stored_name']);
        if (is_file($alt)) {
            @unlink($alt);
        }
    }
    db_update('newsletter', ['orig_name' => $datei['name'], 'stored_name' => $stored, 'mime' => 'application/pdf', 'size_bytes' => (int)$datei['size']], 'id = ?', [$id]);
    if (function_exists('ki_vergessen')) {
        ki_vergessen('newsletter', $id);
    }
    if (function_exists('ki_einreihen')) {
        ki_einreihen('newsletter', $id, 'dokument');
    }
}

function newsletter_delete(array $n): ?string
{
    if ((string)$n['status'] === 'laeuft') {
        return 'Der Versand läuft gerade – erst abbrechen.';
    }
    $pfad = newsletter_pfad($n);
    if ($pfad !== null) {
        @unlink($pfad);
    }
    if (function_exists('ki_vergessen')) {
        ki_vergessen('newsletter', (int)$n['id']);
    }
    db_exec('DELETE FROM newsletter WHERE id = ?', [(int)$n['id']]);
    audit('newsletter.geloescht', 'newsletter', (int)$n['id'], (string)$n['titel']);
    return null;
}

/* ==================================================================== */
/* Planen und verschicken                                                */
/* ==================================================================== */

/** Betreff und Text, wie sie hinausgehen – ohne Fußzeile. Reine Funktion. */
function newsletter_vorlage(array $n, string $ovName): array
{
    $betreff = trim((string)($n['betreff'] ?? '')) !== '' ? (string)$n['betreff'] : (string)$n['titel'];
    $text = trim((string)($n['text'] ?? ''));
    if ($text === '') {
        $text = "Guten Tag,\n\nanbei der aktuelle Newsletter „" . (string)$n['titel'] . "\" als PDF.\n\nViele Grüße\n" . $ovName;
    }
    return ['betreff' => $betreff, 'text' => $text];
}

/**
 * Versand planen: Empfänger auflösen und als offene Versandzeilen eintragen,
 * Status auf „geplant" mit Zeitpunkt (null = sofort beim nächsten Abruf).
 * Rückgabe: ['ok', 'text', 'anzahl']
 */
function newsletter_planen(array $n, ?string $sendenAm, array $user): array
{
    if (!in_array((string)$n['status'], ['entwurf', 'geplant'], true)) {
        return ['ok' => false, 'text' => 'Dieser Newsletter ist schon ' . (NEWSLETTER_STATUS[(string)$n['status']] ?? $n['status']) . '.', 'anzahl' => 0];
    }
    if (newsletter_pfad($n) === null) {
        return ['ok' => false, 'text' => 'Es fehlt das PDF.', 'anzahl' => 0];
    }
    $connector = connector_find((int)($n['connector_id'] ?? 0));
    if ($connector === null || !connector_taugt($connector, 'newsletter')) {
        return ['ok' => false, 'text' => 'Für den Abmeldelink braucht der Newsletter einen gekoppelten Connector mit der Verwendung „Newsletter".', 'anzahl' => 0];
    }
    $r = newsletter_empfaenger_aufloesen($n);
    if (!$r['empfaenger']) {
        return ['ok' => false, 'text' => 'Kein Empfänger mit E-Mail-Adresse' . ($r['gesperrt'] ? ' – ' . $r['gesperrt'] . ' stehen auf der Sperrliste' : '') . '.', 'anzahl' => 0];
    }
    db_exec('DELETE FROM newsletter_versand WHERE newsletter_id = ?', [(int)$n['id']]);
    foreach ($r['empfaenger'] as $e) {
        db_insert('newsletter_versand', ['newsletter_id' => (int)$n['id'], 'email' => $e['email'], 'name' => mb_substr((string)$e['name'], 0, 150), 'token' => newsletter_kennung($e['email'])]);
    }
    $wann = $sendenAm !== null && $sendenAm !== '' ? date('Y-m-d H:i:00', (int)strtotime($sendenAm)) : date('Y-m-d H:i:s');
    db_update('newsletter', ['status' => 'geplant', 'senden_am' => $wann], 'id = ?', [(int)$n['id']]);
    audit('newsletter.geplant', 'newsletter', (int)$n['id'], count($r['empfaenger']) . ' Empfänger ab ' . $wann);
    return ['ok' => true, 'anzahl' => count($r['empfaenger']), 'text' => sprintf('%d Empfänger eingetragen%s. Versand ab %s.',
        count($r['empfaenger']), $r['gesperrt'] ? ', ' . $r['gesperrt'] . ' gesperrte ausgelassen' : '', de_datetime($wann))];
}

/** Planung oder laufenden Versand abbrechen: zurück in den Entwurf, offene Versandzeilen weg */
function newsletter_abbrechen(array $n): void
{
    db_exec('DELETE FROM newsletter_versand WHERE newsletter_id = ? AND gesendet_am IS NULL', [(int)$n['id']]);
    db_update('newsletter', ['status' => 'entwurf', 'gestartet_am' => null], 'id = ?', [(int)$n['id']]);
    audit('newsletter.abgebrochen', 'newsletter', (int)$n['id'], (string)$n['titel']);
}

/** Eine Mail an eine Adresse – mit PDF und Abmeldelink */
function newsletter_mail_senden(array $n, array $connector, string $email, string $name, string $kennung, array $vorlage, string $pdfInhalt): void
{
    $text = $vorlage['text'] . newsletter_fuss((string)setting('newsletter_fusstext', ''), newsletter_abmeldelink($connector, $kennung), (string)setting('ov_name', ''));
    mail_senden($email, $vorlage['betreff'], $text, $name, [[
        'name' => (string)($n['orig_name'] ?: 'newsletter.pdf'), 'mime' => 'application/pdf', 'inhalt' => $pdfInhalt,
    ]]);
}

/**
 * Was ansteht, verschicken – ruft der automatische Abruf. Geplante
 * Newsletter mit erreichtem Zeitpunkt starten, dann höchstens $max Mails.
 * Rückgabe: ['gestartet', 'gesendet', 'fehler', 'fertig']
 */
function newsletter_versand_abarbeiten(int $max = 50, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $res = ['gestartet' => 0, 'gesendet' => 0, 'fehler' => 0, 'fertig' => 0];
    if (!mail_eingerichtet()) {
        return $res;
    }
    foreach (db_all("SELECT * FROM newsletter WHERE status = 'geplant' AND senden_am IS NOT NULL AND senden_am <= ?", [date('Y-m-d H:i:s', $jetzt)]) as $n) {
        db_update('newsletter', ['status' => 'laeuft', 'gestartet_am' => date('Y-m-d H:i:s', $jetzt)], 'id = ?', [(int)$n['id']]);
        $res['gestartet']++;
    }
    $sperre = newsletter_sperre_mengen();
    $uebrig = max(1, $max);
    foreach (db_all("SELECT n.*, c.name AS connector_name FROM newsletter n LEFT JOIN connectors c ON c.id = n.connector_id WHERE n.status = 'laeuft' ORDER BY n.senden_am, n.id") as $n) {
        if ($uebrig <= 0) {
            break;
        }
        $connector = connector_find((int)($n['connector_id'] ?? 0));
        $pfad = newsletter_pfad($n);
        $vorlage = newsletter_vorlage($n, (string)setting('ov_name', ''));
        $pdf = $pfad !== null ? (string)file_get_contents($pfad) : '';
        $offen = db_all("SELECT * FROM newsletter_versand WHERE newsletter_id = ? AND gesendet_am IS NULL AND fehler = '' ORDER BY id LIMIT " . (int)$uebrig, [(int)$n['id']]);
        foreach ($offen as $v) {
            $uebrig--;
            if ($connector === null || $pdf === '') {
                db_update('newsletter_versand', ['fehler' => $connector === null ? 'kein Connector' : 'PDF fehlt'], 'id = ?', [(int)$v['id']]);
                $res['fehler']++;
                continue;
            }
            if (newsletter_gesperrt((string)$v['email'], $sperre)) {
                db_update('newsletter_versand', ['fehler' => 'gesperrt'], 'id = ?', [(int)$v['id']]);
                continue;
            }
            try {
                newsletter_mail_senden($n, $connector, (string)$v['email'], (string)$v['name'], (string)$v['token'], $vorlage, $pdf);
                db_update('newsletter_versand', ['gesendet_am' => date('Y-m-d H:i:s', $jetzt)], 'id = ?', [(int)$v['id']]);
                $res['gesendet']++;
            } catch (Throwable $ex) {
                db_update('newsletter_versand', ['fehler' => mb_substr($ex->getMessage(), 0, 300)], 'id = ?', [(int)$v['id']]);
                $res['fehler']++;
            }
        }
        $stand = newsletter_versandstand((int)$n['id']);
        if ($stand['offen'] === 0) {
            db_update('newsletter', ['status' => 'gesendet', 'gesendet_am' => date('Y-m-d H:i:s', $jetzt)], 'id = ?', [(int)$n['id']]);
            audit('newsletter.verschickt', 'newsletter', (int)$n['id'], sprintf('%d verschickt, %d Fehler', $stand['fertig'], $stand['fehler']));
            $res['fertig']++;
        }
    }
    return $res;
}

/** Eine Probe an eine Adresse – ohne Versandzeile, mit Abmeldelink zur Ansicht */
function newsletter_probe(array $n, string $email, array $user): array
{
    if (!mail_adresse_gueltig($email)) {
        return ['ok' => false, 'text' => 'Für die Probe braucht dein Zugang eine E-Mail-Adresse (Profil).'];
    }
    $connector = connector_find((int)($n['connector_id'] ?? 0));
    if ($connector === null) {
        return ['ok' => false, 'text' => 'Für den Abmeldelink braucht der Newsletter einen Connector.'];
    }
    $pfad = newsletter_pfad($n);
    if ($pfad === null) {
        return ['ok' => false, 'text' => 'Es fehlt das PDF.'];
    }
    $vorlage = newsletter_vorlage($n, (string)setting('ov_name', ''));
    $vorlage['betreff'] = '[Probe] ' . $vorlage['betreff'];
    try {
        newsletter_mail_senden($n, $connector, $email, (string)($user['display_name'] ?? ''), newsletter_kennung($email), $vorlage, (string)file_get_contents($pfad));
    } catch (Throwable $ex) {
        return ['ok' => false, 'text' => 'Die Probe ließ sich nicht verschicken: ' . $ex->getMessage()];
    }
    audit('newsletter.probe', 'newsletter', (int)$n['id'], $email);
    return ['ok' => true, 'text' => 'Probe an ' . $email . ' verschickt.'];
}
