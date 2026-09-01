<?php
declare(strict_types=1);

/**
 * Kontakte und Verteiler.
 *
 * Kontakte sind die Ansprechpartner ausserhalb des Ortsverbands: Kommune,
 * Feuerwehr, Presse, Firmen, Förderer. Ein Verteiler bündelt sie für einen
 * Anlass – etwa eine Einladung – und hält je Kontakt fest, wie der Stand ist.
 */

/* ---------------- Kontakte ---------------- */

function contact_find(int $id): ?array
{
    return db_row('SELECT * FROM contacts WHERE id = ?', [$id]);
}

/** Anzeigename: Person, sonst Organisation */
function contact_name(array $c): string
{
    $person = trim(($c['titel'] ?? '') . ' ' . ($c['vorname'] ?? '') . ' ' . ($c['nachname'] ?? ''));
    $person = trim(preg_replace('/\s+/', ' ', $person) ?? '');
    if ($person !== '') {
        return $person;
    }
    return trim((string)($c['organisation'] ?? '')) ?: 'ohne Namen';
}

/** Briefanrede: eigene Angabe, sonst aus Anrede und Nachname, sonst Vorgabe */
function contact_salutation(array $c): string
{
    if (trim((string)($c['anschreiben'] ?? '')) !== '') {
        return trim((string)$c['anschreiben']);
    }

    $anrede = trim((string)($c['anrede'] ?? ''));
    $nachname = trim((string)($c['nachname'] ?? ''));
    $titel = trim((string)($c['titel'] ?? ''));

    if ($nachname !== '' && $anrede !== '') {
        $form = match ($anrede) {
            'Herr' => 'Sehr geehrter Herr',
            'Frau' => 'Sehr geehrte Frau',
            default => 'Guten Tag',
        };
        // Ohne Titel entstünde sonst eine doppelte Lücke vor dem Nachnamen
        return trim(preg_replace('/\s+/', ' ', $form . ' ' . $titel . ' ' . $nachname) ?? '');
    }

    return (string)setting('kontakte_anrede_vorgabe', 'Sehr geehrte Damen und Herren');
}

/** Postanschrift als Zeilen, für Etiketten und Serienbriefe */
function contact_address_lines(array $c): array
{
    $zeilen = [];
    $name = contact_name($c);
    if (trim((string)$c['organisation']) !== '' && $name !== $c['organisation']) {
        $zeilen[] = $c['organisation'];
    }
    if ($name !== '' && $name !== 'ohne Namen') {
        $zeilen[] = trim(($c['anrede'] ? $c['anrede'] . ' ' : '') . $name);
    }
    if (trim((string)$c['position']) !== '') {
        $zeilen[] = $c['position'];
    }
    if (trim((string)$c['strasse']) !== '') {
        $zeilen[] = $c['strasse'];
    }
    $ort = trim(trim((string)$c['plz']) . ' ' . trim((string)$c['ort']));
    if ($ort !== '') {
        $zeilen[] = $ort;
    }
    if (trim((string)$c['land']) !== '') {
        $zeilen[] = $c['land'];
    }
    return $zeilen;
}

/**
 * $f: q, kategorie_id, group_id, nur_mit_email, nur_mit_anschrift, aktiv, sort
 */
function contact_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['q'])) {
        $w[] = '(c.nachname LIKE ? OR c.vorname LIKE ? OR c.organisation LIKE ?'
            . ' OR c.email LIKE ? OR c.ort LIKE ? OR c.position LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like, $like);
    }
    if (!empty($f['kategorie_id'])) {
        $w[] = 'c.kategorie_id = ?';
        $p[] = (int)$f['kategorie_id'];
    }
    if (!empty($f['nur_mit_email'])) {
        $w[] = "c.email <> ''";
    }
    if (!empty($f['nur_mit_anschrift'])) {
        $w[] = "c.strasse <> '' AND c.ort <> ''";
    }
    if (!isset($f['aktiv']) || $f['aktiv'] !== 'alle') {
        $w[] = 'c.is_active = 1';
    }
    if (!empty($f['group_id'])) {
        $w[] = 'EXISTS (SELECT 1 FROM contact_group_members m WHERE m.contact_id = c.id AND m.group_id = ?)';
        $p[] = (int)$f['group_id'];
    }
    if (!empty($f['nicht_in_group'])) {
        $w[] = 'NOT EXISTS (SELECT 1 FROM contact_group_members m WHERE m.contact_id = c.id AND m.group_id = ?)';
        $p[] = (int)$f['nicht_in_group'];
    }

    $order = match ($f['sort'] ?? 'name') {
        'org'  => 'c.organisation ASC, c.nachname ASC',
        'neu'  => 'c.created_at DESC',
        'ort'  => 'c.ort ASC, c.nachname ASC',
        default => 'c.nachname ASC, c.vorname ASC, c.organisation ASC',
    };

    $sql = 'SELECT c.*, ka.label AS kategorie_label, ka.color AS kategorie_color,
                   (SELECT COUNT(*) FROM contact_group_members m WHERE m.contact_id = c.id) AS verteiler
            FROM contacts c
            LEFT JOIN list_items ka ON ka.id = c.kategorie_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    return db_all($sql, $p);
}

