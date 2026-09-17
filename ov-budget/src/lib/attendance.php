<?php
declare(strict_types=1);

/*
 * Anwesenheit bei Besprechungen
 *
 * Eingeladene kommen aus drei Quellen: Benutzer der Anwendung, Kontakte
 * (auch ganze Verteiler) und frei eingetippte Namen. Je Person hält die
 * Liste einen Status aus der Liste teilnahme_status – von „eingeladen“
 * über „teilgenommen“ bis „nicht erschienen“.
 */

/** Status-Schlüssel mit fester Bedeutung für Zählungen und Protokoll */
const ANWESEND_SLUG = 'teilgenommen';
const ENTSCHULDIGT_SLUG = 'entschuldigt';
const FEHLT_SLUG = 'fehlt';

/** Teilnehmerliste einer Besprechung, aufgelöst auf anzeigbare Namen */
function attendance_list(int $meetingId): array
{
    return db_all(
        "SELECT a.*,
                COALESCE(NULLIF(a.name, ''),
                         NULLIF(TRIM(CONCAT(COALESCE(c.vorname,''), ' ', COALESCE(c.nachname,''))), ''),
                         c.organisation,
                         NULLIF(u.display_name, ''),
                         u.username, '?') AS anzeige,
                c.organisation AS organisation, c.email AS c_email, u.username AS username,
                fg.label AS fachgruppe_label,
                s.label AS status_label, s.color AS status_color, s.slug AS status_slug
         FROM meeting_attendees a
         LEFT JOIN contacts c   ON c.id = a.contact_id
         LEFT JOIN users u      ON u.id = a.user_id
         LEFT JOIN list_items fg ON fg.id = u.fachgruppe_id
         LEFT JOIN list_items s ON s.id = a.status_id
         WHERE a.meeting_id = ?
         ORDER BY anzeige",
        [$meetingId]
    );
}

/** Zählt die Liste nach Status aus (ohne Datenbank, damit leicht prüfbar) */
function attendance_stats(array $rows): array
{
    $s = ['anzahl' => count($rows), 'anwesend' => 0, 'entschuldigt' => 0, 'fehlt' => 0, 'offen' => 0];
    foreach ($rows as $r) {
        $slug = (string)($r['status_slug'] ?? '');
        if ($slug === ANWESEND_SLUG) {
            $s['anwesend']++;
        } elseif ($slug === ENTSCHULDIGT_SLUG) {
            $s['entschuldigt']++;
        } elseif ($slug === FEHLT_SLUG) {
            $s['fehlt']++;
        } else {
            $s['offen']++;
        }
    }
    return $s;
}

/** Namen je Status, für Protokoll und Druckansicht */
function attendance_by_status(array $rows, string $slug): array
{
    $namen = [];
    foreach ($rows as $r) {
        if ((string)($r['status_slug'] ?? '') === $slug) {
            $namen[] = attendance_name($r);
        }
    }
    return $namen;
}

/** Anzeigename samt Organisation oder Fachgruppe */
function attendance_name(array $row, bool $mitZusatz = false): string
{
    $name = trim((string)($row['anzeige'] ?? ''));
    if ($name === '') {
        $name = '?';
    }
    if (!$mitZusatz) {
        return $name;
    }
    $zusatz = trim((string)($row['organisation'] ?? '')) ?: trim((string)($row['fachgruppe_label'] ?? ''));
    return $zusatz !== '' && $zusatz !== $name ? $name . ' (' . $zusatz . ')' : $name;
}

function attendance_default_status(): ?int
{
    return list_id_by_slug('teilnahme_status', 'eingeladen') ?? list_default_id('teilnahme_status');
}

/**
 * Person aufnehmen. $art ist 'user', 'contact' oder 'name'.
 * Gibt true zurück, wenn jemand neu dazugekommen ist.
 */
function attendance_add(int $meetingId, string $art, int|string $wert, ?int $statusId = null): bool
{
    $statusId ??= attendance_default_status();
    $data = ['meeting_id' => $meetingId, 'status_id' => $statusId];

    if ($art === 'user') {
        $data['user_id'] = (int)$wert;
    } elseif ($art === 'contact') {
        $data['contact_id'] = (int)$wert;
    } else {
        $name = trim(mb_substr((string)$wert, 0, 150));
        if ($name === '') {
            return false;
        }
        $data['name'] = $name;
    }

    // Doppelte Einladungen sind egal – der eindeutige Schlüssel fängt sie ab
    $sql = 'INSERT IGNORE INTO meeting_attendees (meeting_id, user_id, contact_id, name, status_id) VALUES (?,?,?,?,?)';
    return db_exec($sql, [
        $meetingId,
        $data['user_id'] ?? null,
        $data['contact_id'] ?? null,
        $data['name'] ?? '',
        $statusId,
    ]) > 0;
}

/** Alle Kontakte eines Verteilers einladen */
function attendance_add_group(int $meetingId, int $groupId): int
{
    $n = 0;
    foreach (db_all('SELECT contact_id FROM contact_group_members WHERE group_id = ?', [$groupId]) as $r) {
        $n += attendance_add($meetingId, 'contact', (int)$r['contact_id']) ? 1 : 0;
    }
    return $n;
}

