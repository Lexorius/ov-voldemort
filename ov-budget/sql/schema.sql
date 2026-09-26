-- ============================================================
--  OV-Budget / "Wünsch dir was" – Datenbankschema
--  MySQL 5.7+ / MariaDB 10.3+
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Konfigurierbare Auswahllisten (Fachgruppen, Funktionen,
-- Dringlichkeiten, Status, Kategorien ...) – alles im Admin pflegbar
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS list_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_key      VARCHAR(50)  NOT NULL,
  label         VARCHAR(150) NOT NULL,
  slug          VARCHAR(150) NOT NULL DEFAULT '',
  description   VARCHAR(255) NOT NULL DEFAULT '',
  color         VARCHAR(20)  NOT NULL DEFAULT '#64748b',
  weight        INT          NOT NULL DEFAULT 0,
  sort_order    INT          NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  is_default    TINYINT(1)   NOT NULL DEFAULT 0,
  is_final      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_list_slug (list_key, slug),
  KEY idx_list (list_key, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Freie Konfiguration (Texte, Schwellwerte, Divera-Zugang ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  skey          VARCHAR(80)  NOT NULL,
  svalue        TEXT         NULL,
  label         VARCHAR(150) NOT NULL DEFAULT '',
  hint          VARCHAR(255) NOT NULL DEFAULT '',
  stype         VARCHAR(20)  NOT NULL DEFAULT 'text',
  sgroup        VARCHAR(50)  NOT NULL DEFAULT 'Allgemein',
  sort_order    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Benutzer
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  email         VARCHAR(150) NOT NULL DEFAULT '',
  display_name  VARCHAR(150) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','leitung','user') NOT NULL DEFAULT 'user',
  fachgruppe_id INT UNSIGNED NULL,
  phone         VARCHAR(60)  NOT NULL DEFAULT '',
  -- Benachrichtigungen über Home Assistant: Name des notify-Dienstes
  ha_notify     VARCHAR(120) NOT NULL DEFAULT '',
  notify_aktiv  TINYINT(1)   NOT NULL DEFAULT 1,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_pw TINYINT(1)  NOT NULL DEFAULT 0,
  last_login    DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  KEY idx_fg (fachgruppe_id),
  CONSTRAINT fk_user_fg FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Funktionen eines Benutzers (n:m, z.B. "Zugführer", "Verwaltungsbeauftragter")
CREATE TABLE IF NOT EXISTS user_functions (
  user_id       INT UNSIGNED NOT NULL,
  function_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, function_id),
  CONSTRAINT fk_uf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uf_fn   FOREIGN KEY (function_id) REFERENCES list_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pseudo-Budget: Haushaltsjahre und Töpfe
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS budgets (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jahr          SMALLINT     NOT NULL,
  name          VARCHAR(150) NOT NULL,
  kategorie_id  INT UNSIGNED NULL,
  fachgruppe_id INT UNSIGNED NULL,
  betrag_netto  DECIMAL(12,2) NOT NULL DEFAULT 0,
  beschreibung  TEXT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jahr (jahr),
  CONSTRAINT fk_budget_kat FOREIGN KEY (kategorie_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_budget_fg  FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- "Wünsch dir was"
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bezeichnung     VARCHAR(200) NOT NULL,
  beschreibung    TEXT NULL,
  begruendung     TEXT NULL,
  anzahl          DECIMAL(10,2) NOT NULL DEFAULT 1,
  einheit_id      INT UNSIGNED NULL,
  netto_einzel    DECIMAL(12,2) NOT NULL DEFAULT 0,
  netto_gesamt    DECIMAL(12,2) NOT NULL DEFAULT 0,
  mwst_satz       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  fachgruppe_id   INT UNSIGNED NULL,
  kategorie_id    INT UNSIGNED NULL,
  dringlichkeit_id INT UNSIGNED NULL,
  status_id       INT UNSIGNED NULL,
  budget_id       INT UNSIGNED NULL,
  -- Wunsch gehört zu einem Fahrzeug (Ersatzteil, Ausstattung); Verweis folgt in Wanderung 020
  vehicle_id      INT UNSIGNED NULL,
  nice_to_have    TINYINT(1)   NOT NULL DEFAULT 0,
  prioritaet      INT          NOT NULL DEFAULT 0,
  benoetigt_bis   DATE         NULL,
  lieferant       VARCHAR(150) NOT NULL DEFAULT '',
  artikelnummer   VARCHAR(100) NOT NULL DEFAULT '',
  link            VARCHAR(500) NOT NULL DEFAULT '',
  antragsteller   VARCHAR(150) NOT NULL DEFAULT '',
  extra           TEXT NULL,
  source          VARCHAR(30)  NOT NULL DEFAULT 'manuell',
  divera_form_id  VARCHAR(60)  NOT NULL DEFAULT '',
  divera_entry_id VARCHAR(60)  NOT NULL DEFAULT '',
  divera_status   TINYINT      NULL,
  freigegeben_von INT UNSIGNED NULL,
  freigegeben_am  DATETIME     NULL,
  created_by      INT UNSIGNED NULL,
  updated_by      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status_id),
  KEY idx_fg (fachgruppe_id),
  KEY idx_divera (divera_form_id, divera_entry_id),
  KEY idx_w_fahrzeug (vehicle_id),
  CONSTRAINT fk_w_fg   FOREIGN KEY (fachgruppe_id)    REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_kat  FOREIGN KEY (kategorie_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_dri  FOREIGN KEY (dringlichkeit_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_sta  FOREIGN KEY (status_id)        REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_ein  FOREIGN KEY (einheit_id)       REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_bud  FOREIGN KEY (budget_id)        REFERENCES budgets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_w_cb   FOREIGN KEY (created_by)       REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_w_ub   FOREIGN KEY (updated_by)       REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_w_frei FOREIGN KEY (freigegeben_von)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wish_attachments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wish_id       INT UNSIGNED NOT NULL,
  stored_name   VARCHAR(120) NOT NULL,
  orig_name     VARCHAR(255) NOT NULL,
  mime          VARCHAR(120) NOT NULL DEFAULT '',
  size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
  kind          VARCHAR(30)  NOT NULL DEFAULT 'angebot',
  betrag_netto  DECIMAL(12,2) NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wish (wish_id),
  CONSTRAINT fk_att_wish FOREIGN KEY (wish_id) REFERENCES wishes(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wish_votes (
  wish_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  points        TINYINT      NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wish_id, user_id),
  CONSTRAINT fk_vote_wish FOREIGN KEY (wish_id) REFERENCES wishes(id) ON DELETE CASCADE,
  CONSTRAINT fk_vote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wish_comments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wish_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  body          TEXT NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wish (wish_id),
  CONSTRAINT fk_wc_wish FOREIGN KEY (wish_id) REFERENCES wishes(id) ON DELETE CASCADE,
  CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ToDo-Listen (OV / Fachgruppe / Funktion / Person)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS todos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel         VARCHAR(200) NOT NULL,
  beschreibung  TEXT NULL,
  target_type   ENUM('ov','fachgruppe','funktion','user') NOT NULL DEFAULT 'ov',
  target_id     INT UNSIGNED NULL,
  status_id     INT UNSIGNED NULL,
  prioritaet_id INT UNSIGNED NULL,
  faellig_am    DATE NULL,
  erledigt_am   DATETIME NULL,
  wish_id       INT UNSIGNED NULL,
  -- Herkunft aus einer Besprechung; die Fremdschlüssel setzt Wanderung 011,
  -- weil meetings und talking_points erst weiter unten angelegt werden
  meeting_id    INT UNSIGNED NULL,
  talking_point_id INT UNSIGNED NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_target (target_type, target_id),
  KEY idx_todo_meeting (meeting_id),
  KEY idx_todo_tp (talking_point_id),
  CONSTRAINT fk_todo_status FOREIGN KEY (status_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_todo_prio   FOREIGN KEY (prioritaet_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_todo_wish   FOREIGN KEY (wish_id)       REFERENCES wishes(id)     ON DELETE SET NULL,
  CONSTRAINT fk_todo_cb     FOREIGN KEY (created_by)    REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS todo_comments (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  todo_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_todo (todo_id),
  CONSTRAINT fk_tc_todo FOREIGN KEY (todo_id) REFERENCES todos(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Divera 24/7 – Formular-Definitionen und Import-Protokoll
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS divera_forms (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id       VARCHAR(60)  NOT NULL,
  name          VARCHAR(200) NOT NULL,
  -- wunsch = Wünsch dir was, thema = Talking Point für den Themenspeicher
  ziel          VARCHAR(20)  NOT NULL DEFAULT 'wunsch',
  -- Bearbeitungsstand an Divera zurückmelden (Weitergeleitet / In Bearbeitung / Abgeschlossen)
  status_sync   TINYINT(1)   NOT NULL DEFAULT 0,
  field_map     TEXT NULL,
  auto_import   TINYINT(1)   NOT NULL DEFAULT 0,
  default_status_id INT UNSIGNED NULL,
  default_fachgruppe_id INT UNSIGNED NULL,
  last_sync     DATETIME NULL,
  raw_schema    MEDIUMTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS divera_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id    VARCHAR(60) NOT NULL DEFAULT '',
  entry_id   VARCHAR(60) NOT NULL DEFAULT '',
  wish_id    INT UNSIGNED NULL,
  tp_id      INT UNSIGNED NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'ok',
  message    VARCHAR(500) NOT NULL DEFAULT '',
  payload    MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warteschlange und Protokoll der Benachrichtigungen
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  ereignis   VARCHAR(40)  NOT NULL DEFAULT '',
  titel      VARCHAR(150) NOT NULL DEFAULT '',
  text       VARCHAR(500) NOT NULL DEFAULT '',
  url        VARCHAR(255) NOT NULL DEFAULT '',
  status     ENUM('offen','gesendet','fehler') NOT NULL DEFAULT 'offen',
  versuche   TINYINT      NOT NULL DEFAULT 0,
  fehler     VARCHAR(300) NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_status (status, id),
  KEY idx_user (user_id),
  CONSTRAINT fk_notify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web-Push-Abos der Browser (je Gerät eines)
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  endpoint   VARCHAR(500) NOT NULL,
  p256dh     VARCHAR(150) NOT NULL,
  auth       VARCHAR(60)  NOT NULL,
  geraet     VARCHAR(150) NOT NULL DEFAULT '',
  last_ok    DATETIME     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_endpoint (endpoint(255)),
  KEY idx_push_user (user_id),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Gesamtbudget je Haushaltsjahr
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS budget_years (
  jahr          SMALLINT      NOT NULL,
  betrag        DECIMAL(12,2) NOT NULL DEFAULT 0,
  beschreibung  TEXT          NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Buchungen: Ausgaben (Haus, Nebenkosten, Tanken ...) und Einnahmen
-- (Einsatzkostenerstattung, technische Hilfeleistung ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  art           ENUM('ausgabe','einnahme') NOT NULL DEFAULT 'ausgabe',
  jahr          SMALLINT      NOT NULL,
  datum         DATE          NOT NULL,
  bezeichnung   VARCHAR(200)  NOT NULL,
  beschreibung  TEXT          NULL,
  kategorie_id  INT UNSIGNED  NULL,
  fachgruppe_id INT UNSIGNED  NULL,
  budget_id     INT UNSIGNED  NULL,
  wish_id       INT UNSIGNED  NULL,
  -- Bezug zu einer Veranstaltung; der Fremdschluessel kommt in der Wanderung,
  -- weil events erst weiter unten angelegt wird
  event_id      INT UNSIGNED  NULL,
  betrag_brutto DECIMAL(12,2) NOT NULL DEFAULT 0,
  mwst_satz     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  betrag_netto  DECIMAL(12,2) NOT NULL DEFAULT 0,
  lieferant     VARCHAR(150)  NOT NULL DEFAULT '',
  beleg_nr      VARCHAR(100)  NOT NULL DEFAULT '',
  referenz      VARCHAR(100)  NOT NULL DEFAULT '',
  bezahlt_am    DATE          NULL,
  notiz         TEXT          NULL,
  created_by    INT UNSIGNED  NULL,
  updated_by    INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jahr (jahr, art, datum),
  KEY idx_kat (kategorie_id),
  CONSTRAINT fk_exp_kat  FOREIGN KEY (kategorie_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_fg   FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_bud  FOREIGN KEY (budget_id)     REFERENCES budgets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_exp_wish FOREIGN KEY (wish_id)       REFERENCES wishes(id)     ON DELETE SET NULL,
  CONSTRAINT fk_exp_cb   FOREIGN KEY (created_by)    REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_exp_ub   FOREIGN KEY (updated_by)    REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Kontakte (Behoerden, Feuerwehr, Firmen, Foerderer ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  anrede        VARCHAR(30)  NOT NULL DEFAULT '',
  titel         VARCHAR(40)  NOT NULL DEFAULT '',
  vorname       VARCHAR(80)  NOT NULL DEFAULT '',
  nachname      VARCHAR(80)  NOT NULL DEFAULT '',
  organisation  VARCHAR(150) NOT NULL DEFAULT '',
  position      VARCHAR(150) NOT NULL DEFAULT '',
  kategorie_id  INT UNSIGNED NULL,
  email         VARCHAR(150) NOT NULL DEFAULT '',
  telefon       VARCHAR(60)  NOT NULL DEFAULT '',
  mobil         VARCHAR(60)  NOT NULL DEFAULT '',
  strasse       VARCHAR(150) NOT NULL DEFAULT '',
  plz           VARCHAR(15)  NOT NULL DEFAULT '',
  ort           VARCHAR(100) NOT NULL DEFAULT '',
  land          VARCHAR(60)  NOT NULL DEFAULT '',
  anschreiben   VARCHAR(150) NOT NULL DEFAULT '',
  notiz         TEXT         NULL,
  extra         TEXT         NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  updated_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_name (nachname, vorname),
  KEY idx_org (organisation),
  CONSTRAINT fk_kontakt_kat FOREIGN KEY (kategorie_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_kontakt_cb  FOREIGN KEY (created_by)   REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_kontakt_ub  FOREIGN KEY (updated_by)   REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verteiler, etwa "Einladung Jubilaeum 2027"
CREATE TABLE IF NOT EXISTS contact_groups (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  beschreibung  TEXT         NULL,
  anlass_am     DATE         NULL,
  ort           VARCHAR(150) NOT NULL DEFAULT '',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_vt_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_group_members (
  group_id      INT UNSIGNED NOT NULL,
  contact_id    INT UNSIGNED NOT NULL,
  status_id     INT UNSIGNED NULL,
  personen      SMALLINT     NOT NULL DEFAULT 1,
  notiz         VARCHAR(255) NOT NULL DEFAULT '',
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, contact_id),
  KEY idx_contact (contact_id),
  CONSTRAINT fk_vtm_group   FOREIGN KEY (group_id)   REFERENCES contact_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_vtm_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)       ON DELETE CASCADE,
  CONSTRAINT fk_vtm_status  FOREIGN KEY (status_id)  REFERENCES list_items(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Besprechungen und Talking Points
-- ------------------------------------------------------------
-- Wiederkehrende Besprechungen: nur die Regel, die Termine stehen in meetings
CREATE TABLE IF NOT EXISTS meeting_series (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel          VARCHAR(200) NOT NULL,
  typ_id         INT UNSIGNED NULL,
  regel          ENUM('woche','monat_wochentag','monat_tag') NOT NULL DEFAULT 'woche',
  intervall      TINYINT      NOT NULL DEFAULT 1,
  wochentag      TINYINT      NOT NULL DEFAULT 1,
  nte            TINYINT      NOT NULL DEFAULT 1,
  monatstag      TINYINT      NOT NULL DEFAULT 1,
  beginn         TIME         NULL,
  ende           TIME         NULL,
  ort            VARCHAR(150) NOT NULL DEFAULT '',
  leitung        VARCHAR(150) NOT NULL DEFAULT '',
  teilnehmer     TEXT         NULL,
  beschreibung   TEXT         NULL,
  start_datum    DATE         NOT NULL,
  end_datum      DATE         NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_serie_typ FOREIGN KEY (typ_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_serie_cb  FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meetings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  series_id      INT UNSIGNED NULL,
  serien_datum   DATE         NULL,
  titel          VARCHAR(200) NOT NULL,
  typ_id         INT UNSIGNED NULL,
  datum          DATE         NOT NULL,
  beginn         TIME         NULL,
  ende           TIME         NULL,
  ort            VARCHAR(150) NOT NULL DEFAULT '',
  leitung        VARCHAR(150) NOT NULL DEFAULT '',
  protokoll_von  VARCHAR(150) NOT NULL DEFAULT '',
  teilnehmer     TEXT         NULL,
  beschreibung   TEXT         NULL,
  notizen        TEXT         NULL,
  status         ENUM('geplant','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_datum (datum),
  UNIQUE KEY uq_serie_termin (series_id, serien_datum),
  CONSTRAINT fk_meet_serie FOREIGN KEY (series_id) REFERENCES meeting_series(id) ON DELETE SET NULL,
  CONSTRAINT fk_meet_typ FOREIGN KEY (typ_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_meet_cb  FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anwesenheit: wer war eingeladen, wer da, wer entschuldigt
CREATE TABLE IF NOT EXISTS meeting_attendees (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  meeting_id    INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NULL,
  contact_id    INT UNSIGNED NULL,
  name          VARCHAR(150) NOT NULL DEFAULT '',
  status_id     INT UNSIGNED NULL,
  notiz         VARCHAR(255) NOT NULL DEFAULT '',
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ma_user (meeting_id, user_id),
  UNIQUE KEY uq_ma_contact (meeting_id, contact_id),
  KEY idx_ma_meeting (meeting_id),
  CONSTRAINT fk_ma_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ma_user    FOREIGN KEY (user_id)    REFERENCES users(id)      ON DELETE CASCADE,
  CONSTRAINT fk_ma_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ma_status  FOREIGN KEY (status_id)  REFERENCES list_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- meeting_id NULL = Themenspeicher
CREATE TABLE IF NOT EXISTS talking_points (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  meeting_id      INT UNSIGNED NULL,
  vorgaenger_id   INT UNSIGNED NULL,
  titel           VARCHAR(200) NOT NULL,
  beschreibung    TEXT         NULL,
  fachgruppe_id   INT UNSIGNED NULL,
  prioritaet_id   INT UNSIGNED NULL,
  status_id       INT UNSIGNED NULL,
  dauer_min       SMALLINT     NULL,
  verantwortlich  VARCHAR(150) NOT NULL DEFAULT '',
  ergebnis        TEXT         NULL,
  sort_order      INT          NOT NULL DEFAULT 0,
  todo_id         INT UNSIGNED NULL,
  eingebracht_von INT UNSIGNED NULL,
  -- Name, wenn das Thema von außen kam (Divera-Formular) und keinem Benutzer zugeordnet ist
  einbringer_name VARCHAR(150) NOT NULL DEFAULT '',
  divera_form_id  VARCHAR(60)  NOT NULL DEFAULT '',
  divera_entry_id VARCHAR(60)  NOT NULL DEFAULT '',
  divera_status   TINYINT      NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_meeting (meeting_id, sort_order),
  KEY idx_tp_divera (divera_form_id, divera_entry_id),
  KEY idx_vorgaenger (vorgaenger_id),
  CONSTRAINT fk_tp_meeting FOREIGN KEY (meeting_id)      REFERENCES meetings(id)       ON DELETE SET NULL,
  CONSTRAINT fk_tp_vorg    FOREIGN KEY (vorgaenger_id)   REFERENCES talking_points(id) ON DELETE SET NULL,
  CONSTRAINT fk_tp_fg      FOREIGN KEY (fachgruppe_id)   REFERENCES list_items(id)     ON DELETE SET NULL,
  CONSTRAINT fk_tp_prio    FOREIGN KEY (prioritaet_id)   REFERENCES list_items(id)     ON DELETE SET NULL,
  CONSTRAINT fk_tp_status  FOREIGN KEY (status_id)       REFERENCES list_items(id)     ON DELETE SET NULL,
  CONSTRAINT fk_tp_todo    FOREIGN KEY (todo_id)         REFERENCES todos(id)          ON DELETE SET NULL,
  CONSTRAINT fk_tp_user    FOREIGN KEY (eingebracht_von) REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anmerkungen und Diskussion zu einem Talking Point, solange er offen ist
CREATE TABLE IF NOT EXISTS talking_point_comments (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tp_id      INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tpc_tp (tp_id, id),
  CONSTRAINT fk_tpc_tp   FOREIGN KEY (tp_id)   REFERENCES talking_points(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpc_user FOREIGN KEY (user_id) REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bestellberechtigungen: je Rolle oder Funktion, wer Wünsche zur
-- Bestellung freigeben (optional bis zu einem Nettobetrag) und wer sie
-- als bestellt markieren darf. Genau eine der Spalten rolle/funktion_id
-- ist gesetzt.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bestell_rechte (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rolle           VARCHAR(20)  NULL,
  funktion_id     INT UNSIGNED NULL,
  darf_freigeben  TINYINT(1)   NOT NULL DEFAULT 0,
  freigabe_grenze DECIMAL(12,2) NULL,
  darf_bestellen  TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_br_rolle (rolle),
  UNIQUE KEY uq_br_funktion (funktion_id),
  CONSTRAINT fk_br_funktion FOREIGN KEY (funktion_id) REFERENCES list_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Connectoren: Briefkaesten auf oeffentlich erreichbaren Webservern
-- (Standortmeldung per QR-Code, Einladungen zu Veranstaltungen)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS connectors (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(100) NOT NULL,
  url                  VARCHAR(255) NOT NULL DEFAULT '',
  -- kurze Adresse fuer Einladungslinks, z. B. https://i.example.de
  kurz_url             VARCHAR(255) NOT NULL DEFAULT '',
  fuer_fahrzeuge       TINYINT(1)   NOT NULL DEFAULT 1,
  fuer_veranstaltungen TINYINT(1)   NOT NULL DEFAULT 0,
  fuer_bestand         TINYINT(1)   NOT NULL DEFAULT 0,
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  -- unser Schluesselpaar fuer diesen Connector und sein oeffentlicher Schluessel
  pem                  TEXT         NULL,
  pubkey               VARCHAR(255) NOT NULL DEFAULT '',
  server_pub           VARCHAR(255) NOT NULL DEFAULT '',
  version              VARCHAR(20)  NOT NULL DEFAULT '',
  gekoppelt_am         DATETIME     NULL,
  angemeldet_am        DATETIME     NULL,
  letzter_abruf        DATETIME     NULL,
  notiz                TEXT         NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Veranstaltungen: Planung, Budget, Dateien und Einladungen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel              VARCHAR(200) NOT NULL,
  beschreibung       TEXT         NULL,
  ort                VARCHAR(200) NOT NULL DEFAULT '',
  beginn             DATETIME     NOT NULL,
  ende               DATETIME     NULL,
  status             ENUM('geplant','laeuft','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
  typ_id             INT UNSIGNED NULL,
  jahr               SMALLINT     NOT NULL,
  budget_id          INT UNSIGNED NULL,
  fachgruppe_id      INT UNSIGNED NULL,
  kosten_geplant     DECIMAL(12,2) NOT NULL DEFAULT 0,
  -- Einladungen ueber einen Connector
  connector_id       INT UNSIGNED NULL,
  code_laenge        TINYINT UNSIGNED NOT NULL DEFAULT 6,
  begleiter_max      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  kommentare_erlaubt TINYINT(1)   NOT NULL DEFAULT 1,
  vertretung_erlaubt TINYINT(1)   NOT NULL DEFAULT 1,
  rueckmeldung_bis   DATE         NULL,
  hinweis            TEXT         NULL,
  -- Manche Arten (Ausbildung, Uebung) brauchen keine Gaesteliste,
  -- sondern nur eine Zahl
  gaesteliste        TINYINT(1)   NOT NULL DEFAULT 1,
  teilnehmer_geplant SMALLINT UNSIGNED NULL,
  teilnehmer_ist     SMALLINT UNSIGNED NULL,
  einladung_aktiv    TINYINT(1)   NOT NULL DEFAULT 0,
  angemeldet_am      DATETIME     NULL,
  notiz              TEXT         NULL,
  created_by         INT UNSIGNED NULL,
  updated_by         INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ev_beginn (beginn),
  KEY idx_ev_jahr (jahr, status),
  CONSTRAINT fk_ev_budget FOREIGN KEY (budget_id)     REFERENCES budgets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_ev_typ    FOREIGN KEY (typ_id)        REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_ev_fg     FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_ev_con    FOREIGN KEY (connector_id)  REFERENCES connectors(id) ON DELETE SET NULL,
  CONSTRAINT fk_ev_cb     FOREIGN KEY (created_by)    REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_ev_ub     FOREIGN KEY (updated_by)    REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eingeladene und ihre Rueckmeldung. Der Code steht in der Einladung;
-- beim Connector liegt nur seine Pruefsumme.
CREATE TABLE IF NOT EXISTS event_guests (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       INT UNSIGNED NOT NULL,
  contact_id     INT UNSIGNED NULL,
  name           VARCHAR(150) NOT NULL DEFAULT '',
  code           VARCHAR(32)  NOT NULL,
  status         ENUM('offen','zusage','absage','vertretung') NOT NULL DEFAULT 'offen',
  begleiter      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  vertretung     VARCHAR(150) NOT NULL DEFAULT '',
  kommentar      TEXT         NULL,
  quelle         VARCHAR(20)  NOT NULL DEFAULT '',
  geantwortet_am DATETIME     NULL,
  notiz          TEXT         NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  UNIQUE KEY uq_event_kontakt (event_id, contact_id),
  KEY idx_eg_event (event_id, status),
  CONSTRAINT fk_eg_event   FOREIGN KEY (event_id)   REFERENCES events(id)   ON DELETE CASCADE,
  CONSTRAINT fk_eg_kontakt FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dateien zur Veranstaltung: Rechnungen, Angebote, Programm, Bilder
CREATE TABLE IF NOT EXISTS event_files (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id     INT UNSIGNED NOT NULL,
  art          VARCHAR(20)  NOT NULL DEFAULT 'dokument',
  titel        VARCHAR(200) NOT NULL DEFAULT '',
  orig_name    VARCHAR(255) NOT NULL DEFAULT '',
  stored_name  VARCHAR(255) NOT NULL,
  thumb_name   VARCHAR(255) NULL,
  mime         VARCHAR(120) NOT NULL DEFAULT '',
  size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
  betrag       DECIMAL(12,2) NULL,
  uploaded_by  INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ef_event (event_id),
  CONSTRAINT fk_ef_event FOREIGN KEY (event_id)    REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_ef_ub    FOREIGN KEY (uploaded_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Gruppen von Funkgeraeten: ein Koffer, eine Ladeschale, ein Satz
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radio_groups (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  beschreibung    TEXT         NULL,
  lagerort        VARCHAR(150) NOT NULL DEFAULT '',
  ziel_typ        ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id         INT UNSIGNED NULL,
  -- QR-Code fuer die Bestandsmeldung am Lagerort
  qr_token        VARCHAR(80)  NOT NULL DEFAULT '',
  qr_connector_id INT UNSIGNED NULL,
  zuletzt_gesehen DATETIME     NULL,
  zuletzt_anzahl  SMALLINT UNSIGNED NULL,
  zuletzt_melder  VARCHAR(60)  NOT NULL DEFAULT '',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gruppe_qr (qr_token),
  CONSTRAINT fk_gruppe_con FOREIGN KEY (qr_connector_id) REFERENCES connectors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Funkgeraete: Bestand, Zuordnung und die Karte darin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bezeichnung    VARCHAR(150) NOT NULL,
  typ_id         INT UNSIGNED NULL,
  status_id      INT UNSIGNED NULL,
  hersteller     VARCHAR(80)  NOT NULL DEFAULT '',
  modell         VARCHAR(80)  NOT NULL DEFAULT '',
  seriennummer   VARCHAR(60)  NOT NULL DEFAULT '',
  inventarnummer VARCHAR(60)  NOT NULL DEFAULT '',
  funkrufname    VARCHAR(80)  NOT NULL DEFAULT '',
  -- Koffer, Ladeschale oder Satz, zu dem das Geraet gehoert
  group_id       INT UNSIGNED NULL,
  -- eigener QR-Code fuer die Bestandsmeldung
  qr_token        VARCHAR(80) NOT NULL DEFAULT '',
  qr_connector_id INT UNSIGNED NULL,
  zuletzt_gesehen DATETIME    NULL,
  zuletzt_melder  VARCHAR(60) NOT NULL DEFAULT '',
  -- wem das Geraet gehoert: ov, fahrzeug, fachgruppe oder person
  ziel_typ       ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id        INT UNSIGNED NULL,
  standort       VARCHAR(150) NOT NULL DEFAULT '',
  beschafft_am   DATE         NULL,
  pruefung_bis   DATE         NULL,
  notiz          TEXT         NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_by     INT UNSIGNED NULL,
  updated_by     INT UNSIGNED NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_funk_ziel (ziel_typ, ziel_id),
  CONSTRAINT fk_funk_grp FOREIGN KEY (group_id)  REFERENCES radio_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_funk_con FOREIGN KEY (qr_connector_id) REFERENCES connectors(id) ON DELETE SET NULL,
  CONSTRAINT fk_funk_typ FOREIGN KEY (typ_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_funk_sta FOREIGN KEY (status_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_funk_cb  FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_funk_ub  FOREIGN KEY (updated_by) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SIM-Karten: Bestand, Zuordnung zu Fahrzeug, Fachgruppe oder Person
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sims (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Mobilfunkkarte oder TETRA-Sicherheitskarte; danach richten sich die Felder
  karte_art     ENUM('mobilfunk','tetra') NOT NULL DEFAULT 'mobilfunk',
  rufnummer     VARCHAR(40)  NOT NULL DEFAULT '',
  iccid         VARCHAR(30)  NOT NULL DEFAULT '',
  -- nur bei TETRA: Teilnehmerkennung und operativ-taktische Adresse
  issi          VARCHAR(40)  NOT NULL DEFAULT '',
  opta          VARCHAR(60)  NOT NULL DEFAULT '',
  typ_id        INT UNSIGNED NULL,
  status_id     INT UNSIGNED NULL,
  anbieter      VARCHAR(80)  NOT NULL DEFAULT '',
  tarif         VARCHAR(120) NOT NULL DEFAULT '',
  datenvolumen  VARCHAR(40)  NOT NULL DEFAULT '',
  -- Vertrag und PIN gibt es nur, wenn die Karte sie hat
  hat_vertrag   TINYINT(1)   NOT NULL DEFAULT 0,
  kosten_monat  DECIMAL(10,2) NULL,
  vertrag_bis   DATE         NULL,
  hat_pin       TINYINT(1)   NOT NULL DEFAULT 0,
  -- PIN und PUK stehen verdeckt in der Anwendung und nur fuer die Leitung
  pin           VARCHAR(20)  NOT NULL DEFAULT '',
  puk           VARCHAR(30)  NOT NULL DEFAULT '',
  geraet        VARCHAR(150) NOT NULL DEFAULT '',
  -- in welchem Funkgeraet die Karte steckt
  radio_id      INT UNSIGNED NULL,
  -- wem die Karte gehoert: ov, fahrzeug, fachgruppe oder person
  ziel_typ      ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id       INT UNSIGNED NULL,
  ausgegeben_am DATE         NULL,
  notiz         TEXT         NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  updated_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sim_ziel (ziel_typ, ziel_id),
  KEY idx_sim_nummer (rufnummer),
  CONSTRAINT fk_sim_funk FOREIGN KEY (radio_id) REFERENCES radios(id)     ON DELETE SET NULL,
  CONSTRAINT fk_sim_typ FOREIGN KEY (typ_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_sim_sta FOREIGN KEY (status_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_sim_cb  FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_sim_ub  FOREIGN KEY (updated_by) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Fahrzeuge und Fahrzeugakte
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vehicles (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bezeichnung       VARCHAR(150) NOT NULL,
  funkrufname       VARCHAR(80)  NOT NULL DEFAULT '',
  issi              VARCHAR(80)  NOT NULL DEFAULT '',
  kennzeichen       VARCHAR(20)  NOT NULL DEFAULT '',
  kennung           VARCHAR(40)  NOT NULL DEFAULT '',
  typ_id            INT UNSIGNED NULL,
  fachgruppe_id     INT UNSIGNED NULL,
  status_id         INT UNSIGNED NULL,
  hersteller        VARCHAR(80)  NOT NULL DEFAULT '',
  modell            VARCHAR(80)  NOT NULL DEFAULT '',
  baujahr           SMALLINT     NULL,
  fahrgestellnummer VARCHAR(40)  NOT NULL DEFAULT '',
  erstzulassung     DATE         NULL,
  km_stand          INT UNSIGNED NULL,
  betriebsstunden   INT UNSIGNED NULL,
  hu_bis            DATE         NULL,
  sp_bis            DATE         NULL,
  uvv_bis           DATE         NULL,
  standort          VARCHAR(150) NOT NULL DEFAULT '',
  notiz             TEXT         NULL,
  extra             TEXT         NULL,
  opta              VARCHAR(60)  NOT NULL DEFAULT '',
  ric               VARCHAR(30)  NOT NULL DEFAULT '',
  divera_vehicle_id INT UNSIGNED NULL,
  fms_status        TINYINT      NULL,
  fms_note          VARCHAR(255) NOT NULL DEFAULT '',
  fms_at            DATETIME     NULL,
  geo_lat           DECIMAL(9,6) NULL,
  geo_lng           DECIMAL(9,6) NULL,
  -- woher der Standort kommt: divera oder mensch
  geo_quelle        VARCHAR(20)  NOT NULL DEFAULT '',
  -- steht seit wann an derselben Stelle (für die Parkposition im Journal)
  geo_park_seit     DATETIME     NULL,
  geo_park_gemeldet TINYINT(1)   NOT NULL DEFAULT 0,
  -- Zugang für den QR-Code im Fahrzeug
  qr_token          VARCHAR(80)  NOT NULL DEFAULT '',
  -- ueber welchen Connector der QR-Code laeuft
  qr_connector_id   INT UNSIGNED NULL,
  geo_at            DATETIME     NULL,
  divera_besatzung  TEXT         NULL,
  divera_daten      MEDIUMTEXT   NULL,
  divera_sync_at    DATETIME     NULL,
  stein_asset_id    VARCHAR(60)  NULL,
  stein_status      VARCHAR(30)  NOT NULL DEFAULT '',
  stein_daten       MEDIUMTEXT   NULL,
  stein_sync_at     DATETIME     NULL,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  ausgemustert_am   DATE         NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stein_asset (stein_asset_id),
  UNIQUE KEY uq_divera_fz (divera_vehicle_id),
  KEY idx_fz_status (status_id),
  KEY idx_qr_token (qr_token),
  CONSTRAINT fk_fz_typ FOREIGN KEY (typ_id)         REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_fz_fg  FOREIGN KEY (fachgruppe_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_fz_sta FOREIGN KEY (status_id)      REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_fz_cb  FOREIGN KEY (created_by)     REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_fz_con FOREIGN KEY (qr_connector_id) REFERENCES connectors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal der Fahrzeugakte: wird nur angehaengt, nie geaendert oder geloescht.
-- Jede Zeile traegt den Hash der vorigen Zeile, dadurch faellt jede nachtraegliche
-- Aenderung beim Pruefen auf (siehe journal_verify in src/lib/vehicles.php).
CREATE TABLE IF NOT EXISTS vehicle_journal (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL,
  user_id     INT UNSIGNED NULL,
  autor       VARCHAR(150) NOT NULL DEFAULT '',
  quelle      ENUM('mensch','stein','divera','system') NOT NULL DEFAULT 'mensch',
  art         VARCHAR(40)  NOT NULL DEFAULT 'notiz',
  titel       VARCHAR(200) NOT NULL DEFAULT '',
  text        TEXT         NULL,
  feld        VARCHAR(60)  NOT NULL DEFAULT '',
  alt_wert    VARCHAR(255) NOT NULL DEFAULT '',
  neu_wert    VARCHAR(255) NOT NULL DEFAULT '',
  ref_typ     VARCHAR(20)  NOT NULL DEFAULT '',
  ref_id      INT UNSIGNED NULL,
  prev_hash   CHAR(64)     NOT NULL DEFAULT '',
  hash        CHAR(64)     NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_vj (vehicle_id, id),
  CONSTRAINT fk_vj_fz   FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_vj_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instandsetzungsauftraege und Schadensmeldungen
CREATE TABLE IF NOT EXISTS vehicle_orders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id    INT UNSIGNED NOT NULL,
  nummer        VARCHAR(20)  NOT NULL DEFAULT '',
  titel         VARCHAR(200) NOT NULL,
  beschreibung  TEXT         NULL,
  art_id        INT UNSIGNED NULL,
  prioritaet_id INT UNSIGNED NULL,
  status_id     INT UNSIGNED NULL,
  werkstatt     VARCHAR(150) NOT NULL DEFAULT '',
  auftragsnummer VARCHAR(60) NOT NULL DEFAULT '',
  -- Nummer aus der THW-Verwaltung; sie vergibt die Nummern, wir übernehmen sie
  thw_nummer    VARCHAR(60) NOT NULL DEFAULT '',
  gemeldet_von  VARCHAR(150) NOT NULL DEFAULT '',
  gemeldet_am   DATE         NULL,
  faellig_am    DATE         NULL,
  erledigt_am   DATE         NULL,
  km_stand      INT UNSIGNED NULL,
  kosten_geschaetzt DECIMAL(12,2) NULL,
  kosten_netto  DECIMAL(12,2) NULL,
  ausfall       TINYINT(1)   NOT NULL DEFAULT 0,
  todo_id       INT UNSIGNED NULL,
  wish_id       INT UNSIGNED NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vo_fz (vehicle_id),
  KEY idx_vo_status (status_id),
  CONSTRAINT fk_vo_fz   FOREIGN KEY (vehicle_id)    REFERENCES vehicles(id)   ON DELETE CASCADE,
  CONSTRAINT fk_vo_art  FOREIGN KEY (art_id)        REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_vo_prio FOREIGN KEY (prioritaet_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_vo_sta  FOREIGN KEY (status_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_vo_todo FOREIGN KEY (todo_id)       REFERENCES todos(id)      ON DELETE SET NULL,
  CONSTRAINT fk_vo_wish FOREIGN KEY (wish_id)       REFERENCES wishes(id)     ON DELETE SET NULL,
  CONSTRAINT fk_vo_cb   FOREIGN KEY (created_by)    REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bilder und Dokumente zu Fahrzeugen und Aufträgen
CREATE TABLE IF NOT EXISTS vehicle_files (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vehicle_id   INT UNSIGNED NOT NULL,
  order_id     INT UNSIGNED NULL,
  art          ENUM('bild','dokument') NOT NULL DEFAULT 'dokument',
  titel        VARCHAR(200) NOT NULL DEFAULT '',
  orig_name    VARCHAR(255) NOT NULL DEFAULT '',
  stored_name  VARCHAR(120) NOT NULL,
  thumb_name   VARCHAR(120) NULL,
  mime         VARCHAR(100) NOT NULL DEFAULT '',
  size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
  is_cover     TINYINT(1)   NOT NULL DEFAULT 0,
  uploaded_by  INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vf_fz (vehicle_id, art),
  KEY idx_vf_auftrag (order_id),
  CONSTRAINT fk_vf_fz      FOREIGN KEY (vehicle_id)  REFERENCES vehicles(id)       ON DELETE CASCADE,
  CONSTRAINT fk_vf_auftrag FOREIGN KEY (order_id)    REFERENCES vehicle_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_vf_user    FOREIGN KEY (uploaded_by) REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merkliste: wer welches Fahrzeug oben angeheftet hat
CREATE TABLE IF NOT EXISTS vehicle_favorites (
  user_id    INT UNSIGNED NOT NULL,
  vehicle_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, vehicle_id),
  KEY idx_vfav_fz (vehicle_id),
  CONSTRAINT fk_vfav_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_vfav_fz   FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protokoll der Stein.app-Abrufe (das Rate Limit macht Nachvollziehbarkeit wichtig)
CREATE TABLE IF NOT EXISTS stein_log (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status      VARCHAR(20)  NOT NULL DEFAULT 'ok',
  assets      INT UNSIGNED NOT NULL DEFAULT 0,
  zuordnungen INT UNSIGNED NOT NULL DEFAULT 0,
  aenderungen INT UNSIGNED NOT NULL DEFAULT 0,
  dauer_ms    INT UNSIGNED NOT NULL DEFAULT 0,
  message     VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stein_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Audit
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  entity     VARCHAR(40)  NOT NULL DEFAULT '',
  entity_id  INT UNSIGNED NULL,
  detail     VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARCHAR(45)  NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Fehlgeschlagene Anmeldeversuche (Brute-Force-Bremse)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(60) NOT NULL,
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user (username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
