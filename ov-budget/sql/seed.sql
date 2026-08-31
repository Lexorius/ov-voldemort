-- ============================================================
--  Grunddaten – alles im Adminbereich änderbar
-- ============================================================
SET NAMES utf8mb4;

-- ---------- Fachgruppen / Einheiten des OV ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES
('fachgruppe','OV-Stab','ov-stab','#0f766e',10),
('fachgruppe','Zugtrupp','zugtrupp','#0369a1',20),
('fachgruppe','Bergungsgruppe 1','b1','#1d4ed8',30),
('fachgruppe','Bergungsgruppe 2','b2','#1d4ed8',40),
('fachgruppe','FGr N (Notversorgung/Notinstandsetzung)','fgr-n','#7c3aed',50),
('fachgruppe','FGr W (Wassergefahren)','fgr-w','#0891b2',60),
('fachgruppe','FGr R (Räumen)','fgr-r','#b45309',70),
('fachgruppe','FGr E (Elektroversorgung)','fgr-e','#ca8a04',80),
('fachgruppe','FGr WP (Wasserschaden/Pumpen)','fgr-wp','#0e7490',90),
('fachgruppe','FGr Log-V (Verpflegung)','fgr-log-v','#be123c',100),
('fachgruppe','FGr Log-M (Materialwirtschaft)','fgr-log-m','#9f1239',110),
('fachgruppe','Jugendgruppe','jugend','#16a34a',120),
('fachgruppe','Ortsverband (übergreifend)','ov','#334155',130);

-- ---------- Funktionen im OV ----------
INSERT IGNORE INTO list_items (list_key, label, slug, sort_order) VALUES
('funktion','Ortsbeauftragte:r','ob',10),
('funktion','stellv. Ortsbeauftragte:r','stellv-ob',20),
('funktion','Zugführer:in','zugfuehrer',30),
('funktion','stellv. Zugführer:in','stellv-zugfuehrer',40),
('funktion','Gruppenführer:in','gruppenfuehrer',50),
('funktion','Verwaltungsbeauftragte:r','verwaltungsbeauftragter',60),
('funktion','Ausbildungsbeauftragte:r','ausbildungsbeauftragter',70),
('funktion','Beauftragte:r für Öffentlichkeitsarbeit','oeffentlichkeitsarbeit',80),
('funktion','Jugendbetreuer:in','jugendbetreuer',90),
('funktion','Kraftfahrer:in / Fahrdienst','kraftfahrer',100),
('funktion','Fachberater:in','fachberater',110),
('funktion','Koch / Küche','koch',120),
('funktion','IT / Kommunikation','it',130),
('funktion','Liegenschaft / Haustechnik','liegenschaft',140);

-- ---------- Dringlichkeiten (weight = Gewicht für Priorisierung) ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, weight, sort_order, is_default) VALUES
('dringlichkeit','Kritisch – Einsatzfähigkeit gefährdet','kritisch','#b91c1c',100,10,0),
('dringlichkeit','Hoch','hoch','#ea580c',70,20,0),
('dringlichkeit','Mittel','mittel','#ca8a04',40,30,1),
('dringlichkeit','Niedrig','niedrig','#16a34a',15,40,0),
('dringlichkeit','Irgendwann','irgendwann','#64748b',5,50,0);

-- ---------- Status "Wünsch dir was" ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('wunsch_status','Neu','neu','#0284c7',10,1,0),
('wunsch_status','In Prüfung','pruefung','#7c3aed',20,0,0),
('wunsch_status','Angebot fehlt','angebot-fehlt','#a16207',30,0,0),
('wunsch_status','Priorisiert','priorisiert','#0d9488',40,0,0),
('wunsch_status','Für Haushalt eingeplant','eingeplant','#1d4ed8',50,0,0),
('wunsch_status','Bestellt','bestellt','#0891b2',60,0,0),
('wunsch_status','Beschafft','beschafft','#15803d',70,0,1),
('wunsch_status','Zurückgestellt','zurueckgestellt','#64748b',80,0,0),
('wunsch_status','Abgelehnt','abgelehnt','#b91c1c',90,0,1);

