<?php
declare(strict_types=1);

/**
 * KI-Vorschlag für die Kategorie eines Kontakts.
 *
 * Die Kategorien sind die vorhandene Auswahlliste „Kontaktkategorien"
 * (kontakt_kategorie), die die Verwaltung pflegt. Die KI bekommt genau
 * diese Liste mit und darf nur daraus wählen; alles, was nicht in der
 * Liste steht, fällt beim Lesen der Antwort weg. Sie legt nie Kategorien
 * an und setzt auch keine – sie macht je Kontakt einen Vorschlag, den
 * jemand übernimmt oder verwirft.
 *
 * Ablauf: Kontakt ohne Kategorie anlegen (oder „vorschlagen lassen") →
 * Auftrag in ki_auftraege mit typ „kontakt" → der minütliche Abruf fragt
 * Ollama → Vorschlag in contact_kategorie_vorschlaege → im Kontakt oder
 * auf der Seite „KI-Vorschläge" übernehmen.
 */

/* ---------------- Vorschläge ---------------- */

/** Offener Vorschlag eines Kontakts: Listeneintrag der Kategorie samt Modell und Zeit, oder null */
function kontakt_kategorie_vorschlag(int $contactId): ?array
{
    return db_row(
        'SELECT l.*, v.modell, v.erstellt_am FROM contact_kategorie_vorschlaege v JOIN list_items l ON l.id = v.kategorie_id
         WHERE v.contact_id = ?',
        [$contactId]
    );
}

/**
 * Vorschlag entscheiden: übernehmen setzt die Kategorie, in jedem Fall
 * verschwindet der Vorschlag. Gibt true, wenn die Kategorie gesetzt wurde.
 */
function kontakt_kategorie_vorschlag_entscheiden(int $contactId, bool $uebernehmen, ?int $userId = null): bool
{
    $gesetzt = false;
    if ($uebernehmen) {
        $v = db_row('SELECT kategorie_id FROM contact_kategorie_vorschlaege WHERE contact_id = ?', [$contactId]);
        if ($v) {
            db_update('contacts', ['kategorie_id' => (int)$v['kategorie_id'], 'updated_by' => $userId], 'id = ?', [$contactId]);
            $gesetzt = true;
        }
    }
    db_exec('DELETE FROM contact_kategorie_vorschlaege WHERE contact_id = ?', [$contactId]);
    return $gesetzt;
}

/** Kontakte mit offenem Vorschlag samt bisheriger und vorgeschlagener Kategorie */
function kontakt_kategorie_vorschlaege_alle(int $limit = 300): array
{
    return db_all(
        'SELECT c.*, ka.label AS kategorie_label, ka.color AS kategorie_color,
                v.kategorie_id AS vorschlag_id, v.modell AS vorschlag_modell, v.erstellt_am AS vorschlag_am,
                kv.label AS vorschlag_label, kv.color AS vorschlag_color
         FROM contact_kategorie_vorschlaege v
         JOIN contacts c ON c.id = v.contact_id
         JOIN list_items kv ON kv.id = v.kategorie_id
         LEFT JOIN list_items ka ON ka.id = c.kategorie_id
         ORDER BY c.kategorie_id IS NOT NULL, c.nachname, c.vorname, c.organisation LIMIT ' . max(1, $limit)
    );
}

/* ---------------- Einreihen ---------------- */

/** Vorschlag für diesen Kontakt anfordern (Auftrag in der KI-Warteschlange) */
function kontakt_kategorie_vorschlagen_lassen(int $contactId): bool
{
    return ki_einreihen('kontakt', $contactId);
}

/**
 * Aktive Kontakte ohne Kategorie, ohne offenen Vorschlag und ohne Auftrag
 * einreihen. Gibt die Zahl der neuen Aufträge.
 */
