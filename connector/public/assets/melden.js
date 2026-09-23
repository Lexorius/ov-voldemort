/*
 * Standort melden – verschlüsselt für OV-Budget.
 *
 * Ablauf einer Meldung:
 *   1. Standort vom Gerät holen (der Browser fragt um Erlaubnis)
 *   2. flüchtiges Schlüsselpaar erzeugen, gemeinsames Geheimnis mit dem
 *      öffentlichen Schlüssel von OV-Budget aushandeln (ECDH, P-256)
 *   3. daraus Schlüssel und Zufallswert ableiten (HKDF-SHA256)
 *   4. Angaben mit AES-GCM verschlüsseln und abschicken
 *
 * Der Connector bekommt nur: flüchtiger öffentlicher Schlüssel, Zufallswert
 * und Geheimtext. Entschlüsseln kann das ausschließlich OV-Budget.
 */
(function () {
  var box = document.getElementById('box');
  if (!box) { return; }

  var token = box.getAttribute('data-token');
  var serverSchluessel = box.getAttribute('data-schluessel');
  var meldung = document.getElementById('meldung');
  var melder = document.getElementById('melder');
  var knoepfe = {
    einmal: document.getElementById('einmal'),
    dauer60: document.getElementById('dauer60'),
    dauer500: document.getElementById('dauer500'),
    stopp: document.getElementById('stopp')
  };
  var timer = null;
  var gesendet = 0;

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

  // Name auf diesem Gerät merken – nur hier, er verlässt es nur mit einer Meldung
  try {
    var gemerkt = window.localStorage.getItem('ovb_melder');
    if (gemerkt && melder) { melder.value = gemerkt; }
  } catch (e) { /* privater Modus: dann eben nicht */ }

  if (!window.isSecureContext || !window.crypto || !window.crypto.subtle || !navigator.geolocation) {
    sage('Dieses Gerät kann hier nicht melden. Nötig sind eine verschlüsselte Verbindung (https) '
      + 'und ein aktueller Browser.', 'schlecht');
    Object.keys(knoepfe).forEach(function (k) { if (knoepfe[k]) { knoepfe[k].disabled = true; } });
    return;
  }

  function standort() {
    return new Promise(function (fertig, fehler) {
      navigator.geolocation.getCurrentPosition(fertig, fehler,
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 15000 });
    });
  }

  async function verschluesseln(inhalt) {
    var empfaenger = await crypto.subtle.importKey(
      'raw', b64uZuBytes(serverSchluessel), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    var eigenes = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
    var geheim = await crypto.subtle.deriveBits({ name: 'ECDH', public: empfaenger }, eigenes.privateKey, 256);
    var eigenerPunkt = new Uint8Array(await crypto.subtle.exportKey('raw', eigenes.publicKey));

    // Aus dem gemeinsamen Geheimnis Schlüssel und Zufallswert ableiten
    var salz = crypto.getRandomValues(new Uint8Array(16));
    var basis = await crypto.subtle.importKey('raw', geheim, 'HKDF', false, ['deriveBits']);
    var info = new TextEncoder().encode('OV-Budget Standort v1');
    var abgeleitet = new Uint8Array(await crypto.subtle.deriveBits(
      { name: 'HKDF', hash: 'SHA-256', salt: salz, info: info }, basis, 352));   // 32 Byte Schlüssel + 12 Byte Nonce
    var schluessel = await crypto.subtle.importKey('raw', abgeleitet.slice(0, 32), 'AES-GCM', false, ['encrypt']);
    var nonce = abgeleitet.slice(32, 44);

    var geheimtext = new Uint8Array(await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce }, schluessel, new TextEncoder().encode(JSON.stringify(inhalt))));

    var paket = new Uint8Array(1 + 65 + 16 + geheimtext.length);
    paket[0] = 1;                       // Fassung des Formats
    paket.set(eigenerPunkt, 1);
    paket.set(salz, 66);
    paket.set(geheimtext, 82);
    return bytesZuB64u(paket);
  }

  async function melden() {
    var pos = await standort();
    var name = melder ? melder.value.trim().slice(0, 60) : '';
    try {
      window.localStorage.setItem('ovb_melder', name);
    } catch (e) { /* egal */ }

    var daten = await verschluesseln({
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      genauigkeit: pos.coords.accuracy || null,
      zeit: Math.floor(Date.now() / 1000),
      melder: name
    });

    var antwort = await fetch('index.php?p=position', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fz: token, daten: daten })
    });
    var ergebnis = await antwort.json().catch(function () { return {}; });
    if (!antwort.ok || !ergebnis.ok) {
      throw new Error(ergebnis.fehler || ('Server antwortete mit ' + antwort.status));
    }
  }

  function zeitText() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2)
      + ':' + ('0' + d.getSeconds()).slice(-2);
  }

  function laufen(sekunden) {
    stoppen();
    einmalSenden(function () {
      timer = window.setInterval(function () { einmalSenden(null, sekunden); }, sekunden * 1000);
      knoepfe.stopp.hidden = false;
      knoepfe.dauer60.disabled = true;
      knoepfe.dauer500.disabled = true;
    }, sekunden);
  }

  function stoppen() {
    if (timer) { window.clearInterval(timer); timer = null; }
    knoepfe.stopp.hidden = true;
    knoepfe.dauer60.disabled = false;
    knoepfe.dauer500.disabled = false;
  }

  function einmalSenden(danach, takt) {
    knoepfe.einmal.disabled = true;
    sage('Standort wird ermittelt …');
    melden().then(function () {
      gesendet++;
      knoepfe.einmal.disabled = false;
      sage('Gesendet um ' + zeitText() + '.'
        + (takt ? ' Nächste Meldung in ' + takt + ' Sekunden.' : '')
        + (gesendet > 1 ? ' (' + gesendet + ' Meldungen)' : ''), 'gut');
      if (danach) { danach(); }
    }).catch(function (fehler) {
      knoepfe.einmal.disabled = false;
      stoppen();
      sage(fehler && fehler.code === 1
        ? 'Ohne Erlaubnis für den Standort geht es nicht.'
        : 'Hat nicht geklappt: ' + ((fehler && fehler.message) || 'unbekannter Grund') + '.', 'schlecht');
    });
  }

  knoepfe.einmal.addEventListener('click', function () { stoppen(); einmalSenden(null); });
  knoepfe.dauer60.addEventListener('click', function () { laufen(60); });
  knoepfe.dauer500.addEventListener('click', function () { laufen(500); });
  knoepfe.stopp.addEventListener('click', function () {
    stoppen();
    sage('Beendet. Es wird nichts mehr gesendet.');
  });
})();
