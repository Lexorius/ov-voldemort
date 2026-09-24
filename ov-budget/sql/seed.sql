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
('wunsch_status','Freigegeben – bitte bestellen','freigegeben','#ea580c',55,0,0),
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

-- ---------- Kontaktkategorien ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES
('kontakt_kategorie','Kommune / Verwaltung',   'kommune',    '#1d4ed8',10),
('kontakt_kategorie','Politik',                'politik',    '#1e40af',20),
('kontakt_kategorie','Behoerde',               'behoerde',   '#0369a1',30),
('kontakt_kategorie','Feuerwehr',              'feuerwehr',  '#b91c1c',40),
('kontakt_kategorie','Rettungsdienst',         'rettung',    '#be123c',50),
('kontakt_kategorie','Polizei',                'polizei',    '#0f766e',60),
('kontakt_kategorie','THW (andere Dienststelle)','thw',      '#003399',70),
('kontakt_kategorie','Hilfsorganisation',      'hilfsorg',   '#0891b2',80),
('kontakt_kategorie','Presse / Medien',        'presse',     '#c2410c',90),
('kontakt_kategorie','Firma / Lieferant',      'firma',      '#7c3aed',100),
('kontakt_kategorie','Foerderer / Spender',    'foerderer',  '#15803d',110),
('kontakt_kategorie','Helfervereinigung',      'hv',         '#a16207',120),
('kontakt_kategorie','Verein',                 'verein',     '#0d9488',130),
('kontakt_kategorie','Privatperson',           'privat',     '#64748b',140),
('kontakt_kategorie','Sonstiges',              'sonstiges',  '#94a3b8',150);

-- ---------- Einladungsstatus ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('einladung_status','Offen',          'offen',      '#64748b',10,1,0),
('einladung_status','Eingeladen',     'eingeladen', '#0284c7',20,0,0),
('einladung_status','Zugesagt',       'zugesagt',   '#15803d',30,0,1),
('einladung_status','Abgesagt',       'abgesagt',   '#b91c1c',40,0,1),
('einladung_status','Keine Rueckmeldung','keine',   '#a16207',50,0,0);

-- ---------- Besprechungsarten ----------
-- ---------- Fahrzeuge ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('fahrzeug_typ','Mannschaftstransportwagen (MTW)','mtw','#0369a1',10,0,0),
('fahrzeug_typ','Gerätekraftwagen (GKW)',        'gkw','#b45309',20,0,0),
('fahrzeug_typ','Lastkraftwagen',                'lkw','#7c3aed',30,0,0),
('fahrzeug_typ','Anhänger',                      'anhaenger','#0d9488',40,0,0),
('fahrzeug_typ','Gerät / Aggregat',              'geraet','#64748b',50,0,0),
('fahrzeug_typ','Sonstiges Fahrzeug',            'sonstiges','#94a3b8',60,0,0);

INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('fahrzeug_status','Einsatzbereit',          'einsatzbereit','#15803d',10,1,0),
('fahrzeug_status','Bedingt einsatzbereit',  'bedingt','#a16207',20,0,0),
('fahrzeug_status','Im Einsatz',             'im-einsatz','#0284c7',30,0,0),
('fahrzeug_status','In Wartung / Werkstatt', 'wartung','#7c3aed',40,0,0),
('fahrzeug_status','Nicht einsatzbereit',    'nicht-einsatzbereit','#b91c1c',50,0,0),
('fahrzeug_status','Ausgemustert',           'ausgemustert','#64748b',60,0,1);

INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('auftrag_art','Schadensmeldung',   'schaden','#b91c1c',10,1,0),
('auftrag_art','Instandsetzung',    'instandsetzung','#c2410c',20,0,0),
('auftrag_art','Wartung / Inspektion','wartung','#0284c7',30,0,0),
('auftrag_art','Hauptuntersuchung / SP','hu','#7c3aed',40,0,0),
('auftrag_art','UVV-Prüfung',       'uvv','#0d9488',50,0,0),
('auftrag_art','Unfallschaden',     'unfall','#991b1b',60,0,0),
('auftrag_art','Beschaffung / Umbau','umbau','#a16207',70,0,0);

INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('auftrag_status','Gemeldet',       'gemeldet','#0284c7',10,1,0),
('auftrag_status','Angenommen',     'angenommen','#1d4ed8',20,0,0),
('auftrag_status','In der Werkstatt','werkstatt','#7c3aed',30,0,0),
('auftrag_status','Teile bestellt', 'teile','#a16207',40,0,0),
('auftrag_status','Erledigt',       'erledigt','#15803d',50,0,1),
('auftrag_status','Verworfen',      'verworfen','#64748b',60,0,1);

INSERT IGNORE INTO list_items (list_key, label, slug, color, weight, sort_order, is_default, is_final) VALUES
('auftrag_prioritaet','Fahrzeug steht still','still','#b91c1c',30,10,0,0),
('auftrag_prioritaet','Hoch',                'hoch','#c2410c',20,20,0,0),
('auftrag_prioritaet','Normal',              'normal','#0284c7',10,30,1,0),
('auftrag_prioritaet','Bei Gelegenheit',     'gelegenheit','#64748b',0,40,0,0);

-- ---------- Teilnahme an Besprechungen ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('teilnahme_status','Eingeladen',       'eingeladen',   '#64748b',10,1,0),
('teilnahme_status','Zugesagt',         'zugesagt',     '#0284c7',20,0,0),
('teilnahme_status','Teilgenommen',     'teilgenommen', '#15803d',30,0,1),
('teilnahme_status','Entschuldigt',     'entschuldigt', '#a16207',40,0,1),
('teilnahme_status','Nicht erschienen', 'fehlt',        '#b91c1c',50,0,1);

INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default) VALUES
('besprechung_typ','OV-Stab / Leitungsrunde',   'ov-stab',        '#003399',10,0),
('besprechung_typ','Zugfuehrerbesprechung',     'zugfuehrer',     '#1d4ed8',20,0),
('besprechung_typ','Gruppenfuehrerbesprechung', 'gruppenfuehrer', '#0369a1',30,0),
('besprechung_typ','Dienstbesprechung',         'dienst',         '#0f766e',40,1),
('besprechung_typ','Helferversammlung',         'helferversammlung','#15803d',50,0),
('besprechung_typ','Jugendgruppe',              'jugend',         '#16a34a',60,0),
('besprechung_typ','Sonstiges',                 'sonstiges',      '#64748b',70,0);

-- ---------- Arten von Veranstaltungen ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default) VALUES
('veranstaltung_typ','Ausbildung',            'ausbildung',      '#0369a1',10,0),
('veranstaltung_typ','Übung',                 'uebung',          '#b45309',20,0),
('veranstaltung_typ','Helferversammlung',     'helferversammlung','#15803d',30,0),
('veranstaltung_typ','Empfang',               'empfang',         '#7c3aed',40,0),
('veranstaltung_typ','Grillabend',            'grillabend',      '#be123c',50,0),
('veranstaltung_typ','Feier',                 'feier',           '#db2777',60,0),
('veranstaltung_typ','Tag der offenen Tür',   'tag-der-offenen-tuer','#0891b2',70,0),
('veranstaltung_typ','Jugendveranstaltung',   'jugend',          '#16a34a',80,0),
('veranstaltung_typ','Sonstiges',             'sonstiges',       '#64748b',90,1);

-- ---------- Status der Talking Points ----------
INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order, is_default, is_final) VALUES
('tp_status','Offen',         'offen',         '#0284c7',10,1,0),
('tp_status','Besprochen',    'besprochen',    '#15803d',20,0,1),
('tp_status','Beschlossen',   'beschlossen',   '#166534',30,0,1),
('tp_status','Vertagt',       'vertagt',       '#b45309',40,0,0),
('tp_status','Abgelehnt',     'abgelehnt',     '#b91c1c',45,0,1),
('tp_status','Zurueckgezogen','zurueckgezogen','#64748b',50,0,1);

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
('telefon_landesvorwahl','+49','Landesvorwahl für Rufnummern','Nummern mit führender 0 werden damit international gespeichert (z. B. 0151… wird +49 151…).','text','Allgemein',35),
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