-- ---------- Kategorien ----------
INSERT IGNORE INTO list_items (list_key, label, slug, sort_order) VALUES
('kategorie','Ausstattung / Gerät',    'ausstattung',10),
('kategorie','Werkzeug',               'werkzeug',20),
('kategorie','PSA / Bekleidung',       'psa',30),
('kategorie','Fahrzeug / Anhänger',    'fahrzeug',40),
('kategorie','IT / Kommunikation',     'it',50),
('kategorie','Liegenschaft',           'liegenschaft',60),
('kategorie','Ausbildung',             'ausbildung',70),
('kategorie','Verpflegung',            'verpflegung',80),
('kategorie','Verbrauchsmaterial',     'verbrauch',90),
('kategorie','Öffentlichkeitsarbeit',  'oea',100),
('kategorie','Sonstiges',              'sonstiges',110);

-- ---------- Einheiten (Mengeneinheit) ----------
INSERT IGNORE INTO list_items (list_key, label, slug, sort_order, is_default) VALUES
('einheit','Stück','stk',10,1),
('einheit','Paar','paar',20,0),
('einheit','Satz','satz',30,0),
('einheit','Meter','m',40,0),
('einheit','Liter','l',50,0),
('einheit','Pauschal','pauschal',60,0);

-- ---------- ToDo-Status ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('todo_status','Offen','offen','#0284c7',10,1,0),
('todo_status','In Arbeit','in-arbeit','#ca8a04',20,0,0),
('todo_status','Wartet auf Zuarbeit','wartet','#7c3aed',30,0,0),
('todo_status','Erledigt','erledigt','#15803d',40,0,1),
('todo_status','Verworfen','verworfen','#64748b',50,0,1);

-- ---------- ToDo-Priorität ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, weight, sort_order, is_default) VALUES
('todo_prioritaet','Sofort','sofort','#b91c1c',100,10,0),
('todo_prioritaet','Hoch','hoch','#ea580c',70,20,0),
('todo_prioritaet','Normal','normal','#0284c7',40,30,1),
('todo_prioritaet','Niedrig','niedrig','#16a34a',10,40,0);

-- ---------- Ausgabenkategorien (Budgetmodul) ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES
('ausgabe_kategorie','Liegenschaft / Haus',        'haus',        '#b45309',10),
('ausgabe_kategorie','Nebenkosten (Strom, Wasser, Heizung)','nebenkosten','#a16207',20),
('ausgabe_kategorie','Reparatur / Instandhaltung', 'reparatur',   '#92400e',30),
('ausgabe_kategorie','Kraftstoff / Tanken',        'tanken',      '#1d4ed8',40),
('ausgabe_kategorie','Fahrzeugunterhalt',          'fahrzeug',    '#1e40af',50),
('ausgabe_kategorie','Getraenke',                  'getraenke',   '#0891b2',60),
('ausgabe_kategorie','Verpflegung',                'verpflegung', '#0e7490',70),
('ausgabe_kategorie','Ausstattung / Geraet',       'ausstattung', '#7c3aed',80),
('ausgabe_kategorie','Werkzeug',                   'werkzeug',    '#6d28d9',90),
('ausgabe_kategorie','PSA / Bekleidung',           'psa',         '#be123c',100),
('ausgabe_kategorie','IT / Kommunikation',         'it',          '#0369a1',110),
('ausgabe_kategorie','Buero / Porto',              'buero',       '#475569',120),
('ausgabe_kategorie','Ausbildung',                 'ausbildung',  '#15803d',130),
('ausgabe_kategorie','Jugendarbeit',               'jugend',      '#16a34a',140),
('ausgabe_kategorie','Oeffentlichkeitsarbeit',     'oea',         '#c2410c',150),
('ausgabe_kategorie','Gebuehren / Versicherungen', 'gebuehren',   '#64748b',160),
('ausgabe_kategorie','Sonstiges',                  'sonstiges',   '#94a3b8',170);

