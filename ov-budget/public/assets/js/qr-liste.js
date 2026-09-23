/*
 * Zeichnet viele QR-Codes auf einmal – einen je Element mit data-qr.
 * Die Erzeugung übernimmt die Bibliothek qrcode.js (siehe deren Kopf).
 *
 * Gedacht für die Einladungsliste: Jede eingeladene Person bekommt ihren
 * eigenen Code, damit sich der Brief zusammenkleben oder abscannen lässt.
 */
(function () {
  if (typeof qrcode !== 'function') {
    return;
  }
  var bloecke = document.querySelectorAll('[data-qr]');
  Array.prototype.forEach.call(bloecke, function (block) {
    var text = block.getAttribute('data-qr');
    if (!text) { return; }
    try {
      // Fassung 0 heißt: die Bibliothek sucht die kleinste passende selbst
      var code = qrcode(0, 'M');
      code.addData(text);
      code.make();
      block.innerHTML = code.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    } catch (e) {
      block.textContent = text;
    }
  });
})();