('ha_benachrichtigung_aktiv','0','Benachrichtigungen über Home Assistant','Meldungen gehen an notify.<Ziel> – in der Regel die Companion-App. Je Person wird das Ziel im Profil hinterlegt.','bool','Home Assistant',100),
('ha_benachrichtigung_stunde','7','Tägliche Erinnerungen ab (Stunde)','Fällige Aufgaben, Fristen und die Besprechung von morgen','number','Home Assistant',110),
('ha_benachrichtigung_basis_url','','Adresse der Anwendung','Fuer den Link in der Meldung. Am besten der Panel-Link, z. B. https://DEINE-HA-ADRESSE/hassio/ingress/<add-on>. Die Adresse aus der Browserzeile enthaelt ein wechselndes Token und taugt nicht. Leer lassen = kein Link.','text','Home Assistant',120),
('notify_aufgabe_neu','1','Melden: neue Aufgabe','','bool','Home Assistant',130),
('notify_aufgabe_faellig','1','Melden: Aufgabe faellig','','bool','Home Assistant',131),
('notify_wunsch_freigabe','1','Melden: neuer Wunsch zur Freigabe','','bool','Home Assistant',132),
('notify_wunsch_freigegeben','1','Melden: eigener Wunsch freigegeben oder bestellt','','bool','Home Assistant',133),
('notify_auftrag_neu','1','Melden: neue Instandsetzungsmeldung','','bool','Home Assistant',134),
('notify_fahrzeug_ausfall','1','Melden: Fahrzeug faellt aus','','bool','Home Assistant',135),
('notify_fristen','1','Melden: Fristen laufen ab','','bool','Home Assistant',136),
('notify_besprechung','1','Melden: Besprechung am naechsten Tag','','bool','Home Assistant',137),
('notify_tp_anmerkung','1','Melden: neue Anmerkung zu einem Talking Point','','bool','Home Assistant',138),
('push_aktiv','0','Benachrichtigungen im Browser (Web Push)','Meldungen erscheinen auch bei geschlossener Seite. Braucht HTTPS; auf dem iPhone muss die Seite zum Home-Bildschirm hinzugefügt sein.','bool','Home Assistant',140),
('push_kontakt','','Kontaktadresse für die Push-Dienste','E-Mail-Adresse, die Google und Mozilla bei Problemen anschreiben können','text','Home Assistant',141),
('ha_mqtt_aktiv','0','Kennzahlen an Home Assistant melden','Über MQTT mit Auto-Discovery. Ohne eigene Angaben unten wird der Broker des Mosquitto-Add-ons genutzt.','bool','Home Assistant',10),
('ha_mqtt_intervall_minuten','5','Meldung alle (Minuten)','','number','Home Assistant',20),
('ha_mqtt_fahrzeuge','0','Je Fahrzeug eigene Entitäten','Status, Funkstatus, HU, SP, Kilometerstand und offene Auftraege je Fahrzeug','bool','Home Assistant',30),
('ha_mqtt_position','0','Fahrzeugstandort als Karte-Eintrag','Braucht Positionen aus Divera; erzeugt je Fahrzeug einen device_tracker','bool','Home Assistant',40),
('ha_mqtt_basis','ovbudget','Themenbaum (MQTT-Topic)','Vorgabe ovbudget. Nur Kleinbuchstaben, Ziffern, - und _','text','Home Assistant',50),
('ha_mqtt_host','','Broker-Host','Leer lassen, wenn das Mosquitto-Add-on genutzt werden soll','text','Home Assistant',60),
('ha_mqtt_port','1883','Broker-Port','','number','Home Assistant',70),
('ha_mqtt_user','','Broker-Benutzer','','text','Home Assistant',80),
('ha_mqtt_passwort','','Broker-Passwort','','password','Home Assistant',90),
('divera_aktiv','0','Divera-24/7-Anbindung aktiv','','bool','Divera 24/7',10),
('divera_base_url','https://app.divera247.com/api','Basis-URL der Divera-API','Ohne abschließenden Schrägstrich','text','Divera 24/7',20),
('divera_accesskey','','Divera Accesskey','System-Benutzer-Accesskey aus der Divera-Verwaltung','password','Divera 24/7',30),
('divera_forms_path','/v2/reporttypes','Pfad: Formularliste','Wird an die Basis-URL angehängt. Divera nennt Formulare reporttypes.','text','Divera 24/7',40),
('divera_entries_path','/v2/reporttypes/{form_id}/reports','Pfad: Formulareinträge','{form_id} wird ersetzt. Divera liefert 50 je Seite; weitere Seiten holt die Anwendung selbst.','text','Divera 24/7',50),
('divera_auth_mode','query','Übergabe des Accesskeys','query = ?accesskey=... , header = Authorization: Bearer ...','select','Divera 24/7',60),
('divera_timeout','15','Timeout in Sekunden','','number','Divera 24/7',70),
('divera_import_status','neu','Status für importierte Wünsche','slug aus der Liste wunsch_status','text','Divera 24/7',80),
('divera_formular_intervall_minuten','15','Formulare automatisch abrufen alle (Minuten)','Gilt für Formulare mit automatischem Import. Jeder Abruf holt alle Seiten (50 Einträge je Seite).','number','Divera 24/7',95),
('divera_status_bearbeitung','freigegeben','Rückmeldung „In Bearbeitung“ bei Wunsch-Status','Slugs aus der Liste wunsch_status, durch Komma getrennt. Gilt nur für Formulare mit Status-Rückmeldung. Themen gelten als in Bearbeitung, sobald sie auf einer Tagesordnung stehen.','text','Divera 24/7',96),
('divera_status_abgeschlossen','bestellt','Rückmeldung „Abgeschlossen“ bei Wunsch-Status','Slugs aus der Liste wunsch_status, durch Komma getrennt. Abschließende Status (z. B. beschafft, abgelehnt) gelten immer als abgeschlossen, besprochene Themen ebenso.','text','Divera 24/7',97),
('divera_position_intervall_minuten','0','Standort erneuern alle (Minuten)','0 = bei jedem Abruf des Funkstatus. Sonst bleibt die letzte Position stehen, bis die Zeit um ist – schont Schreibzugriffe und haelt die Karte ruhiger.','number','Divera 24/7',101),
('divera_position_journal_meter','0','Standortwechsel ins Journal ab (Meter)','0 = aus. Sonst ein Journaleintrag, sobald sich ein Fahrzeug seit dem letzten Eintrag weiter als angegeben bewegt hat. Beim Funkstatuswechsel steht der Standort ohnehin dabei.','number','Divera 24/7',102),
('divera_fahrzeuge_aktiv','0','Fahrzeugdaten aus Divera abgleichen','Funkstatus, Position und Besatzung (v2) sowie OPTA, RIC, Kennzeichen und ISSI (v3)','bool','Divera 24/7',100),
('divera_status_intervall_minuten','2','Funkstatus abrufen alle (Minuten)','Ein Aufruf je Abruf. Wechsel dazwischen gehen verloren – kurze Abstände geben ein genaueres Fahrtenbuch.','number','Divera 24/7',110),
('divera_stamm_intervall_minuten','60','Stammdaten abrufen alle (Minuten)','OPTA, RIC, Kennzeichen, ISSI ändern sich selten','number','Divera 24/7',120),
('divera_personal_key','','Persönlicher Accesskey für /api/v3','Für die Formulare (Wünsch dir was) und die Stammdaten der Fahrzeuge. Leer = der Accesskey oben.','password','Divera 24/7',130),
('divera_status_journal','1','Funkstatus ins Journal schreiben','Jeder Statuswechsel steht dann als Fahrtenbuch in der Fahrzeugakte','bool','Divera 24/7',140),
('divera_position_speichern','1','Position der Fahrzeuge speichern','Letzte bekannte Position mit Link auf die Karte','bool','Divera 24/7',150),
('divera_besatzung_anzeigen','1','Besatzung laut Divera anzeigen','Namen der zugeordneten Personen in der Fahrzeugakte','bool','Divera 24/7',160),
('divera_cron_token','','Token für den automatischen Abruf','Aufruf: /cron.php?token=... (leer = deaktiviert). Der fertige Aufruf steht verdeckt unter Verwaltung → Divera 24/7.','password','Divera 24/7',90),