-- ---------- Einnahmekategorien (Budgetmodul) ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES
('einnahme_kategorie','Einsatzkostenerstattung',        'einsatz',      '#15803d',10),
('einnahme_kategorie','Technische Hilfeleistung (THG)', 'thg',          '#166534',20),
('einnahme_kategorie','Amtshilfe / Anforderung',        'amtshilfe',    '#047857',30),
('einnahme_kategorie','Absicherung / Sanitaetsdienst',  'absicherung',  '#0d9488',40),
('einnahme_kategorie','Ausbildung / Lehrgangserstattung','ausbildung',  '#0891b2',50),
('einnahme_kategorie','Spenden',                        'spenden',      '#7c3aed',60),
('einnahme_kategorie','Zuwendung / Foerderung',         'foerderung',   '#6d28d9',70),
('einnahme_kategorie','Helfervereinigung',              'hv',           '#a16207',80),
('einnahme_kategorie','Verkauf / Erloese',              'verkauf',      '#ca8a04',90),
('einnahme_kategorie','Erstattung Nebenkosten',         'erstattung',   '#0369a1',100),
('einnahme_kategorie','Sonstiges',                      'sonstiges',    '#64748b',110);

-- ---------- Anlage-Typen ----------
INSERT IGNORE INTO list_items (list_key, label, slug, sort_order, is_default) VALUES
('anlage_typ','Angebot','angebot',10,1),
('anlage_typ','Datenblatt','datenblatt',20,0),
('anlage_typ','Foto','foto',30,0),
('anlage_typ','Sonstiges','sonstiges',40,0);

-- ============================================================
--  Einstellungen
-- ============================================================
INSERT IGNORE INTO settings (skey, svalue, label, hint, stype, sgroup, sort_order) VALUES
('app_name','OV-Budget','Name der Anwendung','Erscheint im Kopf und Browser-Titel','text','Allgemein',10),
('ov_name','THW Ortsverband Musterstadt','Name des Ortsverbands','','text','Allgemein',20),
('ov_kurz','OV Musterstadt','Kurzname','Für die mobile Ansicht','text','Allgemein',30),
('theme_color','#003399','Akzentfarbe','THW-Blau ist #003399','color','Allgemein',40),
('footer_text','Interne Planungshilfe – keine offizielle Beschaffungsplattform.','Fußzeile','','textarea','Allgemein',50),
('login_hinweis','Zugang erhältst du von der OV-Leitung.','Hinweistext auf der Anmeldeseite','','textarea','Allgemein',60),
('waehrung','EUR','Währung','ISO-Code, z.B. EUR','text','Allgemein',70),
('haushaltsjahr','2026','Aktuelles Haushaltsjahr','Vorbelegung für neue Budgets und Wünsche','number','Budget',10),
('mwst_satz','19','Standard-MwSt-Satz (%)','','number','Budget',20),
('budget_warn_prozent','90','Warnschwelle Budgetauslastung (%)','Ab diesem Wert wird der Topf rot dargestellt','number','Budget',30),

