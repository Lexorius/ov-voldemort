<?php
declare(strict_types=1);

/**
 * Schlagwörter für Kontakte – und Vorschläge dazu von der KI.
 *
 * Die Schlagwörter sind eine feste Auswahlliste (kontakt_tag), die die
 * Verwaltung pflegt. Die KI bekommt genau diese Liste mit und darf nur
 * daraus wählen; alles, was nicht in der Liste steht, fällt beim Lesen
 * der Antwort weg. Sie legt nie selbst Schlagwörter an und setzt auch
 * keine – sie macht je Kontakt Vorschläge, die jemand übernimmt oder
 * verwirft.
 *
 * Ablauf: Kontakt anlegen (oder „vorschlagen lassen") → Auftrag in
 * ki_auftraege mit typ „kontakt" → der minütliche Abruf fragt Ollama →
 * Vorschläge in contact_tag_vorschlaege → im Kontakt übernehmen.
 */

/** Höchstens so viele Vorschläge je Kontakt */
const KONTAKT_TAG_VORSCHLAEGE_MAX = 4;

/* ---------------- Schlagwörter am Kontakt ---------------- */

/** Alle Schlagwörter der Liste, aktiv oder alle: [id => Zeile] */
function kontakt_tags_liste(bool $nurAktive = true): array
{
    $out = [];
    foreach (list_items('kontakt_tag', $nurAktive) as $i) {
        $out[(int)$i['id']] = $i;
    }
    return $out;
}

/** Schlagwörter eines Kontakts als Listeneinträge */
function kontakt_tags(int $contactId): array
{
    return db_all(
        'SELECT l.*, t.quelle FROM contact_tags t JOIN list_items l ON l.id = t.tag_id
         WHERE t.contact_id = ? ORDER BY l.sort_order, l.label',
        [$contactId]
    );
}

/** Schlagwörter eines Kontakts setzen (ersetzt die bisherigen) */
function kontakt_tags_setzen(int $contactId, array $tagIds, string $quelle = 'hand'): void
{
    db_exec('DELETE FROM contact_tags WHERE contact_id = ?', [$contactId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn($i) => $i > 0))) as $tid) {
        db_exec('INSERT IGNORE INTO contact_tags (contact_id, tag_id, quelle) VALUES (?,?,?)', [$contactId, $tid, $quelle]);
    }
}

/** Schlagwörter hinzufügen, ohne vorhandene anzutasten. Gibt die Zahl der neuen. */
function kontakt_tags_hinzufuegen(int $contactId, array $tagIds, string $quelle = 'ki'): int
{
    $n = 0;
    foreach (array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn($i) => $i > 0))) as $tid) {
        $n += db_exec('INSERT IGNORE INTO contact_tags (contact_id, tag_id, quelle) VALUES (?,?,?)', [$contactId, $tid, $quelle]);
    }
    return $n;
}

/** Optionen für das Auswahl-Partial: [[id, text, sub]] */
function kontakt_tag_optionen(): array
{
    $out = [];
    foreach (kontakt_tags_liste() as $t) {
        $out[] = ['id' => (int)$t['id'], 'text' => (string)$t['label'], 'sub' => (string)$t['description']];
    }
    return $out;
}

/* ---------------- Vorschläge ---------------- */

/** Offene Vorschläge eines Kontakts als Listeneinträge (mit Modell und Zeit) */
function kontakt_tag_vorschlaege(int $contactId): array
{
    return db_all(
        'SELECT l.*, v.modell, v.erstellt_am FROM contact_tag_vorschlaege v JOIN list_items l ON l.id = v.tag_id
         WHERE v.contact_id = ? ORDER BY l.sort_order, l.label',
        [$contactId]
    );
}

/**
 * Vorschläge entscheiden: die übernommenen werden Schlagwörter, alle
 * Vorschläge des Kontakts verschwinden. Gibt die Zahl der übernommenen.
 */
function kontakt_tag_vorschlaege_entscheiden(int $contactId, array $uebernehmen): int
{
    $n = kontakt_tags_hinzufuegen($contactId, $uebernehmen, 'ki');
    db_exec('DELETE FROM contact_tag_vorschlaege WHERE contact_id = ?', [$contactId]);
    return $n;
}