function kontakt_kategorie_alle_einreihen(): int
{
    if (!ki_modul_aktiv('kontakt')) {
        return 0;
    }
    return db_exec(
        "INSERT INTO ki_auftraege (typ, datei_id, status) SELECT 'kontakt', c.id, 'offen' FROM contacts c
         WHERE c.is_active = 1 AND c.kategorie_id IS NULL
           AND NOT EXISTS (SELECT 1 FROM contact_kategorie_vorschlaege v WHERE v.contact_id = c.id)
           AND NOT EXISTS (SELECT 1 FROM ki_auftraege a WHERE a.typ = 'kontakt' AND a.datei_id = c.id)"
    );
}

/** Zähler für die Vorschlagsseite */
function kontakt_kategorie_status(): array
{
    $s = ['mit_kategorie' => 0, 'ohne_kategorie' => 0, 'vorschlaege' => 0, 'offen' => 0, 'fehler' => 0];
    $s['mit_kategorie'] = (int)db_val('SELECT COUNT(*) FROM contacts WHERE is_active = 1 AND kategorie_id IS NOT NULL', [], 0);
    $s['ohne_kategorie'] = (int)db_val('SELECT COUNT(*) FROM contacts WHERE is_active = 1 AND kategorie_id IS NULL', [], 0);
    $s['vorschlaege'] = (int)db_val('SELECT COUNT(*) FROM contact_kategorie_vorschlaege', [], 0);
    foreach (db_all("SELECT status, COUNT(*) AS n FROM ki_auftraege WHERE typ = 'kontakt' GROUP BY status") as $r) {
        if ($r['status'] === 'offen' || $r['status'] === 'laeuft') {
            $s['offen'] += (int)$r['n'];
        } elseif ($r['status'] === 'fehler') {
            $s['fehler'] = (int)$r['n'];
        }
    }
    return $s;
}

/* ---------------- KI: Bitte und Antwort (reine Funktionen) ---------------- */

/**
 * Die Bitte an das Modell: der Kontakt, die erlaubten Kategorien, strenge
 * Regeln. Es gehen nur Angaben mit, die für die Einordnung helfen –
 * keine Rufnummern, keine Anschrift bis auf den Ort.
 *
 * $kategorien: Listeneinträge mit label und description
 */
function kontakt_ki_prompt(array $kontakt, array $kategorien, array $extraFelder = []): string
{
    $liste = [];
    foreach ($kategorien as $k) {
        $zeile = '- ' . trim((string)$k['label']);
        if (trim((string)($k['description'] ?? '')) !== '') {
            $zeile .= ' (' . trim((string)$k['description']) . ')';
        }
        $liste[] = $zeile;
    }

    $name = trim(trim((string)($kontakt['anrede'] ?? '')) . ' ' . trim((string)($kontakt['titel'] ?? '')) . ' '
        . trim((string)($kontakt['vorname'] ?? '')) . ' ' . trim((string)($kontakt['nachname'] ?? '')));
    $felder = [
        'Name'         => (string)preg_replace('/\s+/', ' ', $name),
        'Organisation' => (string)($kontakt['organisation'] ?? ''),
        'Funktion'     => (string)($kontakt['position'] ?? ''),
        'Ort'          => (string)($kontakt['ort'] ?? ''),
    ];
    $email = trim((string)($kontakt['email'] ?? ''));
    if (str_contains($email, '@')) {
        $felder['E-Mail-Domain'] = substr($email, strpos($email, '@') + 1);
    }
    $felder['Notiz'] = ki_text_kuerzen((string)($kontakt['notiz'] ?? ''), 1500);
    $werte = extra_values($kontakt);
    foreach ($extraFelder as $key => $def) {
        $w = trim((string)($werte[$key] ?? ''));
        if ($w !== '' && $def['type'] !== 'bool') {
            $felder[(string)$def['label']] = $w;
        }
    }
    $zeilen = [];
    foreach ($felder as $k => $v) {
        $v = trim((string)$v);
        if ($v !== '') {
            $zeilen[] = $k . ': ' . $v;
        }
    }

    return "Du hilfst beim Kontaktverzeichnis eines THW-Ortsverbands. Antworte auf Deutsch.\n"
        . "Ordne den Kontakt genau einer Kategorie zu. Erlaubt sind ausschließlich die Kategorien aus dieser Liste, "
        . "genau in dieser Schreibweise:\n" . implode("\n", $liste) . "\n\n"
        . "Regeln: Nenne genau eine Kategorie, und nur, wenn sie eindeutig passt. Erfinde keine neue Kategorie "
        . "und ändere keine. Passt keine eindeutig, lass das Feld leer.\n"
        . "Antworte ausschließlich mit einem JSON-Objekt dieser Form: {\"kategorie\": \"…\"}\n\n"
        . "Kontakt:\n" . implode("\n", $zeilen);
}