function contact_save_from_post(?array $existing, array $user): array
{
    $errors = [];

    $vorname = post_str('vorname');
    $nachname = post_str('nachname');
    $organisation = post_str('organisation');

    if ($nachname === '' && $organisation === '') {
        $errors[] = 'Bitte mindestens einen Nachnamen oder eine Organisation angeben.';
    }

    $email = post_str('email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Die E-Mail-Adresse sieht nicht gültig aus.';
    }

    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'anrede'       => mb_substr(post_str('anrede'), 0, 30),
        'titel'        => mb_substr(post_str('titel'), 0, 40),
        'vorname'      => mb_substr($vorname, 0, 80),
        'nachname'     => mb_substr($nachname, 0, 80),
        'organisation' => mb_substr($organisation, 0, 150),
        'position'     => mb_substr(post_str('position'), 0, 150),
        'kategorie_id' => post_int('kategorie_id'),
        'email'        => mb_substr($email, 0, 150),
        'telefon'      => mb_substr(post_str('telefon'), 0, 60),
        'mobil'        => mb_substr(post_str('mobil'), 0, 60),
        'strasse'      => mb_substr(post_str('strasse'), 0, 150),
        'plz'          => mb_substr(post_str('plz'), 0, 15),
        'ort'          => mb_substr(post_str('ort'), 0, 100),
        'land'         => mb_substr(post_str('land'), 0, 60),
        'anschreiben'  => mb_substr(post_str('anschreiben'), 0, 150),
        'notiz'        => post_str('notiz'),
        'extra'        => extra_from_post(contact_extra_fields()),
        'is_active'    => post_bool('is_active'),
        'updated_by'   => (int)$user['id'],
    ];

    if ($existing) {
        db_update('contacts', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('kontakt.bearbeitet', 'contact', $id, contact_name($data));
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('contacts', $data);
        audit('kontakt.angelegt', 'contact', $id, contact_name($data));
    }

    return [$id, []];
}

/* ---------------- Verteiler ---------------- */

function contact_group_find(int $id): ?array
{
    return db_row('SELECT * FROM contact_groups WHERE id = ?', [$id]);
}

function contact_groups_all(bool $onlyActive = false): array
{
    return db_all(
        'SELECT g.*,
                (SELECT COUNT(*) FROM contact_group_members m WHERE m.group_id = g.id) AS anzahl,
                (SELECT COALESCE(SUM(m.personen),0) FROM contact_group_members m
                  JOIN list_items s ON s.id = m.status_id
                 WHERE m.group_id = g.id AND s.slug = ?) AS zugesagt
         FROM contact_groups g'
        . ($onlyActive ? ' WHERE g.is_active = 1' : '')
        . ' ORDER BY g.is_active DESC, g.anlass_am IS NULL, g.anlass_am DESC, g.name',
        ['zugesagt']
    );
}

/** Mitglieder eines Verteilers samt Kontaktdaten */
function contact_group_members(int $groupId): array
{
    return db_all(
        'SELECT c.*, m.status_id, m.personen, m.notiz AS teilnahme_notiz, m.updated_at AS status_am,
                st.label AS status_label, st.color AS status_color, st.slug AS status_slug,
                ka.label AS kategorie_label, ka.color AS kategorie_color
         FROM contact_group_members m
         JOIN contacts c ON c.id = m.contact_id
         LEFT JOIN list_items st ON st.id = m.status_id
         LEFT JOIN list_items ka ON ka.id = c.kategorie_id
         WHERE m.group_id = ?
         ORDER BY c.organisation, c.nachname, c.vorname',
        [$groupId]
    );
}

/** Zahlen für die Kopfzeile eines Verteilers */
function contact_group_stats(array $members): array
{
    $s = ['anzahl' => count($members), 'personen' => 0, 'mit_email' => 0, 'mit_anschrift' => 0];
    foreach ($members as $m) {
        if (($m['status_slug'] ?? '') === 'zugesagt') {
            $s['personen'] += (int)$m['personen'];
        }
        if (trim((string)$m['email']) !== '') {
            $s['mit_email']++;
        }
        if (trim((string)$m['strasse']) !== '' && trim((string)$m['ort']) !== '') {
            $s['mit_anschrift']++;
        }
    }
    return $s;
}

function contact_group_add(int $groupId, int $contactId): bool
{
    return db_exec(
        'INSERT IGNORE INTO contact_group_members (group_id, contact_id, status_id) VALUES (?,?,?)',
        [$groupId, $contactId, list_default_id('einladung_status')]
    ) > 0;
}

function contact_group_remove(int $groupId, int $contactId): void
{
    db_exec('DELETE FROM contact_group_members WHERE group_id = ? AND contact_id = ?', [$groupId, $contactId]);
}
