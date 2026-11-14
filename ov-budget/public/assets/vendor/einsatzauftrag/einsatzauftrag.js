/*!
 * einsatzauftrag – Einsatzauftrag (Fb Fü 3) als PDF mit jsPDF
 * Copyright (c) 2025-2026 Johannes Rudolph
 * Lizenz: EUPL-1.2 (European Union Public Licence, Version 1.2 oder später) – siehe LICENSE in diesem Ordner
 * Quelle: https://github.com/wattnpapa/einsatzauftrag, Stand f642655c20d75416f52412c1f3994618303ec0bf (2026-11-10)
 *
 * Unverändert aus den TypeScript-Quellen (src/) gebaut, nur in eine Browser-Datei übersetzt:
 * esbuild --bundle --format=iife --global-name=Einsatzauftrag. Siehe scripts/einsatzauftrag-aktualisieren.sh.
 */
"use strict";
var Einsatzauftrag = (() => {
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/index.ts
  var index_exports = {};
  __export(index_exports, {
    ABSCHNITTE: () => ABSCHNITTE,
    BLATT_BREITE: () => BLATT_BREITE,
    BLATT_HOEHE: () => BLATT_HOEHE,
    SCHRIFTGROESSEN: () => SCHRIFTGROESSEN,
    UNTERPUNKTE: () => UNTERPUNKTE,
    ohneAuszeichnung: () => ohneAuszeichnung,
    setzeEinsatzauftrag: () => setzeEinsatzauftrag,
    zeichneEinsatzauftrag: () => zeichneEinsatzauftrag,
    zeichneSeite: () => zeichneSeite
  });

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/daten.ts
  var ABSCHNITTE = [
    {
      nummer: 1,
      titel: "Lage",
      erlaeuterung: "(1a: Gefahren- / Schadenlage, 1b: eigene Lage, 1c: Unterstellung und Abgabe von Kräften)",
      unterpunkte: [
        { kennung: "1a", bedeutung: "Gefahren- / Schadenlage" },
        { kennung: "1b", bedeutung: "eigene Lage" },
        { kennung: "1c", bedeutung: "Unterstellung und Abgabe von Kräften" }
      ]
    },
    {
      nummer: 2,
      titel: "Auftrag",
      erlaeuterung: "(erhaltener Auftrag)",
      unterpunkte: []
    },
    {
      nummer: 3,
      titel: "Durchführung",
      erlaeuterung: "(3a: Schwerpunkt, eigene Absicht, 3b: Einzelaufträge, 3c: Zusammenarbeit mit anderen Kräften und Koordinierung, Zeitangaben, Schutzmaßnahmen)",
      unterpunkte: [
        { kennung: "3a", bedeutung: "Schwerpunkt, eigene Absicht" },
        { kennung: "3b", bedeutung: "Einzelaufträge" },
        { kennung: "3c", bedeutung: "Zusammenarbeit mit anderen Kräften und Koordinierung, Zeitangaben, Schutzmaßnahmen" }
      ]
    },
    {
      nummer: 4,
      titel: "Logistik",
      erlaeuterung: "(4a: Verpflegung, 4b: Verbrauchsgüter, 4c: Betriebsstoff, 4d: Instandsetzung, 4e: Medizinische Versorgung, 4f: Unterbringung, 4g: Haushalt, Handkasse, Abrechnung)",
      unterpunkte: [
        { kennung: "4a", bedeutung: "Verpflegung" },
        { kennung: "4b", bedeutung: "Verbrauchsgüter" },
        { kennung: "4c", bedeutung: "Betriebsstoff" },
        { kennung: "4d", bedeutung: "Instandsetzung" },
        { kennung: "4e", bedeutung: "Medizinische Versorgung" },
        { kennung: "4f", bedeutung: "Unterbringung" },
        { kennung: "4g", bedeutung: "Haushalt, Handkasse, Abrechnung" }
      ]
    },
    {
      nummer: 5,
      titel: "Führung und Verbindung",
      erlaeuterung: "(Telekommunikationsverbindungen und Meldewesen, Führungsstellen, Einsatzleitungen, Platz des Führers)",
      unterpunkte: []
    }
  ];
  var UNTERPUNKTE = ABSCHNITTE.flatMap((abschnitt) => abschnitt.unterpunkte.map((punkt) => punkt.kennung));

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/text.ts
  function trenneLangeWoerter(pdf, absatz, maxWidth) {
    return absatz.replace(/\S+/g, (wort) => {
      if (pdf.getTextWidth(wort) <= maxWidth) {
        return wort;
      }
      const stuecke = [];
      let rest = wort;
      const schmal = Math.min(...[".", "i", "l", "'", " "].map((zeichen) => pdf.getTextWidth(zeichen)).filter((breite) => breite > 0));
      const kapazitaet = Number.isFinite(schmal) && schmal > 0 ? Math.ceil(maxWidth / schmal) + 2 : Number.MAX_SAFE_INTEGER;
      while (rest.length > kapazitaet || pdf.getTextWidth(rest) > maxWidth) {
        let passen = 1;
        for (let tief2 = 1, hoch = Math.min(rest.length - 1, kapazitaet); tief2 <= hoch; ) {
          const mitte = tief2 + hoch >> 1;
          if (pdf.getTextWidth(rest.slice(0, mitte)) <= maxWidth) {
            passen = mitte;
            tief2 = mitte + 1;
          } else {
            hoch = mitte - 1;
          }
        }
        let bruch = 0;
        for (let stelle = 1; stelle < Math.min(rest.length - 1, passen); stelle++) {
          if ("/-.@_".includes(rest.charAt(stelle)) && pdf.getTextWidth(rest.slice(0, stelle + 1)) <= maxWidth) {
            bruch = stelle + 1;
          }
        }
        if (bruch > 0 && pdf.getTextWidth(rest.slice(0, bruch)) >= maxWidth / 2) {
          stuecke.push(rest.slice(0, bruch));
          rest = rest.slice(bruch);
          continue;
        }
        let tief = 1;
        for (let t = 1, hoch = Math.min(rest.length - 1, kapazitaet); t <= hoch; ) {
          const mitte = t + hoch >> 1;
          if (pdf.getTextWidth(`${rest.slice(0, mitte)}-`) <= maxWidth) {
            tief = mitte;
            t = mitte + 1;
          } else {
            hoch = mitte - 1;
          }
        }
        stuecke.push(`${rest.slice(0, tief)}-`);
        rest = rest.slice(tief);
      }
      return [...stuecke, rest].join(" ");
    });
  }
  function kuerzeAufBreite(pdf, text, maxWidth) {
    if (pdf.getTextWidth(text) <= maxWidth) {
      return text;
    }
    let gekuerzt = text.trimEnd();
    while (gekuerzt && pdf.getTextWidth(`${gekuerzt}…`) > maxWidth) {
      gekuerzt = gekuerzt.slice(0, -1).trimEnd();
    }
    return `${gekuerzt}…`;
  }
  var ERSATZ = {
    "→": "->",
    "⇒": "=>",
    "⟶": "->",
    "➔": "->",
    "➜": "->",
    "➞": "->",
    "▶": ">",
    "►": ">",
    "←": "<-",
    "⇐": "<=",
    "⟵": "<-",
    "↔": "<->",
    "⇔": "<=>",
    "↑": "^",
    "↓": "v",
    "≥": ">=",
    "≤": "<=",
    "≠": "!=",
    "≈": "~",
    "−": "-",
    "‑": "-",
    "‐": "-",
    "‒": "-",
    "―": "—",
    "✓": "x",
    "✔": "x",
    "☑": "[x]",
    "☐": "[ ]",
    "✗": "x",
    "✘": "x",
    "❌": "x",
    "◦": "-",
    "▪": "•",
    "■": "•",
    "●": "•",
    "◆": "•",
    "‣": "•",
    "⁃": "-",
    "‚": "‚",
    "‛": "'",
    "′": "'",
    "″": '"',
    "‹": "‹",
    "›": "›",
    "­": "",
    "​": "",
    "‌": "",
    "‍": "",
    "⁠": "",
    "\uFEFF": "",
    " ": " ",
    " ": " ",
    " ": " ",
    " ": " ",
    " ": " ",
    " ": " "
  };
  var WINANSI_ZUSATZ = new Set("€‚ƒ„…†‡ˆ‰Š‹ŒŽ‘’“”•–—˜™š›œžŸ");
  function fuerPdf(text) {
    var _a;
    let ergebnis = "";
    for (const zeichen of text.replace(/\r\n?/g, "\n").normalize("NFC")) {
      const code = (_a = zeichen.codePointAt(0)) != null ? _a : 0;
      if (zeichen === "\n" || code >= 32 && code <= 126 || code >= 160 && code <= 255 || WINANSI_ZUSATZ.has(zeichen)) {
        ergebnis += zeichen;
      } else if (zeichen in ERSATZ) {
        ergebnis += ERSATZ[zeichen];
      } else if (zeichen === "	") {
        ergebnis += " ";
      } else if (code < 32 || code >= 127 && code < 160) {
      } else {
        const ohneAkzent = zeichen.normalize("NFD").replace(/[̀-ͯ]/g, "");
        ergebnis += ohneAkzent && [...ohneAkzent].every((teil) => {
          var _a2;
          return ((_a2 = teil.codePointAt(0)) != null ? _a2 : 0) <= 255;
        }) ? ohneAkzent : "?";
      }
    }
    return ergebnis;
  }

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/format.ts
  var GRUNDSTIL = { fett: false, kursiv: false, durch: false };
  var MASKIERBAR = "\\`*_~|[]#+-.!{}()<>";
  var PRIVAT = 57344;
  function maskieren(text) {
    return text.replace(/\\(.)/g, (ganz, zeichen) => MASKIERBAR.includes(zeichen) ? String.fromCharCode(PRIVAT + zeichen.charCodeAt(0)) : ganz);
  }
  function demaskieren(text) {
    return text.replace(/[\ue000-\ue0ff]/g, (zeichen) => String.fromCharCode(zeichen.charCodeAt(0) - PRIVAT));
  }
  var INNEN = "(?=\\S)(.+?)(?<=\\S)";
  var WORT = "[\\p{L}\\p{N}]";
  var AUSZEICHNUNG = new RegExp([
    `\\*\\*\\*${INNEN}\\*\\*\\*(?!\\*)`,
    `\\*\\*${INNEN}\\*\\*(?!\\*)`,
    `(?<!${WORT})__${INNEN}__(?!${WORT})`,
    `~~${INNEN}~~`,
    // Kursiv beginnt nicht mit einem weiteren Stern, sonst wäre „** a **“ kursiv.
    `\\*(?!\\*)${INNEN}\\*(?!\\*)`,
    `(?<!${WORT})_${INNEN}_(?!${WORT})`
  ].join("|"), "u");
  function hatAuszeichnung(text) {
    const maskiert = maskieren(text);
    return maskiert !== text || AUSZEICHNUNG.test(maskiert);
  }
  function laeufe(text, stil = GRUNDSTIL) {
    return verschmelzen(zerlege(maskieren(text), stil).map((lauf) => ({ ...lauf, text: demaskieren(lauf.text) })).filter((lauf) => lauf.text !== ""));
  }
  function zerlege(text, stil) {
    var _a, _b;
    const ergebnis = [];
    let rest = text;
    while (rest) {
      const treffer = AUSZEICHNUNG.exec(rest);
      if (!treffer) {
        ergebnis.push({ ...stil, text: rest });
        break;
      }
      if (treffer.index > 0) {
        ergebnis.push({ ...stil, text: rest.slice(0, treffer.index) });
      }
      const [, beides, fett, fett2, durch, kursiv, kursiv2] = treffer;
      if (beides !== void 0) {
        ergebnis.push(...zerlege(beides, { ...stil, fett: true, kursiv: true }));
      } else if (fett !== void 0 || fett2 !== void 0) {
        ergebnis.push(...zerlege((_a = fett != null ? fett : fett2) != null ? _a : "", { ...stil, fett: true }));
      } else if (durch !== void 0) {
        ergebnis.push(...zerlege(durch, { ...stil, durch: true }));
      } else {
        ergebnis.push(...zerlege((_b = kursiv != null ? kursiv : kursiv2) != null ? _b : "", { ...stil, kursiv: true }));
      }
      rest = rest.slice(treffer.index + treffer[0].length);
    }
    return ergebnis;
  }
  function ohneAuszeichnung(text) {
    return text.split("\n").map((zeile) => laeufe(zeile).map((lauf) => lauf.text).join("")).join("\n");
  }
  function gleicherStil(a, b) {
    return a.fett === b.fett && a.kursiv === b.kursiv && a.durch === b.durch;
  }
  function verschmelzen(liste) {
    const ergebnis = [];
    for (const lauf of liste) {
      const letzter = ergebnis[ergebnis.length - 1];
      if (letzter && gleicherStil(letzter, lauf)) {
        ergebnis[ergebnis.length - 1] = { ...letzter, text: letzter.text + lauf.text };
      } else {
        ergebnis.push(lauf);
      }
    }
    return ergebnis;
  }
  function schnitt(stil) {
    return stil.fett ? stil.kursiv ? "bolditalic" : "bold" : stil.kursiv ? "italic" : "normal";
  }
  function istTabellenzeile(zeile) {
    const t = zeile.trim();
    return t.startsWith("|") && t.length > 1 && t.slice(1).includes("|");
  }
  function zellen(zeile) {
    let t = zeile.trim().slice(1);
    if (t.endsWith("|") && !t.endsWith("\\|")) {
      t = t.slice(0, -1);
    }
    return t.split(/(?<!\\)\|/).map((zelle) => zelle.replace(/\t/g, " ").trim());
  }
  function istTrennzeile(zeile) {
    return zeile.length > 0 && zeile.every((zelle) => /^:?-{2,}:?$/.test(zelle));
  }
  function bloecke(text, tabellen) {
    const normal = String(text).replace(/\\n/g, "\n").replace(/\r\n?/g, "\n").replace(/\s+$/, "");
    if (normal.trim() === "") {
      return [];
    }
    const ergebnis = [];
    let tabelle = [];
    let kopfzeilen = 0;
    const abschliessen = () => {
      if (tabelle.length > 0) {
        ergebnis.push({ art: "tabelle", zeilen: tabelle, kopfzeilen });
      }
      tabelle = [];
      kopfzeilen = 0;
    };
    for (const zeile of normal.split("\n")) {
      if (tabellen && istTabellenzeile(zeile)) {
        const teile = zellen(zeile);
        if (istTrennzeile(teile)) {
          if (kopfzeilen === 0) {
            kopfzeilen = tabelle.length;
          }
        } else {
          tabelle.push(teile);
        }
        continue;
      }
      abschliessen();
      ergebnis.push({ art: "absatz", text: zeile });
    }
    abschliessen();
    return ergebnis;
  }
  function setzeSchrift(pdf, stil, groesse) {
    pdf.setFont("helvetica", schnitt(stil));
    pdf.setFontSize(groesse);
  }
  function laufbreite(pdf, lauf, groesse) {
    setzeSchrift(pdf, lauf, groesse);
    return pdf.getTextWidth(lauf.text);
  }
  function umbrechenLaeufe(pdf, eingabe, maxWidth, groesse) {
    const zeilen = [];
    let zeile = [];
    let breite = 0;
    for (const wort of woerter(pdf, eingabe, maxWidth, groesse)) {
      const wortbreite = wort.teile.reduce((summe, teil) => summe + laufbreite(pdf, teil, groesse), 0);
      if (zeile.length === 0) {
        zeile = [...wort.teile];
        breite = wortbreite;
        continue;
      }
      const lueckenbreite = wort.luecke ? laufbreite(pdf, wort.luecke, groesse) : 0;
      if (breite + lueckenbreite + wortbreite <= maxWidth + 1e-9) {
        zeile.push(...wort.luecke ? [wort.luecke] : [], ...wort.teile);
        breite += lueckenbreite + wortbreite;
      } else {
        zeilen.push(verschmelzen(zeile));
        zeile = [...wort.teile];
        breite = wortbreite;
      }
    }
    zeilen.push(verschmelzen(zeile));
    return zeilen;
  }
  function woerter(pdf, eingabe, maxWidth, groesse) {
    const stuecke = [];
    for (const lauf of eingabe) {
      setzeSchrift(pdf, lauf, groesse);
      const geteilt = trenneLangeWoerter(pdf, lauf.text, maxWidth);
      for (const teil of geteilt.split(/(\s+)/)) {
        if (teil) {
          stuecke.push({ lauf: { ...lauf, text: /^\s+$/.test(teil) ? " " : teil }, leer: /^\s+$/.test(teil) });
        }
      }
    }
    const woerter2 = [];
    let luecke;
    let offen = false;
    for (const { lauf, leer } of stuecke) {
      if (leer) {
        luecke = lauf;
        offen = false;
      } else if (offen) {
        woerter2[woerter2.length - 1].teile.push(lauf);
      } else {
        woerter2.push({ luecke, teile: [lauf] });
        luecke = void 0;
        offen = true;
      }
    }
    return woerter2;
  }
  function umbrechenMitTabs(pdf, absaetze, maxWidth, groesse, raster) {
    const zellen2 = absaetze.map(tabZellen);
    const spalten = tabSpalten(pdf, zellen2, maxWidth, groesse, raster);
    return zellen2.flatMap((absatz) => tabAbsatz(pdf, absatz, maxWidth, groesse, spalten, raster));
  }
  function tabZellen(absatz) {
    const zellen2 = [[]];
    for (const lauf of absatz) {
      lauf.text.split("	").forEach((teil, index) => {
        if (index > 0) {
          zellen2.push([]);
        }
        if (teil) {
          zellen2[zellen2.length - 1].push({ ...lauf, text: teil });
        }
      });
    }
    return zellen2;
  }
  function zellbreite(pdf, zelle, groesse) {
    return zelle.reduce((summe, lauf) => summe + laufbreite(pdf, lauf, groesse), 0);
  }
  function tabSpalten(pdf, zellen2, maxWidth, groesse, raster) {
    var _a;
    const luecke = laufbreite(pdf, { ...GRUNDSTIL, text: " " }, groesse);
    const spalten = [0];
    const anzahl = Math.max(...zellen2.map((absatz) => absatz.length));
    for (let spalte = 1; spalte < anzahl; spalte++) {
      const vorher = spalten[spalte - 1];
      if (vorher === void 0) {
        spalten.push(void 0);
        continue;
      }
      let breiteste = 0;
      for (const absatz of zellen2) {
        const breite = spalte < absatz.length ? vorher + zellbreite(pdf, (_a = absatz[spalte - 1]) != null ? _a : [], groesse) : 0;
        if (breite <= maxWidth) {
          breiteste = Math.max(breiteste, breite);
        }
      }
      const anfang = (Math.floor((breiteste + luecke) / raster) + 1) * raster;
      spalten.push(anfang < maxWidth ? anfang : void 0);
    }
    return spalten;
  }
  function tabAbsatz(pdf, zellen2, maxWidth, groesse, spalten, raster) {
    const luecke = laufbreite(pdf, { ...GRUNDSTIL, text: " " }, groesse);
    const zeilen = [];
    let zeile = [];
    let stueck;
    let einzug = 0;
    let cursor = 0;
    const stueckAbschliessen = () => {
      if (stueck) {
        zeile.push({ x: stueck.x, laeufe: verschmelzen(stueck.laeufe) });
        cursor = stueck.x + stueck.breite;
        stueck = void 0;
      }
    };
    const neueZeile = () => {
      stueckAbschliessen();
      zeilen.push(zeile);
      zeile = [];
      cursor = einzug;
    };
    zellen2.forEach((zelle, index) => {
      if (index > 0) {
        stueckAbschliessen();
        const mindestens = cursor + (zeile.length > 0 ? luecke : 0);
        const spalte = spalten[index];
        const stopp = spalte !== void 0 && spalte >= mindestens ? spalte : (Math.floor(mindestens / raster) + 1) * raster;
        if (stopp >= maxWidth) {
          neueZeile();
        } else {
          cursor = stopp;
          if (cursor <= maxWidth * 0.6) {
            einzug = cursor;
          }
        }
      }
      for (const wort of woerter(pdf, zelle, maxWidth - einzug, groesse)) {
        const wortbreite = zellbreite(pdf, wort.teile, groesse);
        if (stueck) {
          const lueckenbreite = wort.luecke ? laufbreite(pdf, wort.luecke, groesse) : 0;
          if (stueck.x + stueck.breite + lueckenbreite + wortbreite <= maxWidth + 1e-9) {
            stueck.laeufe.push(...wort.luecke ? [wort.luecke] : [], ...wort.teile);
            stueck.breite += lueckenbreite + wortbreite;
            continue;
          }
          neueZeile();
        } else if (zeile.length > 0 && cursor + wortbreite > maxWidth + 1e-9) {
          neueZeile();
        }
        stueck = { x: cursor, laeufe: [...wort.teile], breite: wortbreite };
      }
    });
    neueZeile();
    return zeilen;
  }

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/satz.ts
  var BLATT_BREITE = 210;
  var BLATT_HOEHE = 297;
  var LINKS = 25;
  var RECHTS = BLATT_BREITE - 20;
  var BREITE = RECHTS - LINKS;
  var QUER = BREITE / 190;
  var OBEN = 9;
  var UNTEN = 285;
  var FUSS_Y = 291;
  var KOPF_HOEHE = 9.7;
  var KOPF_KENNUNG = 25.5 * QUER;
  var KOPF_TITEL = 121.7 * QUER;
  var DOPPEL = 0.8;
  var STRICH = 0.2;
  var STRICH_FEIN = 0.15;
  var ADRESSE_BESCHRIFTUNG = 30;
  var ADRESSE_LFDNR = 30;
  var ADRESSE_MINDESTHOEHEN = [9.2, 13.5, 9];
  var KENNUNG_SPALTE = 7.4;
  var ZEILE_MIN = 6.4;
  var AUFTRAG_MIN = 13;
  var ABSCHNITT_KOPF_MIN = 10;
  var ERLAEUTERUNG_X = 41.7 * QUER;
  var ABSCHLUSS_OBEN_MIN = 13.5;
  var ABSCHLUSS_UNTEN_MIN = 9.7;
  var ABGESANDT_SPALTEN = [28.4 * QUER, 87.6 * QUER, 136.1 * QUER];
  var ABSCHLUSS_SPALTEN = [9 * QUER, 66 * QUER, 139 * QUER];
  var GELB = "#ffffa6";
  var BLAU = "#0000f5";
  var SCHWARZ = "#000000";
  var GRAU = "#555555";
  var WASSERZEICHEN = { text: "ÜBUNG", groesse: 120, winkel: 45, farbe: "#f4b8b8" };
  var PT = 25.4 / 72;
  var SCHRIFT_BESCHRIFTUNG = 10;
  var SCHRIFT_TITEL = 11;
  var SCHRIFT_ERLAEUTERUNG = 8;
  var SCHRIFT_KENNUNG = 7;
  var BLOCKABSTAENDE = [5.5, 2.5];
  var SCHRIFTGROESSEN = [10, 9.5, 9, 8.5, 8];
  function zeilenhoehe(groesse) {
    return groesse * PT * 1.18;
  }
  var TABSTOPP = 6.25;
  function oberlaenge(groesse) {
    return groesse * PT * 0.86;
  }
  function setzeEinsatzauftrag(pdf, daten, optionen = {}) {
    const vorher = { schrift: pdf.getFont(), groesse: pdf.getFontSize() };
    daten = druckbareDaten(daten);
    optionen = {
      ...optionen,
      ...optionen.kennung !== void 0 ? { kennung: fuerPdf(optionen.kennung) } : {},
      ...optionen.titel !== void 0 ? { titel: fuerPdf(optionen.titel) } : {},
      ...optionen.organisation !== void 0 ? { organisation: fuerPdf(optionen.organisation) } : {},
      ...optionen.fusszeile ? { fusszeile: fuerPdf(optionen.fusszeile) } : {}
    };
    try {
      const groessen = optionen.schriftgroesse ? [optionen.schriftgroesse] : SCHRIFTGROESSEN;
      const versuche = [];
      for (const groesse of groessen) {
        for (const abstand of BLOCKABSTAENDE) {
          const seiten = new Setzer(pdf, daten, optionen, groesse, abstand).setze().map((elemente) => ({ elemente }));
          versuche.push({ seiten, schriftgroesse: groesse });
          if (seiten.length === 1) {
            return versuche[versuche.length - 1];
          }
        }
      }
      const wenigste = Math.min(...versuche.map((versuch) => versuch.seiten.length));
      return versuche.find((versuch) => versuch.seiten.length === wenigste);
    } finally {
      pdf.setFont(vorher.schrift.fontName, vorher.schrift.fontStyle);
      pdf.setFontSize(vorher.groesse);
    }
  }
  function druckbareDaten(daten) {
    const ergebnis = {};
    for (const [schluessel, wert] of Object.entries(daten)) {
      if (typeof wert === "string") {
        ergebnis[schluessel] = druckbar(wert);
      } else if (typeof wert === "boolean") {
        ergebnis[schluessel] = wert;
      } else if (Array.isArray(wert)) {
        ergebnis[schluessel] = wert.map((text) => typeof text === "string" ? druckbar(text) : text);
      } else if (wert && typeof wert === "object") {
        ergebnis[schluessel] = Object.fromEntries(Object.entries(wert).map(([punkt, text]) => [punkt, typeof text === "string" ? druckbar(text) : text]));
      }
    }
    return ergebnis;
  }
  function druckbar(text) {
    return text.split("	").map(fuerPdf).join("	");
  }
  var TABELLE_RAND = 1.2;
  var TABELLE_POLSTER = 0.7;
  var TABELLE_ABSTAND = 1;
  var TABELLE_KOPF = "#e6e6e6";
  var TABELLE_SPALTE_MIN = 6;
  var Setzer = class {
    constructor(pdf, daten, optionen, schrift, abstand) {
      this.pdf = pdf;
      this.daten = daten;
      this.optionen = optionen;
      this.schrift = schrift;
      this.abstand = abstand;
      this.seiten = [];
      this.elemente = [];
      this.y = OBEN;
      this.zeile = zeilenhoehe(schrift);
      this.polster = (ZEILE_MIN - zeilenhoehe(SCHRIFTGROESSEN[0])) / 2;
    }
    setze() {
      this.neueSeite();
      this.adresse();
      for (const abschnitt of ABSCHNITTE) {
        this.abschnitt(abschnitt);
      }
      this.abschluss();
      this.fusszeilen();
      return this.seiten;
    }
    // ---- Grundbausteine ----
    neueSeite() {
      this.elemente = [];
      this.seiten.push(this.elemente);
      this.y = OBEN;
      if (this.daten.uebung) {
        this.wasserzeichen();
      }
      this.kopf();
      this.y += KOPF_HOEHE + this.abstand;
    }
    text(text, x, y, groesse, {
      fett = false,
      kursiv = false,
      farbe = SCHWARZ,
      ausrichtung = "links"
    } = {}) {
      if (!text) {
        return;
      }
      this.pdf.setFont("helvetica", schnitt({ fett, kursiv, durch: false }));
      this.pdf.setFontSize(groesse);
      const breite = this.pdf.getTextWidth(text);
      this.elemente.push(kursiv ? { art: "text", text, x, y, groesse, fett, kursiv, farbe, ausrichtung, breite } : { art: "text", text, x, y, groesse, fett, farbe, ausrichtung, breite });
    }
    /** „ÜBUNG“ diagonal über die Mitte des Formulars, auf jeder Seite gleich. */
    wasserzeichen() {
      const { text, groesse, winkel, farbe } = WASSERZEICHEN;
      this.pdf.setFont("helvetica", "bold");
      this.pdf.setFontSize(groesse);
      const breite = this.pdf.getTextWidth(text);
      const hoehe = groesse * PT * 0.72;
      const bogen = winkel * Math.PI / 180;
      const x = (LINKS + RECHTS) / 2 - breite / 2 * Math.cos(bogen) + hoehe / 2 * Math.sin(bogen);
      const y = (OBEN + UNTEN) / 2 + breite / 2 * Math.sin(bogen) + hoehe / 2 * Math.cos(bogen);
      this.elemente.push({ art: "wasserzeichen", text, x, y, groesse, farbe, winkel, breite });
    }
    linie(x1, y1, x2, y2, staerke = STRICH) {
      this.elemente.push({ art: "linie", x1, y1, x2, y2, staerke });
    }
    doppellinie(y) {
      this.linie(LINKS, y, RECHTS, y, STRICH_FEIN);
      this.linie(LINKS, y + DOPPEL, RECHTS, y + DOPPEL, STRICH_FEIN);
    }
    /**
     * Text der Eintragungen umbrechen, mit Markdown-Auszeichnungen (fett,
     * kursiv, durchgestrichen) und, wenn `tabellen`, mit Tabellen.
     */
    umbrich(text, breite, tabellen = false) {
      const ergebnis = [];
      const liste = bloecke(text != null ? text : "", tabellen);
      const mitTab = (block) => (block == null ? void 0 : block.art) === "absatz" && block.text.includes("	") && block.text.trim() !== "";
      for (let index = 0; index < liste.length; index++) {
        const block = liste[index];
        if (block.art === "tabelle") {
          ergebnis.push(...this.tabelle(block.zeilen, block.kopfzeilen, breite));
          continue;
        }
        if (mitTab(block)) {
          const gruppe = [block.text];
          while (mitTab(liste[index + 1])) {
            gruppe.push(liste[++index].text);
          }
          ergebnis.push(...umbrechenMitTabs(this.pdf, gruppe.map((absatz) => laeufe(absatz)), breite, this.schrift, TABSTOPP * this.schrift / 10).map((zeile) => this.tabSatzzeile(zeile)));
        } else if (block.text.trim() === "") {
          ergebnis.push(this.textSatzzeile([]));
        } else if (hatAuszeichnung(block.text)) {
          ergebnis.push(...umbrechenLaeufe(this.pdf, laeufe(block.text), breite, this.schrift).map((zeile) => this.textSatzzeile(zeile)));
        } else {
          this.pdf.setFont("helvetica", "normal");
          this.pdf.setFontSize(this.schrift);
          const zeilen = this.pdf.splitTextToSize(trenneLangeWoerter(this.pdf, block.text, breite), breite);
          ergebnis.push(...zeilen.map((zeile) => this.textSatzzeile(zeile ? [{ ...GRUNDSTIL, text: zeile }] : [])));
        }
      }
      return ergebnis;
    }
    /** Eine Textzeile mit Tabstopps: jedes Stück an seiner Stelle. */
    tabSatzzeile(zeile) {
      return {
        hoehe: this.zeile,
        zeichne: (x, oben) => {
          for (const stueck of zeile) {
            this.laeufe(stueck.laeufe, x + stueck.x, oben + oberlaenge(this.schrift));
          }
        }
      };
    }
    /** Eine Textzeile aus Läufen, links beginnend. */
    textSatzzeile(zeile) {
      return {
        hoehe: this.zeile,
        zeichne: (x, oben) => this.laeufe(zeile, x, oben + oberlaenge(this.schrift))
      };
    }
    /** Läufe nebeneinander ab `x` auf der Grundlinie `y`; durchgestrichene mit Strich. */
    laeufe(zeile, x, y, groesse = this.schrift) {
      const schlicht = zeile.length === 1 && !zeile[0].durch;
      let links = x;
      for (const lauf of zeile) {
        this.pdf.setFont("helvetica", schnitt(lauf));
        this.pdf.setFontSize(groesse);
        const breite = this.pdf.getTextWidth(lauf.text);
        const kern = schlicht ? lauf.text : lauf.text.trim();
        const vorne = schlicht ? 0 : this.pdf.getTextWidth(lauf.text.slice(0, lauf.text.length - lauf.text.trimStart().length));
        this.text(kern, links + vorne, y, groesse, { fett: lauf.fett, kursiv: lauf.kursiv });
        if (lauf.durch && kern) {
          const hoehe = y - groesse * PT * 0.3;
          const ende = links + vorne + this.pdf.getTextWidth(kern);
          this.elemente.push({ art: "linie", x1: links + vorne, y1: hoehe, x2: ende, y2: hoehe, staerke: groesse * PT * 0.07 });
        }
        links += breite;
      }
    }
    /**
     * Tabelle als Satzzeilen, eine je Tabellenzeile. Die Spalten sind so breit
     * wie ihr Inhalt; reicht die Breite nicht, bekommen breite Spalten
     * gleich viel und der Text bricht in der Zelle um.
     */
    tabelle(zeilen, kopfzeilen, breite) {
      const spalten = Math.max(...zeilen.map((zeile) => zeile.length));
      const inhalt = zeilen.map((zeile, index) => Array.from({ length: spalten }, (_, spalte) => {
        var _a;
        return laeufe((_a = zeile[spalte]) != null ? _a : "", { ...GRUNDSTIL, fett: index < kopfzeilen });
      }));
      const natuerlich = Array.from({ length: spalten }, (_, spalte) => Math.max(TABELLE_SPALTE_MIN, ...inhalt.map((zeile) => {
        var _a;
        return ((_a = zeile[spalte]) != null ? _a : []).reduce((summe, lauf) => {
          this.pdf.setFont("helvetica", schnitt(lauf));
          this.pdf.setFontSize(this.schrift);
          return summe + this.pdf.getTextWidth(lauf.text.replace(/\s+/g, " "));
        }, 0) + 2 * TABELLE_RAND;
      })));
      const breiten = [...natuerlich];
      if (natuerlich.reduce((a, b) => a + b, 0) > breite) {
        const offen = new Set(natuerlich.map((_, spalte) => spalte));
        let rest = breite;
        for (let geaendert = true; geaendert && offen.size > 0; ) {
          geaendert = false;
          const anteil = rest / offen.size;
          for (const spalte of [...offen]) {
            if (natuerlich[spalte] <= anteil) {
              rest -= natuerlich[spalte];
              offen.delete(spalte);
              geaendert = true;
            }
          }
        }
        for (const spalte of offen) {
          breiten[spalte] = rest / offen.size;
        }
      }
      const gesamt = breiten.reduce((a, b) => a + b, 0);
      return inhalt.map((zeile, index) => {
        const umbrochen = zeile.map((lauf, spalte) => umbrechenLaeufe(this.pdf, lauf, breiten[spalte] - 2 * TABELLE_RAND, this.schrift));
        const oberer = index === 0 ? TABELLE_ABSTAND : 0;
        const unterer = index === inhalt.length - 1 ? TABELLE_ABSTAND : 0;
        const zelle = Math.max(1, ...umbrochen.map((teil) => teil.length)) * this.zeile + 2 * TABELLE_POLSTER;
        return {
          hoehe: oberer + zelle + unterer,
          zeichne: (x, oben) => {
            const o = oben + oberer;
            const u = o + zelle;
            if (index < kopfzeilen) {
              this.elemente.push({ art: "flaeche", x, y: o, b: gesamt, h: zelle, farbe: TABELLE_KOPF });
            }
            this.linie(x, o, x + gesamt, o, STRICH_FEIN);
            this.linie(x, u, x + gesamt, u, STRICH_FEIN);
            let links = x;
            this.linie(links, o, links, u, STRICH_FEIN);
            umbrochen.forEach((teil, spalte) => {
              teil.forEach((laeufe2, nummer) => this.laeufe(laeufe2, links + TABELLE_RAND, o + TABELLE_POLSTER + oberlaenge(this.schrift) + nummer * this.zeile));
              links += breiten[spalte];
              this.linie(links, o, links, u, STRICH_FEIN);
            });
          }
        };
      });
    }
    /** Satzzeilen untereinander ab der Grundlinie `y` der ersten Textzeile setzen. */
    zeilen(zeilen, x, y) {
      let oben = y - oberlaenge(this.schrift);
      for (const zeile of zeilen) {
        zeile.zeichne(x, oben);
        oben += zeile.hoehe;
      }
    }
    /** Gesamthöhe von Satzzeilen. */
    summe(zeilen) {
      return zeilen.reduce((summe, zeile) => summe + zeile.hoehe, 0);
    }
    /**
     * Einzeiliger Wert: verkleinert bis 6 pt, dann mit „…“ gekürzt, damit er
     * nicht ins Nachbarfeld läuft.
     */
    einzeilig(text, x, y, breite, groesse = this.schrift, fett = false) {
      const wert = (text != null ? text : "").replace(/\s+/g, " ").trim();
      if (!wert) {
        return;
      }
      this.pdf.setFont("helvetica", fett ? "bold" : "normal");
      let aktuell = groesse;
      this.pdf.setFontSize(aktuell);
      while (this.pdf.getTextWidth(wert) > breite && aktuell > 6) {
        aktuell = Math.max(6, aktuell - 0.25);
        this.pdf.setFontSize(aktuell);
      }
      this.text(kuerzeAufBreite(this.pdf, wert, breite), x, y, aktuell, { fett });
    }
    // ---- Kopf ----
    kopf() {
      var _a, _b;
      const oben = this.y;
      const unten = oben + KOPF_HOEHE;
      const mitte = oben + KOPF_HOEHE / 2 + SCHRIFT_TITEL * PT * 0.35;
      const titelLinks = LINKS + KOPF_KENNUNG;
      const titelRechts = titelLinks + KOPF_TITEL;
      this.elemente.push({ art: "flaeche", x: titelLinks, y: oben, b: KOPF_TITEL, h: KOPF_HOEHE, farbe: BLAU });
      for (const y of [oben, unten]) {
        this.linie(LINKS, y, RECHTS, y);
      }
      for (const x of [LINKS, titelLinks, titelRechts, RECHTS]) {
        this.linie(x, oben, x, unten);
      }
      this.einzeiligMitte((_a = this.optionen.kennung) != null ? _a : "Fb Fü 3", LINKS + KOPF_KENNUNG / 2, mitte, KOPF_KENNUNG - 2, SCHWARZ);
      this.einzeiligMitte((_b = this.optionen.titel) != null ? _b : "Einsatzauftrag", titelLinks + KOPF_TITEL / 2, mitte, KOPF_TITEL - 4, "#ffffff");
      this.organisation(titelRechts, oben, RECHTS - titelRechts);
    }
    einzeiligMitte(text, x, y, breite, farbe) {
      this.pdf.setFont("helvetica", "bold");
      let groesse = SCHRIFT_TITEL;
      this.pdf.setFontSize(groesse);
      while (this.pdf.getTextWidth(text) > breite && groesse > 6) {
        groesse -= 0.25;
        this.pdf.setFontSize(groesse);
      }
      this.text(kuerzeAufBreite(this.pdf, text, breite), x, y, groesse, { fett: true, farbe, ausrichtung: "mitte" });
    }
    /** Rechtes Kopffeld: Organisation in Blau, rechts daneben das Logo. */
    organisation(links, oben, breite) {
      var _a;
      const rand = 1;
      const logo = this.optionen.logo;
      let textRechts = links + breite - rand * 1.5;
      if (logo && logo.breite > 0 && logo.hoehe > 0) {
        const h = KOPF_HOEHE - 2 * rand;
        const b = Math.min(breite - 2 * rand, h * logo.breite / logo.hoehe);
        const hoehe = b * logo.hoehe / logo.breite;
        const x = links + breite - rand - b;
        this.elemente.push({ art: "bild", daten: logo.daten, format: logo.format, x, y: oben + (KOPF_HOEHE - hoehe) / 2, b, h: hoehe });
        textRechts = x - rand;
      }
      const zeilen = ((_a = this.optionen.organisation) != null ? _a : "Technisches\nHilfswerk").split(/\r?\n|\\n/).map((z) => z.trim()).filter(Boolean).slice(0, 3);
      if (zeilen.length === 0) {
        return;
      }
      const verfuegbar = textRechts - links - rand * 1.5;
      if (verfuegbar < 5) {
        return;
      }
      this.pdf.setFont("helvetica", "bold");
      let groesse = zeilen.length > 2 ? 8 : 11;
      this.pdf.setFontSize(groesse);
      while (Math.max(...zeilen.map((z) => this.pdf.getTextWidth(z))) > verfuegbar && groesse > 6) {
        groesse -= 0.25;
        this.pdf.setFontSize(groesse);
      }
      const abstand = groesse * PT * 1.05;
      const erste = oben + KOPF_HOEHE / 2 - (zeilen.length - 1) * abstand / 2 + groesse * PT * 0.35;
      const mittig = textRechts === links + breite - rand * 1.5;
      zeilen.forEach((zeile, index) => {
        const text = kuerzeAufBreite(this.pdf, zeile, verfuegbar);
        this.text(
          text,
          mittig ? links + breite / 2 : textRechts,
          erste + index * abstand,
          groesse,
          { fett: true, farbe: BLAU, ausrichtung: mittig ? "mitte" : "rechts" }
        );
      });
    }
    // ---- Absender, Einsatz, Empfänger ----
    adresse() {
      const wertX = LINKS + ADRESSE_BESCHRIFTUNG + 1.5;
      const lfdLinks = RECHTS - ADRESSE_LFDNR;
      const zeilen = [
        { beschriftung: "Absender", zeilen: this.umbrich(this.daten.absender, RECHTS - wertX - 1.5) },
        { beschriftung: "für den Einsatz", zeilen: this.umbrich(this.daten.einsatz, lfdLinks - wertX - 1.5) },
        { beschriftung: "Empfänger", zeilen: this.umbrich(this.daten.empfaenger, RECHTS - wertX - 1.5) }
      ];
      const hoehen = zeilen.map((zeile, index) => {
        var _a;
        return Math.max((_a = ADRESSE_MINDESTHOEHEN[index]) != null ? _a : ZEILE_MIN, zeile.zeilen.length * this.zeile + 2 * this.polster);
      });
      const oben = this.y;
      this.doppellinie(oben);
      let y = oben + DOPPEL;
      const grund = (zeilenOben) => zeilenOben + this.polster + oberlaenge(this.schrift);
      zeilen.forEach((zeile, index) => {
        var _a;
        const hoehe = (_a = hoehen[index]) != null ? _a : 0;
        if (index > 0) {
          this.linie(LINKS, y, RECHTS, y);
        }
        this.text(zeile.beschriftung, LINKS + 1.5, grund(y), SCHRIFT_BESCHRIFTUNG);
        this.zeilen(zeile.zeilen, wertX, grund(y));
        if (index === 1) {
          this.linie(lfdLinks, y, lfdLinks, y + hoehe);
          this.text("lfd. Nr.:", lfdLinks + 1.5, grund(y), SCHRIFT_BESCHRIFTUNG);
          this.einzeilig(this.daten.laufendeNummer, lfdLinks + 1.5, grund(y) + this.zeile + 1, ADRESSE_LFDNR - 3, this.schrift + 1, true);
        }
        y += hoehe;
      });
      this.linie(LINKS + ADRESSE_BESCHRIFTUNG, oben + DOPPEL, LINKS + ADRESSE_BESCHRIFTUNG, y);
      this.doppellinie(y);
      this.linie(LINKS, oben, LINKS, y + DOPPEL);
      this.linie(RECHTS, oben, RECHTS, y + DOPPEL);
      this.y = y + DOPPEL + this.abstand;
    }
    // ---- Abschnitte 1–5 ----
    textzeilen(abschnitt) {
      var _a;
      const breite = BREITE - KENNUNG_SPALTE - 3;
      if (abschnitt.unterpunkte.length > 0) {
        return abschnitt.unterpunkte.map((punkt) => {
          var _a2;
          return {
            kennung: punkt.kennung,
            zeilen: this.umbrich((_a2 = this.daten.unterpunkte) == null ? void 0 : _a2[punkt.kennung], breite, true),
            mindesthoehe: ZEILE_MIN
          };
        });
      }
      if (abschnitt.nummer === 2) {
        return [{ kennung: "", zeilen: this.umbrich(this.daten.auftrag, breite, true), mindesthoehe: AUFTRAG_MIN }];
      }
      const fuehrung = (_a = this.daten.fuehrung) != null ? _a : "";
      const normal = (text) => String(text != null ? text : "").replace(/\\n/g, "\n").replace(/\r\n?/g, "\n");
      const roh = Array.isArray(fuehrung) ? fuehrung.map(normal) : normal(fuehrung).split(/\n[ \t]*\n/);
      const absaetze = roh.map((absatz) => absatz.replace(/^(?:[ \t]*\n)+|(?:\n[ \t]*)+$/g, "").trimEnd()).filter((absatz) => absatz.trim() !== "");
      if (absaetze.length === 0) {
        absaetze.push("");
      }
      return absaetze.map((absatz) => ({ kennung: "", zeilen: this.umbrich(absatz, breite, true), mindesthoehe: ZEILE_MIN }));
    }
    zeilenhoehe(zeilen, mindesthoehe) {
      return Math.max(mindesthoehe, this.summe(zeilen) + 2 * this.polster);
    }
    abschnitt(abschnitt) {
      const offen = this.textzeilen(abschnitt);
      let fortsetzung = false;
      while (offen.length > 0) {
        const kopf = this.abschnittKopfhoehe(abschnitt, fortsetzung);
        const erste = offen[0];
        const mindestens = this.zeilenhoehe(erste.zeilen.slice(0, 2), erste.mindesthoehe);
        if (this.y + DOPPEL + kopf + mindestens + DOPPEL > UNTEN && this.y > OBEN + KOPF_HOEHE + this.abstand + 0.01) {
          this.neueSeite();
        }
        const oben = this.y;
        this.doppellinie(oben);
        this.abschnittKopf(abschnitt, oben + DOPPEL, kopf, fortsetzung);
        let y = oben + DOPPEL + kopf;
        const zeilenOben = y;
        let gesetzt = 0;
        while (offen.length > 0) {
          const zeile = offen[0];
          const hoehe = this.zeilenhoehe(zeile.zeilen, zeile.mindesthoehe);
          if (y + hoehe + DOPPEL <= UNTEN) {
            this.textzeile(zeile, y);
            y += hoehe;
            gesetzt++;
            offen.shift();
            continue;
          }
          const frei = UNTEN - DOPPEL - y - 2 * this.polster;
          let passen = 0;
          for (let summe = 0; passen < zeile.zeilen.length && summe + zeile.zeilen[passen].hoehe <= frei + 1e-9; passen++) {
            summe += zeile.zeilen[passen].hoehe;
          }
          if (passen === 0 && gesetzt === 0 && oben <= OBEN + KOPF_HOEHE + this.abstand + 0.01) {
            if (zeile.zeilen.length <= 1) {
              this.textzeile(zeile, y);
              y += hoehe;
              gesetzt++;
              offen.shift();
              continue;
            }
            passen = 1;
          }
          const mindestTeil = gesetzt === 0 ? 1 : 2;
          if (passen >= mindestTeil && zeile.zeilen.length > passen) {
            const teil = { ...zeile, zeilen: zeile.zeilen.slice(0, passen) };
            const teilhoehe = this.zeilenhoehe(teil.zeilen, 0);
            this.textzeile(teil, y);
            y += teilhoehe;
            zeile.zeilen = zeile.zeilen.slice(passen);
            zeile.mindesthoehe = ZEILE_MIN;
          }
          break;
        }
        this.linie(LINKS + KENNUNG_SPALTE, zeilenOben, LINKS + KENNUNG_SPALTE, y);
        this.doppellinie(y);
        this.linie(LINKS, oben, LINKS, y + DOPPEL);
        this.linie(RECHTS, oben, RECHTS, y + DOPPEL);
        this.y = y + DOPPEL + this.abstand;
        if (offen.length > 0) {
          this.neueSeite();
          fortsetzung = true;
        }
      }
    }
    abschnittKopfZeilen(abschnitt) {
      const x = this.erlaeuterungX(abschnitt);
      this.pdf.setFont("helvetica", "normal");
      this.pdf.setFontSize(SCHRIFT_ERLAEUTERUNG);
      return this.pdf.splitTextToSize(abschnitt.erlaeuterung, RECHTS - 1.5 - x);
    }
    /** Die Erläuterung beginnt bündig wie im Vordruck, bei langem Titel dahinter. */
    erlaeuterungX(abschnitt) {
      this.pdf.setFont("helvetica", "bold");
      this.pdf.setFontSize(SCHRIFT_TITEL);
      const titelEnde = LINKS + 1.5 + this.pdf.getTextWidth(`${abschnitt.nummer}. ${abschnitt.titel}`);
      return Math.max(LINKS + ERLAEUTERUNG_X, titelEnde + 5);
    }
    abschnittKopfhoehe(abschnitt, fortsetzung) {
      if (fortsetzung) {
        return ZEILE_MIN + 1;
      }
      const zeilen = this.abschnittKopfZeilen(abschnitt).length;
      return Math.max(ABSCHNITT_KOPF_MIN, 4.6 + (zeilen - 1) * SCHRIFT_ERLAEUTERUNG * PT * 1.15 + 3);
    }
    abschnittKopf(abschnitt, oben, hoehe, fortsetzung) {
      this.elemente.push({ art: "flaeche", x: LINKS, y: oben, b: BREITE, h: hoehe, farbe: GELB });
      this.text(`${abschnitt.nummer}. ${abschnitt.titel}${fortsetzung ? " (Fortsetzung)" : ""}`, LINKS + 1.5, oben + 5.2, SCHRIFT_TITEL, { fett: true });
      if (!fortsetzung) {
        const x = this.erlaeuterungX(abschnitt);
        this.abschnittKopfZeilen(abschnitt).forEach((zeile, index) => this.text(zeile, x, oben + 4.4 + index * SCHRIFT_ERLAEUTERUNG * PT * 1.15, SCHRIFT_ERLAEUTERUNG));
      }
    }
    textzeile(zeile, oben) {
      this.linie(LINKS, oben, RECHTS, oben);
      this.text(zeile.kennung, LINKS + 0.8, oben + 2.9, SCHRIFT_KENNUNG);
      this.zeilen(zeile.zeilen, LINKS + KENNUNG_SPALTE + 1.5, oben + this.polster + oberlaenge(this.schrift));
    }
    // ---- Unterschrift und Abgesandt ----
    abschluss() {
      const spalten = ABSCHLUSS_SPALTEN.map((x) => LINKS + x);
      const grenzen = [spalten[1], spalten[2], RECHTS - 1.5];
      const felder = [this.daten.ortDatum, this.daten.unterschrift, this.daten.dienststelle].map((wert, index) => this.umbrich(wert, grenzen[index] - spalten[index] - 2));
      const beschriftungY = 3.6;
      const wertOben = beschriftungY + 1.4;
      const obenHoehe = Math.max(ABSCHLUSS_OBEN_MIN, wertOben + Math.max(...felder.map((f) => f.length)) * this.zeile + this.polster);
      const gesamt = obenHoehe + ABSCHLUSS_UNTEN_MIN;
      if (this.y + gesamt > UNTEN) {
        this.neueSeite();
      }
      const oben = this.y;
      const mitte = oben + obenHoehe;
      const unten = mitte + ABSCHLUSS_UNTEN_MIN;
      for (const y of [oben, mitte, unten]) {
        this.linie(LINKS, y, RECHTS, y);
      }
      this.linie(LINKS, oben, LINKS, unten);
      this.linie(RECHTS, oben, RECHTS, unten);
      ["Ort, Datum", "Unterschrift", "Dienststellung"].forEach((beschriftung, index) => {
        var _a;
        const x = spalten[index];
        this.text(beschriftung, x, oben + beschriftungY, 9);
        this.zeilen((_a = felder[index]) != null ? _a : [], x, oben + wertOben + oberlaenge(this.schrift));
      });
      const [am, von, unterschrift] = ABGESANDT_SPALTEN.map((x) => LINKS + x);
      for (const x of [am, von, unterschrift]) {
        this.linie(x, mitte, x, unten);
      }
      const grundlinie = mitte + ABSCHLUSS_UNTEN_MIN / 2 + SCHRIFT_BESCHRIFTUNG * PT * 0.35;
      const um = am + 29.5 * QUER;
      this.text("Abgesandt", LINKS + 1.5, grundlinie, SCHRIFT_BESCHRIFTUNG);
      this.text("am", am + 1.5, grundlinie, SCHRIFT_BESCHRIFTUNG);
      this.text("um", um, grundlinie, SCHRIFT_BESCHRIFTUNG);
      this.text("von", von + 1.5, grundlinie, SCHRIFT_BESCHRIFTUNG);
      this.text("Unterschrift:", unterschrift + 1.5, grundlinie, SCHRIFT_BESCHRIFTUNG);
      this.einzeilig(this.daten.abgesandtAm, am + 7, grundlinie, um - am - 8);
      this.einzeilig(this.daten.abgesandtUm, um + 6, grundlinie, von - um - 7);
      this.einzeilig(this.daten.abgesandtVon, von + 8.5, grundlinie, unterschrift - von - 10);
      this.y = unten;
    }
    // ---- Blattrand ----
    fusszeilen() {
      var _a;
      const anzahl = this.seiten.length;
      const nummer = ((_a = this.daten.laufendeNummer) != null ? _a : "").replace(/\s+/g, " ").trim();
      this.seiten.forEach((seite, index) => {
        var _a2;
        this.elemente = seite;
        const rechts = [this.daten.uebung ? "ÜBUNG" : "", nummer ? `lfd. Nr. ${nummer}` : "", `Seite ${index + 1} von ${anzahl}`].filter(Boolean).join(" · ");
        this.pdf.setFont("helvetica", "normal");
        this.pdf.setFontSize(7);
        const rechtsText = kuerzeAufBreite(this.pdf, rechts, BREITE / 2);
        this.text(rechtsText, RECHTS, FUSS_Y, 7, { farbe: GRAU, ausrichtung: "rechts" });
        const fuss = ((_a2 = this.optionen.fusszeile) != null ? _a2 : "").replace(/\s+/g, " ").trim();
        if (fuss) {
          this.pdf.setFontSize(7);
          this.text(kuerzeAufBreite(this.pdf, fuss, BREITE - this.pdf.getTextWidth(rechtsText) - 5), LINKS, FUSS_Y, 7, { farbe: GRAU });
        }
      });
    }
  };

  // ../ea-src/wattnpapa-einsatzauftrag-f642655/src/zeichnen.ts
  function zeichneSeite(pdf, seite) {
    for (const element of seite.elemente) {
      switch (element.art) {
        case "flaeche":
          pdf.setFillColor(element.farbe);
          pdf.rect(element.x, element.y, element.b, element.h, "F");
          break;
        case "linie":
          pdf.setDrawColor(0, 0, 0);
          pdf.setLineWidth(element.staerke);
          pdf.line(element.x1, element.y1, element.x2, element.y2);
          break;
        case "text":
          pdf.setFont("helvetica", element.fett ? element.kursiv ? "bolditalic" : "bold" : element.kursiv ? "italic" : "normal");
          pdf.setFontSize(element.groesse);
          pdf.setTextColor(element.farbe);
          pdf.text(element.text, element.x, element.y, {
            align: element.ausrichtung === "links" ? "left" : element.ausrichtung === "mitte" ? "center" : "right"
          });
          break;
        case "bild":
          pdf.addImage(element.daten, element.format, element.x, element.y, element.b, element.h);
          break;
        case "wasserzeichen":
          pdf.setFont("helvetica", "bold");
          pdf.setFontSize(element.groesse);
          pdf.setTextColor(element.farbe);
          pdf.text(element.text, element.x, element.y, { angle: element.winkel });
          break;
      }
    }
    pdf.setFont("helvetica", "normal");
    pdf.setTextColor(0, 0, 0);
    pdf.setDrawColor(0, 0, 0);
    pdf.setFillColor(0, 0, 0);
    pdf.setLineWidth(0.200025);
  }
  function zeichneEinsatzauftrag(pdf, daten, optionen = {}) {
    const satz = setzeEinsatzauftrag(pdf, daten, optionen);
    satz.seiten.forEach((seite, index) => {
      if (index > 0) {
        pdf.addPage("a4", "p");
      }
      zeichneSeite(pdf, seite);
    });
    return satz;
  }
  return __toCommonJS(index_exports);
})();