/**
 * Die Antwort des Modells lesen und streng gegen die Liste prüfen: zurück
 * kommt die ID einer Kategorie, die es wirklich gibt (Bezeichnung oder
 * Schlüssel, Groß/Klein und Umlaute egal), sonst null. Nennt das Modell
 * mehrere, zählt die erste bekannte. Reine Funktion.
 */
function kontakt_ki_antwort_parsen(string $roh, array $kategorien): ?int
{
    $roh = trim($roh);
    $a = strpos($roh, '{');
    $z = strrpos($roh, '}');
    $genannt = [];
    if ($a !== false && $z !== false && $z >= $a) {
        $d = json_decode(substr($roh, $a, $z - $a + 1), true);
        $t = is_array($d) ? ($d['kategorie'] ?? $d['category'] ?? $d['kategorien'] ?? $d['categories'] ?? '') : '';
        foreach (is_array($t) ? $t : [$t] as $w) {
            if (is_scalar($w)) {
                $genannt[] = trim((string)$w);
            }
        }
    } elseif ($roh !== '') {
        // Kein JSON – vielleicht die nackte Bezeichnung oder eine Aufzählung
        $genannt = array_map('trim', preg_split('/[,;\n]+/', $roh) ?: []);
    }

    $index = [];
    foreach ($kategorien as $k) {
        foreach ([(string)$k['label'], (string)($k['slug'] ?? '')] as $s) {
            $n = import_norm($s);
            if ($n !== '') {
                $index[$n] ??= (int)$k['id'];
            }
        }
    }
    foreach ($genannt as $w) {
        $n = import_norm(trim($w, "#-* \"'"));
        if ($n !== '' && isset($index[$n])) {
            return $index[$n];
        }
    }
    return null;
}

/* ---------------- KI: Auswertung ---------------- */

/**
 * Einen Kontakt vom Textmodell einordnen lassen und den Vorschlag
 * speichern. Nennt das Modell die schon gesetzte Kategorie oder keine,
 * gibt es keinen Vorschlag – ein alter wird dann entfernt.
 * Wirft KiException; gibt ['kategorie_id' => ?int, 'modell' => …].
 */
function kontakt_ki_auswerten(int $contactId): array
{
    $c = contact_find($contactId);
    if (!$c) {
        throw new KiUebersprungen('Den Kontakt gibt es nicht mehr.');
    }
    $kategorien = list_items('kontakt_kategorie');
    if (!$kategorien) {
        throw new KiUebersprungen('In der Auswahlliste „Kontaktkategorien" steht nichts – ohne Liste gibt es keine Vorschläge.');
    }
    $modell = trim((string)setting('ki_modell', ''));
    if ($modell === '') {
        throw new KiException('Kein Textmodell eingetragen.');
    }

    $antwort = ki_generieren($modell, kontakt_ki_prompt($c, $kategorien, contact_extra_fields()));
    $id = kontakt_ki_antwort_parsen($antwort, $kategorien);
    if ($id !== null && !empty($c['kategorie_id']) && (int)$c['kategorie_id'] === $id) {
        $id = null;   // passt schon – nichts vorzuschlagen
    }

    db_exec('DELETE FROM contact_kategorie_vorschlaege WHERE contact_id = ?', [$contactId]);
    if ($id !== null) {
        db_exec('INSERT INTO contact_kategorie_vorschlaege (contact_id, kategorie_id, modell) VALUES (?,?,?)',
            [$contactId, $id, mb_substr($modell, 0, 100)]);
    }
    return ['kategorie_id' => $id, 'modell' => $modell];
}