('wunsch_modul_name','Wünsch dir was','Bezeichnung des Wunsch-Moduls','','text','Wünsche',10),
('wunsch_intro','Trage hier ein, was deine Fachgruppe braucht. Je besser die Begründung und je konkreter das Angebot, desto einfacher die Priorisierung.','Einleitungstext im Wunsch-Modul','','textarea','Wünsche',20),
('wunsch_angebot_pflicht_ab','500','Angebot verpflichtend ab Nettobetrag','0 = nie verpflichtend','number','Wünsche',30),
('wunsch_begruendung_pflicht','1','Begründung ist Pflichtfeld','','bool','Wünsche',40),
('wunsch_voting_aktiv','1','Abstimmung (Daumen hoch) aktiv','Alle angemeldeten Benutzer dürfen Wünsche unterstützen','bool','Wünsche',50),
('wunsch_voting_punkte','5','Maximale Stimmen pro Person','0 = unbegrenzt','number','Wünsche',60),
('wunsch_user_darf_status','0','Normale Benutzer dürfen Status ändern','Sonst nur Leitung/Admin','bool','Wünsche',70),
('upload_max_mb','10','Maximale Dateigröße Upload (MB)','','number','Wünsche',80),
('upload_erlaubte_typen','pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,odt,ods','Erlaubte Dateiendungen','Komma-getrennt','text','Wünsche',90),
('wunsch_extra_felder','','Zusätzliche Freifelder','Ein Feld pro Zeile, Format: schluessel|Beschriftung|typ (text,textarea,number,bool,date)','textarea','Wünsche',100),

('todo_modul_name','Aufgaben','Bezeichnung des ToDo-Moduls','','text','Aufgaben',10),
('todo_intro','Aufgaben für den Ortsverband, einzelne Fachgruppen, Funktionen oder Personen.','Einleitungstext im Aufgaben-Modul','','textarea','Aufgaben',20),
('todo_user_darf_anlegen','1','Normale Benutzer dürfen Aufgaben anlegen','','bool','Aufgaben',30),
('todo_faellig_warntage','7','Aufgaben als "bald fällig" markieren (Tage)','','number','Aufgaben',40),

('divera_aktiv','0','Divera-24/7-Anbindung aktiv','','bool','Divera 24/7',10),
('divera_base_url','https://app.divera247.com/api','Basis-URL der Divera-API','Ohne abschließenden Schrägstrich','text','Divera 24/7',20),
('divera_accesskey','','Divera Accesskey','System-Benutzer-Accesskey aus der Divera-Verwaltung','password','Divera 24/7',30),
('divera_forms_path','/v2/forms','Pfad: Formularliste','Wird an die Basis-URL angehängt','text','Divera 24/7',40),
('divera_entries_path','/v2/forms/{form_id}/entries','Pfad: Formulareinträge','{form_id} wird ersetzt','text','Divera 24/7',50),
('divera_auth_mode','query','Übergabe des Accesskeys','query = ?accesskey=... , header = Authorization: Bearer ...','select','Divera 24/7',60),
('divera_timeout','15','Timeout in Sekunden','','number','Divera 24/7',70),
('divera_import_status','neu','Status für importierte Wünsche','slug aus der Liste wunsch_status','text','Divera 24/7',80),
('divera_cron_token','','Token für den automatischen Abruf','Aufruf: /cron.php?token=... (leer = deaktiviert)','text','Divera 24/7',90),

('budget_modul_name','Budget','Bezeichnung des Budget-Moduls','','text','Budget',5),
('budget_intro','Gesamtbudget des Haushaltsjahres, laufende Ausgaben und die daraus entstehende Uebersicht.','Einleitungstext im Budget-Modul','','textarea','Budget',6),
('ausgaben_betragsart','brutto','Betraege werden erfasst als','brutto oder netto - gilt fuer Ausgaben und Einnahmen','text','Budget',40),
('ausgaben_user_darf_sehen','1','Alle Mitglieder duerfen Buchungen sehen','Ausgaben und Einnahmen. Sonst nur Leitung und Administration','bool','Budget',50),
('session_lifetime','43200','Session-Laufzeit in Sekunden','Standard: 12 Stunden','number','Sicherheit',10),
('login_max_versuche','8','Fehlversuche bis Sperre','Sperre gilt pro Benutzername für die Sperrdauer','number','Sicherheit',20),
('login_sperre_minuten','15','Sperrdauer in Minuten','','number','Sicherheit',30),
('passwort_min_laenge','10','Mindestlänge Passwort','','number','Sicherheit',40);