/** Kontakte mit offenen Vorschlägen samt Kontaktdaten und den Vorschlägen */
function kontakt_tag_vorschlaege_alle(int $limit = 200): array
{
    $kontakte = db_all(
        'SELECT c.*, ka.label AS kategorie_label, ka.color AS kategorie_color,
                (SELECT COUNT(*) FROM contact_tag_vorschlaege v WHERE v.contact_id = c.id) AS vorschlaege
         FROM contacts c LEFT JOIN list_items ka ON ka.id = c.kategorie_id
         WHERE EXISTS (SELECT 1 FROM contact_tag_vorschlaege v WHERE v.contact_id = c.id)
         ORDER BY c.nachname, c.vorname, c.organisation LIMIT ' . max(1, $limit)
    );
    foreach ($kontakte as &$c) {
        $c['tags'] = kontakt_tags((int)$c['id']);
        $c['vorschlaege_liste'] = kontakt_tag_vorschlaege((int)$c['id']);
    }
    unset($c);
    return $kontakte;
}

/* ---------------- Einreihen ---------------- */

/** Vorschläge für diesen Kontakt anfordern (Auftrag in der KI-Warteschlange) */
function kontakt_tags_vorschlagen_lassen(int $contactId): bool
{
    return ki_einreihen('kontakt', $contactId);
}

/**
 * Alle aktiven Kontakte ohne Schlagwörter, ohne offene Vorschläge und
 * ohne Auftrag einreihen. Gibt die Zahl der neuen Aufträge.
 */
function kontakt_tags_alle_einreihen(): int
{
    if (!ki_modul_aktiv('kontakt')) {
        return 0;
    }
    return db_exec(
        "INSERT INTO ki_auftraege (typ, datei_id, status) SELECT 'kontakt', c.id, 'offen' FROM contacts c
         WHERE c.is_active = 1
           AND NOT EXISTS (SELECT 1 FROM contact_tags t WHERE t.contact_id = c.id)
           AND NOT EXISTS (SELECT 1 FROM contact_tag_vorschlaege v WHERE v.contact_id = c.id)
           AND NOT EXISTS (SELECT 1 FROM ki_auftraege a WHERE a.typ = 'kontakt' AND a.datei_id = c.id)"
    );
}

