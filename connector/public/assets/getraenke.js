/*
 * Getränke melden – „leer", „wird knapp" oder „reicht noch", verschlüsselt für OV-Multitool.
 *
 * Gleicher Weg wie bei den Standortmeldungen: flüchtiges Schlüsselpaar,
 * ECDH auf P-256, HKDF, AES-256-GCM. Der Connector bekommt nur Geheimtext;
 * er weiß, dass jemand gemeldet hat, aber nicht was.
 */
(function () {
  var box = document.getElementById('box');
  if (!box) { return; }

  // Name des Lagerplatzes steht im Anker der Adresse und bleibt im Browser
  try {
    var anker = new URLSearchParams((window.location.hash || '').replace(/^#/, ''));
    var name = anker.get('n');
    if (name) {
      var feld = document.getElementById('was');
      var lesbar = decodeURIComponent(escape(atob(name.replace(/-/g, '+').replace(/_/g, '/'))));
      if (feld && lesbar) { feld.textContent = lesbar; document.title = lesbar + ' – Getränke melden'; }
    }
  } catch (e) { /* ohne Anker bleibt es bei "Lagerplatz" */ }

  var token = box.getAttribute('data-token');
  var basis = box.getAttribute('data-basis') || '';
  var serverSchluessel = box.getAttribute('data-schluessel');
  var formular = document.getElementById('formular');
  var meldung = document.getElementById('meldung');
  var melder = document.getElementById('melder');
  var knopf = document.getElementById('senden');

  function sage(text, art) {
    meldung.textContent = text;
    meldung.className = 'meldung' + (art ? ' ' + art : '');
  }

  function b64uZuBytes(s) {
    var rest = '='.repeat((4 - (s.length % 4)) % 4);
    var roh = atob((s + rest).replace(/-/g, '+').replace(/_/g, '/'));
    var b = new Uint8Array(roh.length);
    for (var i = 0; i < roh.length; i++) { b[i] = roh.charCodeAt(i); }
    return b;
  }

  function bytesZuB64u(bytes) {
    var s = '';
    var b = new Uint8Array(bytes);
    for (var i = 0; i < b.length; i++) { s += String.fromCharCode(b[i]); }
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  try {
    var gemerkt = window.localStorage.getItem('ovb_melder');
    if (gemerkt && melder) { melder.value = gemerkt; }
  } catch (e) { /* privater Modus: dann eben nicht */ }

  if (!window.isSecureContext || !window.crypto || !window.crypto.subtle) {
    sage('Dieses Gerät kann hier nicht melden. Nötig sind eine verschlüsselte Verbindung (https) '
      + 'und ein aktueller Browser.', 'schlecht');
    knopf.disabled = true;
    return;
  }

  /* Alle Getränke einsammeln, bei denen etwas angetippt oder eine Zahl eingetragen ist */
  function einsammeln() {
    var liste = [];
    Array.prototype.forEach.call(formular.querySelectorAll('[data-getraenk]'), function (zeile) {
      var id = parseInt(zeile.getAttribute('data-getraenk'), 10);
      var stand = zeile.querySelector('input[type=radio]:checked');
      var zahl = zeile.querySelector('input[type=number]');
      var anzahl = zahl && zahl.value.trim() !== '' ? parseInt(zahl.value, 10) : null;
      if (anzahl !== null && (isNaN(anzahl) || anzahl < 0)) { anzahl = null; }
      if (!stand && anzahl === null) { return; }
      liste.push({ id: id, stand: stand ? stand.value : (anzahl === 0 ? 'leer' : 'ok'), anzahl: anzahl });
    });
    return liste;
  }

  async function verschluesseln(inhalt) {
    var empfaenger = await crypto.subtle.importKey(
      'raw', b64uZuBytes(serverSchluessel), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    var eigenes = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
    var geheim = await crypto.subtle.deriveBits({ name: 'ECDH', public: empfaenger }, eigenes.privateKey, 256);
    var eigenerPunkt = new Uint8Array(await crypto.subtle.exportKey('raw', eigenes.publicKey));

    var salz = crypto.getRandomValues(new Uint8Array(16));
    var basisSchluessel = await crypto.subtle.importKey('raw', geheim, 'HKDF', false, ['deriveBits']);
    var info = new TextEncoder().encode('OV-Budget Standort v1');
    var abgeleitet = new Uint8Array(await crypto.subtle.deriveBits(
      { name: 'HKDF', hash: 'SHA-256', salt: salz, info: info }, basisSchluessel, 352));
    var schluessel = await crypto.subtle.importKey('raw', abgeleitet.slice(0, 32), 'AES-GCM', false, ['encrypt']);
    var nonce = abgeleitet.slice(32, 44);

    var geheimtext = new Uint8Array(await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce }, schluessel, new TextEncoder().encode(JSON.stringify(inhalt))));

    var paket = new Uint8Array(1 + 65 + 16 + geheimtext.length);
    paket[0] = 1;
    paket.set(eigenerPunkt, 1);
    paket.set(salz, 66);
    paket.set(geheimtext, 82);
    return bytesZuB64u(paket);
  }

  async function melden(liste) {
    var name = melder ? melder.value.trim().slice(0, 60) : '';
    try { window.localStorage.setItem('ovb_melder', name); } catch (e) { /* egal */ }
    var daten = await verschluesseln({ meldungen: liste, melder: name, zeit: Math.floor(Date.now() / 1000) });
    var antwort = await fetch(basis + 'index.php?p=getraenkemeldung', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ t: token, daten: daten })
    });
    var ergebnis = await antwort.json().catch(function () { return {}; });
    if (!antwort.ok || !ergebnis.ok) {
      throw new Error(ergebnis.fehler || ('Server antwortete mit ' + antwort.status));
    }
  }

  formular.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var liste = einsammeln();
    if (!liste.length) {
      sage('Bitte bei mindestens einem Getränk antippen, was zutrifft.', 'schlecht');
      return;
    }
    knopf.disabled = true;
    sage('Wird verschlüsselt und gesendet …');
    melden(liste).then(function () {
      sage(liste.length === 1 ? 'Ein Getränk gemeldet. Danke!' : liste.length + ' Getränke gemeldet. Danke!', 'gut');
      knopf.textContent = 'Nochmal melden';
      knopf.disabled = false;
      formular.reset();
      if (melder) { try { melder.value = window.localStorage.getItem('ovb_melder') || ''; } catch (e) { /* egal */ } }
    }).catch(function (fehler) {
      knopf.disabled = false;
      sage('Hat nicht geklappt: ' + ((fehler && fehler.message) || 'unbekannter Grund') + '.', 'schlecht');
    });
  });
})();
