# Änderungsverlauf

## 1.2.0

- Umbau zu einem regulären Add-on-Repository: das Add-on liegt jetzt im
  Unterordner `ov-budget/`, sodass sich die GitHub-URL direkt unter
  *Repositories* im Add-on Store hinzufügen lässt.
- Dokumentation als `DOCS.md` im Add-on selbst.

## 1.1.0

- Das Add-on bringt seine eigene MariaDB mit und installiert sich vollständig
  allein. Das MariaDB-Add-on ist nicht mehr nötig, kann aber weiterhin über
  `db_host` verwendet werden.
- Datenbank, Uploads und Sitzungen liegen unter `/data`; `backup: cold` sorgt
  für konsistente Sicherungen.
- Geordnetes Herunterfahren der Datenbank beim Stoppen des Add-ons.

## 1.0.0

- Erste Fassung: Wunschliste, Aufgaben, Budgettöpfe, Divera-24/7-Anbindung,
  Benutzerverwaltung und frei konfigurierbare Auswahllisten.
- Ingress-Unterstützung, optionaler direkter Zugriff über Port 8099.