('fahrzeug_modul_name','Fahrzeuge','Bezeichnung des Fahrzeugmoduls','','text','Fahrzeuge',10),
('fahrzeug_intro','Fahrzeuge des Ortsverbands mit Fahrzeugakte, Journal und Instandsetzungsauftraegen.','Einleitungstext im Fahrzeugmodul','','textarea','Fahrzeuge',20),
('fahrzeug_user_darf_sehen','1','Alle Mitglieder duerfen Fahrzeuge sehen','Sonst nur Leitung und Administration','bool','Fahrzeuge',30),
('fahrzeug_user_darf_melden','1','Alle Mitglieder duerfen Schaeden melden','Legt einen Auftrag an und schreibt ins Journal','bool','Fahrzeuge',40),
('connector_aktiv','0','Standortmeldung per QR-Code','Fahrzeuge bekommen einen QR-Code, ueber den jede Person ohne Zugang den Standort melden kann. Braucht den OV-Budget-Connector auf einem oeffentlich erreichbaren Webserver.','bool','Fahrzeuge',80),
('connector_intervall_minuten','2','Meldungen abholen alle (Minuten)','','number','Fahrzeuge',82),
('connector_park_minuten','60','Parkposition ab (Minuten)','So lange muss ein Fahrzeug an derselben Stelle stehen, bevor es einen Journaleintrag gibt','number','Fahrzeuge',83),
('connector_park_radius_meter','50','Derselbe Ort bis (Meter)','Wie weit sich ein Fahrzeug bewegen darf, ohne dass es als Ortswechsel gilt','number','Fahrzeuge',84),
('fahrzeug_karte','1','Karte in der Fahrzeugakte zeigen','Bindet eine Karte von OpenStreetMap ein. Der Browser laedt sie direkt dort; ohne Internet oder wenn das nicht gewuenscht ist, bleibt der Verweis auf die Karte.','bool','Fahrzeuge',75),
('fahrzeug_frist_warnung_tage','30','Vorwarnung fuer HU, SP und UVV (Tage)','Ab wann eine Frist als bald faellig gilt','number','Fahrzeuge',50),
('fahrzeug_dokument_typen','pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,odt,ods,txt','Erlaubte Dokumenttypen bei Fahrzeugen','Dateiendungen, durch Komma getrennt. Die Groesse begrenzt die allgemeine Upload-Grenze.','text','Fahrzeuge',55),
('fahrzeug_extra_felder','','Zusaetzliche Felder fuer Fahrzeuge','Ein Feld pro Zeile, Format: schluessel|Beschriftung|typ (text,textarea,number,bool,date)','textarea','Fahrzeuge',60),