/** Zähler für die Vorschlagsseite */
function kontakt_tags_status(): array
{
    $s = ['mit_tags' => 0, 'ohne_tags' => 0, 'vorschlaege' => 0, 'offen' => 0, 'fehler' => 0];
    $s['mit_tags'] = (int)db_val('SELECT COUNT(DISTINCT contact_id) FROM contact_tags', [], 0);
    $s['ohne_tags'] = (int)db_val('SELECT COUNT(*) FROM contacts c WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM contact_tags t WHERE t.contact_id = c.id)', [], 0);
    $s['vorschlaege'] = (int)db_val('SELECT COUNT(DISTINCT contact_id) FROM contact_tag_vorschlaege', [], 0);
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
 * Die Bitte an das Modell: der Kontakt, die erlaubte Liste, strenge
 * Regeln. Es gehen nur Angaben mit, die für die Einordnung helfen –
 * keine Rufnummern, keine Anschrift bis auf den Ort.
 *
 * $tags: Listeneinträge mit label und description
 */
function kontakt_ki_prompt(array $kontakt, array $tags, array $extraFelder = []): string
{
    $liste = [];
    foreach ($tags as $t) {
        $zeile = '- ' . trim((string)$t['label']);
        if (trim((string)($t['description'] ?? '')) !== '') {
            $zeile .= ' (' . trim((string)$t['description']) . ')';
        }
        $liste[] = $zeile;
    }

    $zeilen = [];
    $name = trim(trim((string)($kontakt['anrede'] ?? '')) . ' ' . trim((string)($kontakt['titel'] ?? '')) . ' '
        . trim((string)($kontakt['vorname'] ?? '')) . ' ' . trim((string)($kontakt['nachname'] ?? '')));
    $name = (string)preg_replace('/\s+/', ' ', $name);
    $felder = [
        'Name'         => $name,
        'Organisation' => (string)($kontakt['organisation'] ?? ''),
        'Funktion'     => (string)($kontakt['position'] ?? ''),
        'Kategorie'    => (string)($kontakt['kategorie_label'] ?? ''),
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
    foreach ($felder as $k => $v) {
        $v = trim((string)$v);
        if ($v !== '') {
            $zeilen[] = $k . ': ' . $v;
        }
    }

    return "Du hilfst beim Kontaktverzeichnis eines THW-Ortsverbands. Antworte auf Deutsch.\n"
        . "Ordne dem Kontakt passende Schlagwörter zu. Erlaubt sind ausschließlich die Schlagwörter aus dieser Liste, "
        . "genau in dieser Schreibweise:\n" . implode("\n", $liste) . "\n\n"
        . "Regeln: Wähle höchstens " . KONTAKT_TAG_VORSCHLAEGE_MAX . " Schlagwörter und nur solche, die eindeutig zum Kontakt passen. "
        . "Erfinde keine neuen Schlagwörter und ändere keine. Passt nichts, bleibt die Liste leer.\n"
        . "Antworte ausschließlich mit einem JSON-Objekt dieser Form: {\"schlagwoerter\": [\"…\"]}\n\n"
        . "Kontakt:\n" . implode("\n", $zeilen);
}

/**
 * Die Antwort des Modells lesen und streng gegen die Liste prüfen:
 * zurück kommen nur IDs von Schlagwörtern, die es wirklich gibt (Label
 * oder Schlüssel, Groß/Klein und Umlaute egal). Unbekanntes fällt weg,
 * mehr als das Höchstmaß ebenso. Reine Funktion.
 */
function kontakt_ki_antwort_parsen(string $roh, array $tags): array
{
    $roh = trim($roh);
    $a = strpos($roh, '{');
    $z = strrpos($roh, '}');
    $genannt = [];
    if ($a !== false && $z !== false && $z >= $a) {
        $d = json_decode(substr($roh, $a, $z - $a + 1), true);
        $t = is_array($d) ? ($d['schlagwoerter'] ?? $d['schlagwörter'] ?? $d['tags'] ?? $d['keywords'] ?? []) : [];
        if (is_string($t)) {
            $t = preg_split('/[,;\n]+/', $t) ?: [];
        }
        foreach ((array)$t as $w) {
            if (is_scalar($w)) {
                $genannt[] = trim((string)$w);
            }
        }
    } elseif ($roh !== '') {
        // Kein JSON – vielleicht eine schlichte Aufzählung
        $genannt = array_map('trim', preg_split('/[,;\n]+/', $roh) ?: []);
    }

    $index = [];
    foreach ($tags as $t) {
        $id = (int)$t['id'];
        foreach ([(string)$t['label'], (string)($t['slug'] ?? '')] as $k) {
            $n = import_norm($k);
            if ($n !== '') {
                $index[$n] ??= $id;
            }
        }
    }

    $ids = [];
    foreach ($genannt as $w) {
        $n = import_norm(ltrim($w, '#-* '));
        if ($n !== '' && isset($index[$n]) && !in_array($index[$n], $ids, true)) {
            $ids[] = $index[$n];
        }
        if (count($ids) >= KONTAKT_TAG_VORSCHLAEGE_MAX) {
            break;
        }
    }
    return $ids;
}

/* ---------------- KI: Auswertung ---------------- */

/**
 * Einen Kontakt vom Textmodell einordnen lassen und die Vorschläge
 * speichern. Schon gesetzte Schlagwörter werden nicht noch einmal
 * vorgeschlagen. Wirft KiException; gibt ['tags' => [ids], 'modell' => …].
 */
function kontakt_ki_auswerten(int $contactId): array
{
    $c = contact_find($contactId);
    if (!$c) {
        throw new KiUebersprungen('Den Kontakt gibt es nicht mehr.');
    }
    $tags = kontakt_tags_liste();
    if (!$tags) {
        throw new KiUebersprungen('In der Auswahlliste „Schlagwörter (Kontakte)" steht noch nichts – ohne Liste gibt es keine Vorschläge.');
    }
    $modell = trim((string)setting('ki_modell', ''));
    if ($modell === '') {
        throw new KiException('Kein Textmodell eingetragen.');
    }
    $c['kategorie_label'] = list_label(!empty($c['kategorie_id']) ? (int)$c['kategorie_id'] : null, '');

    $antwort = ki_generieren($modell, kontakt_ki_prompt($c, array_values($tags), contact_extra_fields()));
    $ids = kontakt_ki_antwort_parsen($antwort, array_values($tags));

    $schon = array_map(static fn($t) => (int)$t['id'], kontakt_tags($contactId));
    $ids = array_values(array_diff($ids, $schon));

    db_exec('DELETE FROM contact_tag_vorschlaege WHERE contact_id = ?', [$contactId]);
    foreach ($ids as $tid) {
        db_exec('INSERT IGNORE INTO contact_tag_vorschlaege (contact_id, tag_id, modell) VALUES (?,?,?)',
            [$contactId, $tid, mb_substr($modell, 0, 100)]);
    }
    return ['tags' => $ids, 'modell' => $modell];
}
