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
  mwst_satz       DECIMAL(5,2)  NOT NULL DEFAULT 19.00,
  fachgruppe_id   INT UNSIGNED NULL,
  kategorie_id    INT UNSIGNED NULL,
  dringlichkeit_id INT UNSIGNED NULL,
  status_id       INT UNSIGNED NULL,
  budget_id       INT UNSIGNED NULL,
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
  created_by      INT UNSIGNED NULL,
  updated_by      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status_id),
  KEY idx_fg (fachgruppe_id),
  KEY idx_divera (divera_form_id, divera_entry_id),
  CONSTRAINT fk_w_fg   FOREIGN KEY (fachgruppe_id)    REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_kat  FOREIGN KEY (kategorie_id)     REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_dri  FOREIGN KEY (dringlichkeit_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_sta  FOREIGN KEY (status_id)        REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_ein  FOREIGN KEY (einheit_id)       REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_w_bud  FOREIGN KEY (budget_id)        REFERENCES budgets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_w_cb   FOREIGN KEY (created_by)       REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_w_ub   FOREIGN KEY (updated_by)       REFERENCES users(id)      ON DELETE SET NULL
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
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_target (target_type, target_id),
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
  status     VARCHAR(20) NOT NULL DEFAULT 'ok',
  message    VARCHAR(500) NOT NULL DEFAULT '',
  payload    MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
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
-- Ausgaben (Haus, Nebenkosten, Getraenke, Tanken ...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  jahr          SMALLINT      NOT NULL,
  datum         DATE          NOT NULL,
  bezeichnung   VARCHAR(200)  NOT NULL,
  beschreibung  TEXT          NULL,
  kategorie_id  INT UNSIGNED  NULL,
  fachgruppe_id INT UNSIGNED  NULL,
  budget_id     INT UNSIGNED  NULL,
  wish_id       INT UNSIGNED  NULL,
  betrag_brutto DECIMAL(12,2) NOT NULL DEFAULT 0,
  mwst_satz     DECIMAL(5,2)  NOT NULL DEFAULT 19.00,
  betrag_netto  DECIMAL(12,2) NOT NULL DEFAULT 0,
  lieferant     VARCHAR(150)  NOT NULL DEFAULT '',
  beleg_nr      VARCHAR(100)  NOT NULL DEFAULT '',
  bezahlt_am    DATE          NULL,
  notiz         TEXT          NULL,
  created_by    INT UNSIGNED  NULL,
  updated_by    INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jahr (jahr, datum),
  KEY idx_kat (kategorie_id),
  CONSTRAINT fk_exp_kat  FOREIGN KEY (kategorie_id)  REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_fg   FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_exp_bud  FOREIGN KEY (budget_id)     REFERENCES budgets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_exp_wish FOREIGN KEY (wish_id)       REFERENCES wishes(id)     ON DELETE SET NULL,
  CONSTRAINT fk_exp_cb   FOREIGN KEY (created_by)    REFERENCES users(id)      ON DELETE SET NULL,
  CONSTRAINT fk_exp_ub   FOREIGN KEY (updated_by)    REFERENCES users(id)      ON DELETE SET NULL
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