('stein_aktiv','0','Stein.APP-Abgleich aktiv','Holt regelmaessig den Stand aller Fahrzeuge und schreibt Aenderungen ins Journal','bool','Stein.APP',10),
('stein_api_key','','API-Schluessel (Bearer-Token)','Aus der Stein.APP, gleicher Schluessel wie fuer die Home-Assistant-Integration','password','Stein.APP',20),
('stein_bu_id','','BU-ID des Ortsverbands','Nummer der Organisationseinheit in der Stein.APP','text','Stein.APP',30),
('stein_intervall_minuten','10','Abstand zwischen zwei Abrufen (Minuten)','Die Schnittstelle hat ein striktes Rate Limit. Weniger als 10 Minuten sind nicht zu empfehlen.','number','Stein.APP',40),
('stein_sync_beim_aufruf','1','Abgleich beim Oeffnen des Moduls','Nur, wenn der letzte Abruf laenger als das Intervall zurueckliegt','bool','Stein.APP',50),
('stein_auto_anlegen','0','Unbekannte Fahrzeuge selbst anlegen','Nur mit erkennbarem Kennzeichen; alles andere wird in der Verwaltung zum Zuordnen angeboten','bool','Stein.APP',60),
('stein_base_url','https://stein.app/api/api/ext','Basis-URL der Schnittstelle','Nur aendern, wenn die Stein.APP umzieht','text','Stein.APP',70),
('stein_timeout','15','Zeitlimit je Abruf (Sekunden)','','number','Stein.APP',80),
('stein_webhook_secret','','Webhook-Secret der Stein.APP','Aus den OV-Einstellungen der Stein.APP. Damit meldet die Stein.APP Aenderungen sofort an /webhook.php - das ist schonender als regelmaessiges Abfragen. Die Adresse muss dafuer von aussen erreichbar sein.','password','Stein.APP',85),
('stein_debug','0','Antworten der Stein.APP mitschneiden','Nur zur Fehlersuche: legt die Antworten als Datei zum Herunterladen ab (Verwaltung - Stein.APP). Der API-Schluessel steht nicht darin. Hoechstens 20 Dateien, danach werden die aeltesten geloescht.','bool','Stein.APP',95),
('cron_token','','Token fuer den automatischen Abruf','Aufruf: /cron.php?token=... (leer = nur ueber die Kommandozeile)','password','Stein.APP',90),
('budget_modul_name','Budget','Bezeichnung des Budget-Moduls','','text','Budget',5),
('budget_intro','Gesamtbudget des Haushaltsjahres, laufende Ausgaben und die daraus entstehende Uebersicht.','Einleitungstext im Budget-Modul','','textarea','Budget',6),
('budget_rundung','0','Betraege in der Uebersicht runden','0 = centgenau, sonst auf 10, 100 oder 1000 runden. Betrifft nur die Budgetuebersicht, nicht die Listen.','select','Budget',35),
('ausgaben_betragsart','brutto','Betraege werden erfasst als','brutto oder netto - gilt fuer Ausgaben und Einnahmen','text','Budget',40),
('bestell_eigene_freigeben','1','Eigene Wünsche selbst freigeben erlaubt','Aus = Vier-Augen-Prinzip: Wer einen Wunsch angelegt hat, darf ihn nicht selbst zur Bestellung freigeben','bool','Budget',60),
('ausgaben_user_darf_sehen','1','Alle Mitglieder duerfen Buchungen sehen','Ausgaben und Einnahmen. Sonst nur Leitung und Administration','bool','Budget',50),
('kontakte_modul_name','Kontakte','Bezeichnung des Kontakt-Moduls','','text','Kontakte',10),
('kontakte_intro','Ansprechpartner des Ortsverbands und Verteiler fuer Einladungen.','Einleitungstext im Kontakt-Modul','','textarea','Kontakte',20),
('kontakte_user_darf_sehen','0','Alle Mitglieder duerfen Kontakte sehen','Kontakte enthalten personenbezogene Daten. Standard: nur Leitung und Administration.','bool','Kontakte',30),
('kontakte_anrede_vorgabe','Sehr geehrte Damen und Herren','Vorgabe fuer die Briefanrede','Wird verwendet, wenn beim Kontakt keine eigene Anrede hinterlegt ist','text','Kontakte',40),
('kontakte_extra_felder','','Zusaetzliche Felder fuer Kontakte','Ein Feld pro Zeile, Format: schluessel|Beschriftung|typ (text,textarea,number,bool,date)','textarea','Kontakte',50),
('besprechung_modul_name','Besprechungen','Bezeichnung des Besprechungs-Moduls','','text','Besprechungen',10),
('tp_bezeichnung','Talking Points','Bezeichnung fuer die Themen','z.B. Talking Points, Themen oder Tagesordnungspunkte','text','Besprechungen',20),
('besprechung_intro','Themen sammeln, auf Besprechungen setzen und Ergebnisse festhalten.','Einleitungstext','','textarea','Besprechungen',30),
('besprechung_user_darf_sehen','1','Alle Mitglieder duerfen Besprechungen sehen','Sonst nur Leitung und Administration','bool','Besprechungen',40),
('tp_user_darf_anlegen','1','Alle Mitglieder duerfen Themen einbringen','Eigene Themen bleiben bearbeitbar, bis sie besprochen sind','bool','Besprechungen',50),
('protokoll_anmerkungen','protokoll','Anmerkungen im Ausdruck','Ob die Anmerkungen zu den Talking Points klein gedruckt mit erscheinen. Beim Drucken laesst es sich jedes Mal umschalten.','select','Besprechungen',70),
('protokoll_anmerkungen_namen','1','Im Ausdruck Namen und Zeitpunkt der Anmerkungen zeigen','Aus = nur der Text','bool','Besprechungen',71),
('tp_dauer_vorgabe','10','Vorgabedauer je Thema (Minuten)','Fuer die geplanten Uhrzeiten in der Tagesordnung','number','Besprechungen',60),
('serie_vorlauf_tage','60','Wiederkehrende Besprechungen: Termine im Voraus (Tage)','So weit in die Zukunft werden Termine einer Serie angelegt, damit man Themen daraufsetzen kann','number','Besprechungen',70),
('veranstaltung_modul_name','Veranstaltungen','Bezeichnung des Veranstaltungsmoduls','','text','Veranstaltungen',10),
('veranstaltung_intro','Veranstaltungen des Ortsverbands: Termin, Budget, Rechnungen und die Gaesteliste an einer Stelle.','Einleitungstext im Veranstaltungsmodul','','textarea','Veranstaltungen',20),
('veranstaltung_user_darf_sehen','1','Alle Mitglieder duerfen Veranstaltungen sehen','Anlegen und aendern darf nur die OV-Leitung','bool','Veranstaltungen',30),
('veranstaltung_code_laenge','6','Laenge des Einladungscodes','Anzahl der Zeichen hinter der kurzen Adresse. Vorgabe fuer neue Veranstaltungen, je Veranstaltung aenderbar. Mehr Zeichen heisst schwerer zu erraten.','number','Veranstaltungen',40),
('veranstaltung_begleiter_max','0','Begleiter je eingeladener Person (Vorgabe)','0 = niemand bringt jemanden mit. Je Veranstaltung aenderbar.','number','Veranstaltungen',50),
('veranstaltung_kommentare','1','Kommentare auf der Einladungsseite (Vorgabe)','Eingeladene koennen der Rueckmeldung eine Nachricht mitgeben. Je Veranstaltung aenderbar.','bool','Veranstaltungen',60),
('veranstaltung_ohne_gaesteliste','ausbildung,uebung','Arten ohne Gaesteliste','Kurznamen der Veranstaltungsarten, durch Komma getrennt (siehe Verwaltung - Auswahllisten - Veranstaltungsarten). Bei diesen Arten wird beim Anlegen nur die Teilnehmerzahl erfasst, keine Einladungen. Je Veranstaltung umschaltbar.','text','Veranstaltungen',65),
('veranstaltung_intervall_minuten','10','Rueckmeldungen abholen alle (Minuten)','Wie oft der automatische Abruf beim Connector nach neuen Rueckmeldungen sieht','number','Veranstaltungen',70),
('session_lifetime','43200','Session-Laufzeit in Sekunden','Standard: 12 Stunden','number','Sicherheit',10),
('login_max_versuche','8','Fehlversuche bis Sperre','Sperre gilt pro Benutzername für die Sperrdauer','number','Sicherheit',20),
('login_sperre_minuten','15','Sperrdauer in Minuten','','number','Sicherheit',30),
('passwort_min_laenge','10','Mindestlänge Passwort','','number','Sicherheit',40);

-- ---------- Bestellberechtigungen ----------
-- Nur Vorgaben: vorhandene Zeilen bleiben unberührt, Änderungen in der
-- Verwaltung werden also nicht überschrieben.
INSERT IGNORE INTO bestell_rechte (rolle, darf_freigeben, darf_bestellen) VALUES
('admin',1,1),
('leitung',1,1),
('user',0,0);

INSERT IGNORE INTO bestell_rechte (funktion_id, darf_freigeben, darf_bestellen)
SELECT id, 1, 0 FROM list_items WHERE list_key = 'funktion' AND slug IN ('ob','stellv-ob');

INSERT IGNORE INTO bestell_rechte (funktion_id, darf_freigeben, darf_bestellen)
SELECT id, 0, 1 FROM list_items WHERE list_key = 'funktion' AND slug = 'verwaltungsbeauftragter';