/** Liste eines anderen Termins übernehmen, Status wieder auf „eingeladen“ */
function attendance_copy(int $vonMeetingId, int $nachMeetingId): int
{
    $n = 0;
    foreach (attendance_list($vonMeetingId) as $r) {
        if ($r['user_id']) {
            $n += attendance_add($nachMeetingId, 'user', (int)$r['user_id']) ? 1 : 0;
        } elseif ($r['contact_id']) {
            $n += attendance_add($nachMeetingId, 'contact', (int)$r['contact_id']) ? 1 : 0;
        } else {
            $n += attendance_add($nachMeetingId, 'name', (string)$r['name']) ? 1 : 0;
        }
    }
    return $n;
}

/**
 * Der Termin, dessen Liste sich zum Übernehmen anbietet: der letzte
 * zurückliegende Termin derselben Serie, sonst derselben Besprechungsart.
 */
function attendance_source_meeting(array $meeting): ?array
{
    $sql = 'SELECT m.id, m.titel, m.datum,
                   (SELECT COUNT(*) FROM meeting_attendees a WHERE a.meeting_id = m.id) AS anzahl
            FROM meetings m
            WHERE m.id <> ? AND m.datum <= ? %s
            HAVING anzahl > 0
            ORDER BY m.datum DESC, m.id DESC
            LIMIT 1';
    $p = [(int)$meeting['id'], (string)$meeting['datum']];

    if (!empty($meeting['series_id'])) {
        $row = db_row(sprintf($sql, 'AND m.series_id = ?'), array_merge($p, [(int)$meeting['series_id']]));
        if ($row) {
            return $row;
        }
    }
    if (!empty($meeting['typ_id'])) {
        return db_row(sprintf($sql, 'AND m.typ_id = ?'), array_merge($p, [(int)$meeting['typ_id']]));
    }
    return db_row(sprintf($sql, ''), $p);
}

/** Status mehrerer Zeilen setzen; gibt die Anzahl der Änderungen zurück */
function attendance_save_status(int $meetingId, array $status, array $notizen = []): int
{
    $n = 0;
    foreach ($status as $id => $statusId) {
        $id = (int)$id;
        $statusId = (int)$statusId;
        if ($id <= 0 || $statusId <= 0) {
            continue;
        }
        $n += db_exec(
            'UPDATE meeting_attendees SET status_id = ?, notiz = ? WHERE id = ? AND meeting_id = ?',
            [$statusId, mb_substr(trim((string)($notizen[$id] ?? '')), 0, 255), $id, $meetingId]
        );
    }
    return $n;
}

/** Alle offenen Einträge auf einen Status setzen (z. B. alle als anwesend) */
function attendance_set_all(int $meetingId, string $slug, bool $nurOffene = true): int
{
    $statusId = list_id_by_slug('teilnahme_status', $slug);
    if (!$statusId) {
        return 0;
    }
    $sql = 'UPDATE meeting_attendees a LEFT JOIN list_items s ON s.id = a.status_id
            SET a.status_id = ? WHERE a.meeting_id = ?';
    if ($nurOffene) {
        $sql .= " AND COALESCE(s.slug, '') IN ('', 'eingeladen', 'zugesagt')";
    }
    return db_exec($sql, [$statusId, $meetingId]);
}

function attendance_remove(int $meetingId, int $id): int
{
    return db_exec('DELETE FROM meeting_attendees WHERE id = ? AND meeting_id = ?', [$id, $meetingId]);
}

/** Benutzer, die noch nicht auf der Liste stehen */
function attendance_candidate_users(int $meetingId): array
{
    return db_all(
        "SELECT u.id, COALESCE(NULLIF(u.display_name, ''), u.username) AS name, fg.label AS fachgruppe_label
         FROM users u
         LEFT JOIN list_items fg ON fg.id = u.fachgruppe_id
         WHERE u.is_active = 1
           AND u.id NOT IN (SELECT COALESCE(user_id, 0) FROM meeting_attendees WHERE meeting_id = ?)
         ORDER BY name",
        [$meetingId]
    );
}

/** Kontakte, die noch nicht auf der Liste stehen */
function attendance_candidate_contacts(int $meetingId, string $q = ''): array
{
    $w = ['c.is_active = 1'];
    $p = [$meetingId];
    if (trim($q) !== '') {
        $w[] = '(c.vorname LIKE ? OR c.nachname LIKE ? OR c.organisation LIKE ?)';
        $like = '%' . trim($q) . '%';
        array_push($p, $like, $like, $like);
    }
    return db_all(
        "SELECT c.id, TRIM(CONCAT(COALESCE(c.vorname,''), ' ', COALESCE(c.nachname,''))) AS name, c.organisation
         FROM contacts c
         WHERE c.id NOT IN (SELECT COALESCE(contact_id, 0) FROM meeting_attendees WHERE meeting_id = ?)
           AND " . implode(' AND ', $w) . "
         ORDER BY c.nachname, c.vorname
         LIMIT 100",
        $p
    );
}
