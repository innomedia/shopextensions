# shopextensions – Testplan

Abnahme-/Testcheckliste für das Modul `innomedia/shopextensions`. Sie gehört zum Modul (neben
[`USAGE.md`](USAGE.md)), damit sie mit dem Modul mitwandert und Kollegen unabhängig vom Projekt
weiter abhaken können. Feature-Konfiguration und Config-Referenz siehe `USAGE.md`.

Diese Checkliste wird am Ende der Optimierung **einmal komplett** durchgegangen.
Voraussetzung für alle Tests: einmal `dev/build?flush=1` als Webserver-User (Schreibrechte
auf `public/assets` müssen passen).

Legende: ☐ offen · ☑ bestanden · ✗ fehlgeschlagen · 🔴 **PRIORITÄT 1**

---

> ## 🔴 ZUERST PRÜFEN vor Go-Live: Verzögerte Zahlungen (SEPA/async) — **[Kapitel 11](#11-verzögerte-zahlungen-sepaasync--auto-storno-optionales-feature)**
> Bevor das Modul produktiv geht, **müssen zwingend die Async-Payment-Tests aus Kapitel 11 als
> Erstes** durchlaufen werden — insbesondere **11.1** (braucht es `use_async_notification`?),
> **11.2** (async Unpaid→Paid, Webhook) und **11.8** (Doppelzahlung). Grund: das Zusammenspiel
> Flag ↔ Webhook ↔ Rücksprung entscheidet über korrekte Zahlungszustände und verhindert
> Doppelzahlungen. Voraussetzung: Mollie-Testmode + **öffentlich erreichbare Notify-URL**.

---

## 1. SiteConfig / CMS-Einstellungen

- ☑ **Tab-Struktur:** In den Website-Einstellungen liegen alle Shop-Felder unter
  **Einstellungen → Shop** (silvershop-Reiter), in den Untertabs *Rechnung & Belege*,
  *Benachrichtigungen*, *Hinweise & Texte*. Keine losen Tabs „Produkte"/„Bestellabschluss" mehr.
  (Doppelter „Shop"-Reiter durch Extension-Reihenfolge behoben: `After: '#silvershop-extensions'`.)
- ☑ **Feld-Beschreibungen:** Jedes Feld hat eine verständliche Beschreibung, die erklärt,
  wo der Wert erscheint. (Logo: 400–1000 px + Warnung bei zu großen Dateien; Checkout-Hinweis:
  „über der Auswahl der Zahlungsmittel"; Hinweis nach Abschluss: Tipp „wie es weitergeht".)
- ☑ **enabled_fields:** Nur die aktivierten Felder werden angezeigt. Dist-Vorlage liegt als
  `shopextensions/_config/siteconfig.yml.example` bei und ist in USAGE (Abschnitt 4) genannt.
- ☑ **Speichern:** Werte lassen sich speichern und bleiben nach Reload erhalten.
- ☑ **Logo-Upload:** `ReceiptLogo` lässt sich hochladen und erscheint später im PDF.

## 2. Nummernkreis / Rechnungsnummern

- ☑ **Präfix + Startwert leer:** Erste bezahlte Bestellung erhält `RE200000`
  (Fallback-Konstanten greifen).
- ☑ **Präfix + Startwert gesetzt** (z. B. `RG` / `500000`): Erste Rechnung `RG500000`,
  Folgebestellung `RG500001` (fortlaufend +1).
- ☑ **Bestehende Rechnungsnummer** wird bei erneutem `onPaid`/Statuswechsel **nicht** überschrieben
  (auch bei manuellem Unpaid → Paid → Unpaid bestätigt).
- ☑ **Manuelle Zahlart (Rechnung):** Nummer wird bei **Autorisierung des Manual-Payments** vergeben.
  *Fix in dieser                                                          
- Runde:* Die alte Vergabe hing an `onStatusChange` (Cart→Unpaid), zu diesem
  Zeitpunkt existiert das Payment aber noch nicht → Nummer blieb 0 (siehe Order 5). Vergabe läuft
  jetzt über `ShopExtensions\PaymentExtension::onAuthorized/onCaptured` (Gateway `Manual`).
- ☑ **0 €-Bestellung:** bekommt per Default **keine** Rechnung (keine Nummer, kein Beleg-PDF, keine
  Beleg-Mail) und verbraucht **keine** Nummer. Mit `Order.issue_invoice_for_zero_total: true` wird
  sie wie eine bezahlte Bestellung behandelt.

## 3. Steuer (Bug-Fix aus dieser Runde)

- ☑ **Dropdown-Werte:** Produkt-„Steuersatz"-Dropdown hat die Option-Werte **0/7/19**
  (nicht 0/1/2). Prüfen im gerenderten HTML.
- ☑ **Speichern 19%:** Auswahl „19% (regulär)" speichert `Tax=19` in der DB.
- ☑ **Warenkorb:** Produkt mit 19% zeigt „zzgl. MwSt. zu 19%" mit korrektem Betrag
  (50 € → 9,50 € bei tax_mode=exclusive); auch 7% korrekt angezeigt/berechnet.
- ☑ **19% als Default:** Neues Produkt bekommt Tax=19 vorbelegt (Dropdown zeigt „19% (regulär)").
  Fix: Vorbelegung lief über eine nie aufgerufene `populateDefaults()` → jetzt `onAfterPopulateDefaults`.
  Zusätzlich DB-Spalten-Default `Decimal(5,2,19)` = 19,00 (greift nach `dev/build`).
- ☑ **Produkt 33** zeigt nach Fix 19% (Regressions-Check der Datenkorrektur).
- ☑ **inclusive-Modus:** bei `tax_mode: inclusive` erscheint im Warenkorb „Enthaltene MwSt. zu 19%",
  und der **Produkt-/Seminar-Preishinweis** zeigt „inkl. MwSt." statt hartcodiert „zzgl. MwSt.".
  Fix: neuer, tax_mode-abhängiger Helper `$SiteConfig.TaxHint` ersetzt die vier hartcodierten
  `<%t …PLUS_VAT "zzgl. MwSt." %>` in den Theme-Templates (SeminarFormatPage, Course ×2, SeminarPage).
- ☑ **Steuer bei platzierten Bestellungen eingefroren (idiomatisch):** Nach Umstellen von `tax_mode`
  (oder Sätzen) ändert sich bei **placed Orders** die Steuerzeile **nicht** mehr (Betrag *und*
  Label); nur Carts rechnen live (verifiziert: Cart 9 wechselt 7,00€→6,54€, Order 7 bleibt 9,50€ /
  „zzgl. MwSt. zu 19%"). Umsetzung: `CustomTaxModifier.value()` liefert den Steuerbeitrag (Basis-
  `modify()` friert ihn in `Amount`), beim Placement wird die Steuer **pro Zeile** auf die OrderItems
  (`TaxRate`/`TaxAmount`, wie `CalculatedTotal`) und der Modus auf `Order.TaxMode` eingefroren
  (engine-aware verteilt). **Setup:** `dev/build` (Spalten `OrderItem.TaxRate/TaxAmount`,
  `Order.TaxMode`) + einmalig `FreezeOrderTaxTask` (hier gelaufen, 7 Orders eingefroren).

### 3.1 Inclusive-Modus (B2C) – noch komplett zu testen

> Bisher nur **exclusive** durchgespielt (Checkout ok). Zum Testen `tax_mode: inclusive` setzen
> (Projekt-Override in `app/_config/shop.yml` von `exclusive` auf `inclusive`), `?flush=1`.
> **Nur Checkout/Anzeige** — der Export-Gegentest steht separat unter **9.2 (B2C)** und kommt später.

- ☐ **Preis wird nicht aufgeschlagen:** Produkt für 50 € (Bruttopreis) → Warenkorb-/Bestellsumme
  bleibt **50,00 €** (in inclusive ist die Steuer im Preis enthalten, wird **nicht** addiert).
  Gegenprobe zu exclusive, wo 50 € → 59,50 € wird.
- ☐ **Steuer herausgerechnet:** Die Steuerzeile zeigt **„Enthaltene MwSt. zu 19%"** mit dem
  **herausgerechneten** Betrag (50,00 € brutto → 7,98 € enthaltene MwSt., netto 42,02 €), nicht
  9,50 €. Bei 7%: 50,00 € → 3,27 € enthalten.
- ☐ **Modifier-Beitrag = 0:** In inclusive addiert der `CustomTaxModifier` **nichts** zur
  Ordersumme (`value()` liefert 0); die enthaltene MwSt. ist reine Anzeige. Order-Total =
  Summe der Bruttopreise.
- ☐ **Gemischte Sätze (7% & 19%):** je Satz eine eigene „Enthaltene MwSt."-Zeile mit korrekt
  herausgerechnetem Betrag; Summe der Bruttopreise = Bestell-Gesamtbetrag (Cent-genau).
- ☐ **Preishinweis Produkt/Seminar:** zeigt „inkl. MwSt." (via `$SiteConfig.TaxHint`) — bereits
  isoliert bestätigt (Kap. 3), hier im vollen inclusive-Durchlauf gegenprüfen.
- ☐ **Rechnungs-PDF:** Beleg weist die MwSt. als **enthalten** aus (Label „inkl./Enthaltene MwSt.",
  netto+Steuer stimmen mit der Anzeige überein), Gesamtbetrag = Bruttosumme.
- ☐ **Placement friert inclusive ein:** Nach Abschluss bleibt die Steuerzeile beim späteren
  Zurückstellen auf exclusive **unverändert** (Betrag *und* Label „Enthaltene MwSt."); `Order.TaxMode`
  = `inclusive`, `OrderItem.TaxAmount` = herausgerechneter Wert (Gegenstück zum eingefrorenen
  exclusive-Fall bei Order 7).

## 4. E-Mails & PDF-Anhänge

- ☐ **Beleg-Mail (receipt):** Kunde erhält Beleg-Mail; Rechnungs-PDF hängt an
  (`attach_invoice_to_receipt: true`).
- ☐ **Admin-Benachrichtigung:** geht an `AdminNotificationMail`; PDF hängt an
  (`attach_invoice_to_admin: true`).
- ☐ **Bestätigungs-Mail (confirmation):** **kein** PDF-Anhang (`attach_invoice_to_confirmation: false`).
- ☐ **Flag-Umschaltung:** Setzt man `attach_invoice_to_confirmation: true`, hängt das PDF an.
- ☐ **Master-Switch:** `ShopConfig.sendReceipt: false` unterdrückt alle Anhänge unabhängig der Flags.
- ☐ **Absender:** From-Adresse/Name kommen aus SiteConfig (mailconfig-Modul: `AdminEmail`/`AdminName`).
- ☐ **Queue:** Mails laufen als `SendOrderEmailJob` über QueuedJobs (bei `use_queued_jobs: true`).
- ☐ **Status-Mail:** enthält in der Signatur `ReceiptPhone` (statt des früheren Platzhalters „PhoneNumber").

## 5. PDF-Belege

- ☑ `/OrderReceipt/StreamReceipt/<OrderID>` liefert (als CMS-User/Admin) ein Rechnungs-PDF mit
  Logo, Header, Footer. (Route + Controller `OrderReceiptController` neu; früher 404, da keine Route.
  Logo wird jetzt binär als base64-Data-URI eingebettet statt per URL geladen → rendert sauber.)
- ☑ `/OrderReceipt/StreamDeliverySlip/<OrderID>` liefert ein **Lieferschein**-PDF: Titel
  „Lieferschein <Bestellnummer>" (Nummer aus der Order abgeleitet), **nur Artikel + Menge**
  (keine Preise/Summen/Zahlungen). Feature per YAML zuschaltbar (`Order.enable_delivery_slip`,
  Default aus; hier via `app/_config/deliveryslip.yml` aktiv) — bei deaktiviert: kein CMS-Button
  und Route liefert **404**. Template unter `shopextensions/templates/DeliverySlip.ss`,
  projektseitig via `themes/<theme>/templates/DeliverySlip.ss` überschreibbar.
- ☐ Account-Bereich: Kunde kann seinen Beleg über `receipt/<ID>` herunterladen.
- ☑ **CMS-Vorschau:** Im Order-Tab „Rechnungen" wird über dem „Rechnung herunterladen"-Button eine
  400px-PDF-Vorschau (erste Seite, via ImageMagick/`PreviewReceipt`) angezeigt — nur wenn eine
  Rechnungsnummer existiert. Zugriff wie beim Download (Besitzer/CMS-User). (Braucht `?flush=1`,
  damit die neue Action registriert wird.)
- ☐ **Zugriffsschutz (IDOR-Fix):** Als **anonym** → Login-Redirect; als **fremdes Member**
  (nicht Besitzer, kein CMS-Zugang) → **403**; als **Besitzer-Member** und als **CMS-User** →
  Download klappt. (Alte ungesicherte `StreamReceipt`-Actions am `PageController` entfernt.)

## 6. Checkout / Warenkorb (Regression – bestehende Funktionen)

- ☐ Checkout-Formular rendert über `ExtendedCheckoutForm` (CompositeFields aufgeflacht).
- ☐ Reine Digital-/Kursbestellung (kein Versand) blendet die Versandadresse aus.
- ☑ AJAX-Warenkorb (Mini-Cart + Item-Count) aktualisiert sich beim Hinzufügen/Entfernen.
  *(Live bestätigt. Header-Mini-Cart (`.header__ajaxcart` / `.header__itemsincart`, im `<% uncached %>`-
  Block), Vanilla-JS `themes/project/javascript/shop-cart.js` (kein jQuery), ein Refresh-Endpoint
  `/ajaxcart/updateCart`. Neues Tailwind-Produkt-Template `SilverShop/Page/Layout/Product.ss`.
  Siehe `docs/AJAX_CART_PRODUCT_TEMPLATE_PLAN.md`.)*
- ☑ Varianten-Preis aktualisiert per AJAX (`selectvariation`).
  *(Live bestätigt: Preis **und** Variantenbild wechseln beim Dropdown-Wechsel. `selectvariation` liefert
  JSON `{success, price, image?}`; JS löst die Variante clientseitig über das native `VariationOptions`-
  JSON auf (Set-Abgleich der Wert-IDs), Basis-URL wird auf abschließenden Slash normalisiert.)*
- ☐ Zahlung über Mollie/PayPal startet (Order-`getDescription()` wird gesetzt, kein Gateway-Fehler).

## 7. Aufgeräumtes / Entferntes

- ☑ Keine Newsletter-Reste: `Newsletter`-Spalte in `SilverShop_Order` entfernt, keine Fehler.
  (Code-Verifikation: silvershop/core `Order.php` hat keine `Newsletter`-Spalte, `OrderExtension`
  fügt keine hinzu → nach `dev/build` existiert die Spalte nicht.)
- ☑ Keine Referenzen auf `signupForNewsletter`/MailChimp mehr im Modul.
  (Grep über `shopextensions/**` nach `newsletter`/`mailchimp`/`signupfor` = 0 Treffer.
  Hinweis: Das projekteigene `NewsletterSignupForm` im `app/`-PageController ist ein separates,
  DSGVO-konformes Feature und gehört nicht zum Shop-Modul.)

---

## 8. Teil-B-Fixes (diese Runde)

- ☐ **Beleg-Mail nur einmal:** Nach `onPaid` wird die Beleg-Mail bei bereits gesetztem
  `ReceiptSent` **nicht erneut** verschickt (`|| true` entfernt).
- ☐ **Manuelle Zahlart:** Rechnungsnummer wird bei Manual-Zahlung vergeben. Der `'Cart '`-
  Leerzeichen-Bug war nur die halbe Miete — beim Placement existiert das Payment noch nicht, daher
  jetzt Vergabe über `PaymentExtension::onAuthorized` (siehe Kap. 2, „Manuelle Zahlart").
- ☐ **Versand-Titel:** Falls `CustomShippingModifier` aktiviert wird, zeigt die Versandzeile
  keinen 16%-Corona-Titel mehr, sondern konstant den `singular_name`.

## 9. Rechnungs-/Buchhaltungs-Export (optionales Feature)

> Voraussetzung: `receiptexport.yml.example` nach `app/_config/` kopiert, DATEV-Konten (mind.
> `account_by_rate`) testweise gesetzt, `dev/build flush=1` gelaufen.

### 9.1 Aktivierung & Zugriff
- ☐ Ohne die Opt-in-YAML: Route `/shopexport/csv` liefert **404** (Feature aus).
- ☐ `dev/build` legt/behält `SilverShop_Order.StornoDate`, `UsedPaymentOptionID`
  und die Tabelle `ShopExtensions_PaymentOption` an – **ohne Fehler**. (Die frühere numerische Spalte
  `StornoNumber` entfällt — die Storno-Belegnummer wird aus der Rechnungsnummer abgeleitet.)
- ☐ Als **Nicht-Admin/anonym** führt `/shopexport/csv/<Monat>/<Jahr>` zum **Login-Redirect**
  (kein Datenabfluss). Der oap-Bug (invertierte Permission-Logik) ist behoben.
- ☐ Als **Admin** startet der Download.

### 9.2 Steuer-Engine (`OrderTaxExtension.getTaxInformation()`)
- ☑ **Gemischte Sätze:** eine Bestellung/Warenkorb mit 7% **und** 19% liefert je Satz eine eigene
  `{net,tax,gross}`-Zeile (verifiziert: Cart mit 7%+19%). Der Export erzeugt daraus **eine H-Zeile
  je Satz** auf dem jeweiligen Erlöskonto (`account_by_rate[$rate]`); `gross==0` wird übersprungen.
- ☑ **Export liest gefrorene Werte:** für placed Orders liefert `getTaxInformation()` die beim
  Placement eingefrorene Aufschlüsselung (`Order.FrozenTaxInformation`). Verifiziert: Buchungszeilen
  RE10000/RE10002 bleiben bei Umstellung von `tax_mode` **unverändert** (Carts rechnen weiter live).
- ☐ **B2C (`tax_mode: inclusive`):** Bestellung mit gemischten Sätzen (7% & 19%). Pro Satz wird
  `net`, `tax`, `gross` korrekt herausgerechnet; Summe der `gross` = Bestell-Gesamtbetrag
  (Cent-genau, Reconciliation). *(Export-Gegenstück zum Checkout-Test in Kap. 3.1 — später.)*
- ☑ **B2B (`tax_mode: exclusive`):** gleiche Artikel, MwSt. wird **aufgeschlagen**; `net` = Basis,
  `gross = net + tax`. Verifiziert an Order 9 (`00009`, gemischt): 19% → net 50,00 / tax 9,50 /
  gross 59,50; 7% → net 50,00 / tax 3,50 / gross 53,50; Σ gross 113,00 = `Order.Total` (Cent-genau).
- ☐ **Order-Coupon (Cart):** Rabatt wird **anteilig** je Steuersatz verteilt (nach Umsatzanteil),
  nicht komplett einem Satz zugeschlagen.
- ☐ **Item-Coupon:** Rabatt landet exakt beim Steuersatz des rabattierten Artikels.
- ☐ **Gift-Card/Gutschein:** mindert den **Umsatz nicht** (Zahlungsmittel) – `gross`/`net`/`tax`
  bleiben wie ohne Gift-Card; nur die Zahlung ist geringer.
- ☐ **Versand:** anteilig auf die **Nicht-0%**-Sätze verteilt. Sonderfall reine 0%-Bestellung:
  Versand wird unter `high_tax_rate` (19%) gebucht.
- ☐ **Keine undefinierten Array-Keys / Warnings** bei Bestellungen ohne Coupon/ohne Versand
  (die oap-Bugs mit nicht initialisierten Keys sind behoben).

### 9.3 DATEV-CSV

> Im **exclusive**-Modus ohne Gutscheine geprüft und für korrekt befunden. **Noch offen:** Gegentest
> im **inclusive**-Modus und mit **verwendeten Gutscheinen/Coupons** (siehe Coupon-Punkte in 9.2).

- ☑ Datei öffnet in Excel/DATEV ohne Spaltenversatz (Header- und Datenzeilen haben **gleiche
  Spaltenzahl**; der doppelte `Leistungsdatum`-Header aus oap ist entfernt).
- ☑ Umlaute korrekt (Ausgabe **ISO-8859-1**).
- ☑ Pro Bestellung **eine `H`-Zeile je Steuersatz** mit `gross`; `Konto` = konfiguriertes
  Erlöskonto des Satzes, `Gegenkonto`/`KOST1` aus Config, `Belegfeld 1` = Rechnungsnummer.
- ☑ Sätze mit `gross == 0` erzeugen **keine** Zeile.
- ☑ EXTF-Kopfzeile enthält die konfigurierten Werte (Berater-Nr., Version, Zeitraum Monat/Jahr).
- ☐ Leere `account_by_rate`-Konten → Feld `Konto` bleibt leer (bewusst, kein Fehler).
  *(Edge-Case, mit gepflegten Konten nicht beobachtbar — separat prüfen.)*

### 9.4 Storno / Stornorechnung
- ☑ **Storno-Belegnummer:** `Order.enable_storno: true`: Wechsel auf `AdminCancelled` stempelt
  `StornoDate`; die Storno-Nummer ist die **Rechnungsnummer + `-S`** (Config `Order.storno_suffix`,
  kein eigener Nummernkreis). Verifiziert an Order 9: `RE10003` → **`RE10003-S`**, `IsStorno()=true`.
- ☑ **Stornorechnung-PDF (CMS):** Im Order-Tab „Rechnungen" erscheint bei stornierten Bestellungen der
  Button **„Stornorechnung … herunterladen"** → `/OrderReceipt/StreamStorno/<ID>` (Zugriff wie beim
  Rechnungs-Download: Besitzer-Member/CMS-User, sonst Login-Redirect/403). Das PDF trägt den Titel
  „Stornorechnung `RE10003-S`", verweist auf die Originalrechnung und zeigt **alle Beträge negativ**
  (Artikel, Zwischensumme, MwSt. je Satz, Gesamt). Verifiziert an Order 9: −€100,00 netto,
  −€9,50 (19%) / −€3,50 (7%), **−€113,00** gesamt (Reconciliation stimmt). Template `Storno.ss`
  (projektseitig via Theme überschreibbar).
- ☑ **CSV-Gegenbuchung:** Der Export erzeugt für die stornierte Bestellung zusätzlich zur `H`-Zeile
  eine **`S`-Gegenbuchung** je Steuersatz mit `Belegfeld 1 = RE10003-S` und `StornoDate`; die
  Umkehr erfolgt DATEV-konform über das Soll/Haben-Kennzeichen (H→S), gleicher Bruttobetrag netto
  = 0. **Kein Fatal** (der oap-Fehler durch undefiniertes `StornoNumber()` tritt nicht mehr auf).
- ☐ `enable_storno: false`: kein Storno-Button, `/OrderReceipt/StreamStorno/<ID>` liefert **404**,
  `AdminCancelled` erzeugt **keine** `S`-Zeile (und keinen Fehler). *(Regressions-Check offen.)*

### 9.5 Sammel-PDF

> Normalfall (exclusive) geprüft, sieht gut aus. **Noch offen:** inclusive-Modus + Gutschein-Fälle.

- ☑ `/shopexport/pdf/<Monat>/<Jahr>` liefert ein PDF mit allen passenden Belegen des Zeitraums
  (Download im Browser).
- ☐ CLI `sake shopexport/pdf` schreibt die Datei in `assets/<pdf_folder>/`.
- ☐ Leerer Zeitraum → PDF mit Hinweis „Keine Rechnungen…", kein Fatal.
  *(Edge-Case — separat prüfen.)*

### 9.6 Anzeige-Konsistenz (optionaler Bridge)
- ☐ `CustomTaxModifier.use_tax_engine_for_display: true`: Steuerzeilen in
  Bestellübersicht/Rechnung entsprechen **exakt** dem Export (gleiche Beträge je Satz).
- ☐ Flag `false` (Default): unveränderte, item-basierte Anzeige (Regression).

---

## 10. Zahlart-Vorauswahl im Checkout (optionales Feature)

> Voraussetzung: `paymenttiles.yml.example` nach `app/_config/` kopiert, `dev/build flush=1`,
> mind. 2 Zahlarten unter *Shop → Zahlarten* gepflegt (mit Mollie-Methoden-Code).

### 10.1 Aktivierung & Verwaltung
- ☐ Flag aus (Default): Checkout zeigt die **Standard-Gateway-Liste**, kein Verhalten geändert
  (Regression).
- ☐ Flag an, aber **keine** aktive `PaymentOption`: automatischer **Fallback** auf Standard-Liste
  (Kunde kann immer zahlen).
- ☐ *Shop → Zahlarten*-Reiter erscheint nur bei aktivem Feature; Kacheln per Drag & Drop
  sortierbar (`GridFieldOrderableRows`), Icon-Upload funktioniert.

### 10.2 Darstellung & Auswahl (JS-frei)
- ☐ Kacheln rendern als **echte Radios** (`name="PaymentMethod"`, Wert = PaymentOption-ID) mit
  Icon + Titel; ausgewählte Kachel ist optisch hervorgehoben.
- ☐ **Ohne JavaScript** (JS im Browser deaktiviert): Auswahl + Submit funktionieren, Auswahl geht
  nicht verloren – **kein** `.attr('checked')`-Hack, **keine** versteckte zweite Liste.
- ☐ Nur Kacheln mit **unterstütztem Gateway** und `Enabled` werden angezeigt.
- ☐ Ungültige/fehlende Auswahl → Validierungsfehler „Bitte wählen Sie eine Zahlart."

### 10.3 Persistenz & Durchreichung
- ☐ Nach Submit ist `Order.UsedPaymentOptionID` gesetzt; erneutes Aufrufen des Schrittes
  zeigt die Auswahl vorbelegt (`getData`).
- ☐ SilverShop-**Gateway** ist korrekt gesetzt (`Checkout::setPaymentMethod(PaymentGateway)`),
  Payment wird über das erwartete Gateway erzeugt.
- ☐ **Mollie-Testmode:** Bei gewählter Untermethode (z. B. `ideal`, `creditcard`) wird
  `paymentMethod` an Mollie übergeben und Mollies **eigener Auswahlbildschirm übersprungen**.
- ☐ Kein `$_SESSION['custompaymentmethod']`/`paymentType` mehr im Request-Fluss (alter
  Session-Seitenkanal entfernt).
- ☐ Gateway **ohne** Methoden-Code (z. B. `Manual`, `PayPal_Express`): funktioniert normal, es
  wird kein leerer `paymentMethod` gesendet.

---

## 11. Verzögerte Zahlungen (SEPA/async) & Auto-Storno (optionales Feature) — 🔴 PRIORITÄT 1 (vor Go-Live ZUERST prüfen)

> Voraussetzung: `delayedpayments.yml.example` nach `app/_config/` kopiert
> (`place_before_payment: true`, `GatewayInfo.Mollie.use_async_notification: true`,
> `CancelStaleUnpaidOrdersTask.cancel_after_days`), `dev/build flush=1`. Mollie im **Testmode**
> mit **öffentlich erreichbarer Notify-URL** (Produktion ok; lokal per Tunnel/ngrok), sonst kommt
> die SEPA-Bestätigung nie an. Zahlart-Kacheln (Kap. 10) aktiv, u. a. eine SEPA-Kachel (Mollie,
> Methoden-Code `directdebit`).

### 11.1 🔴 Braucht es `use_async_notification` überhaupt? (offene Frage — bewusst prüfen)
> **Kontext:** `use_async_notification` war bislang **nicht bewusst aktiv**, und trotzdem gab es
> **keine Probleme** mit SEPA-Zahlungen, die erst Tage später quittiert wurden. Unklar ist, ob das
> Flag wirklich nötig ist oder ob Mollie den späten Zahlungseingang auch ohne async-Notification
> sauber auf `Paid` bringt. Deshalb **beide Zustände gegentesten** und das Ergebnis hier festhalten,
> bevor das Flag dauerhaft gesetzt wird.

- ☐ **Ohne `use_async_notification` (bisheriger Ist-Zustand):** SEPA-Testzahlung durchführen, die
  erst verzögert quittiert. Prüfen, **wie** und **wann** die Order auf `Paid` geht (Webhook? erneuter
  Aufruf? gar nicht?) und ob dabei etwas hängen bleibt (`Unpaid` ohne Auflösung). Beobachtung notieren.
- ☐ **Mit `use_async_notification: true`:** gleiche SEPA-Testzahlung. Prüfen, ob der Übergang
  `pending → Unpaid → Paid` sauber über den Webhook läuft (siehe 11.2).
- ☐ **Entscheidung dokumentieren:** Bringt das Flag einen konkreten Unterschied (z. B. verlässlicheres
  `Paid`, sonst hängende `Unpaid`), oder ist der bisherige Zustand ausreichend? Ergebnis + Begründung
  hier eintragen; danach `delayedpayments.yml`/`payment.yml` entsprechend setzen **oder** das Flag
  bewusst wieder entfernen.

### 11.2 🔴 Async-Bestätigung (Unpaid → Paid)
- ☐ **SEPA (directdebit):** Rücksprung von Mollie mit `pending` → Order steht auf **`Unpaid`**
  (Kette `onAwaitingCaptured` → `placeOrder`). Später eintreffender Webhook → **`Paid`**
  (`onCaptured` → `completePayment`). Status in CMS/DB prüfen.
- ☐ **iDEAL / Kreditkarte, Flag AN:** Rücksprung → Order bleibt zunächst **`Unpaid`** (SuccessUrl),
  `Paid` kommt **erst per Webhook** (`onCaptured`) Sekunden später — **nicht** instant beim Rücksprung.
  (Nur mit Flag **AUS** capturt die Karte synchron im Rücksprung → sofort `Paid`. Siehe die
  Kernbefund-Analyse in `docs/SEPA_DOUBLE_PAYMENT_PLAN.md`.)

### 11.3 place_before_payment & Cleanup
- ☐ **Order existiert vor dem Redirect:** Beim Klick auf „Zahlen" ist die Order bereits als
  **`Unpaid`** angelegt, **bevor** zum Zahlungsanbieter weitergeleitet wird.
- ☐ **Abbruch bei Mollie:** Order bleibt **`Unpaid`** (nicht `Cart`).
- ☐ **CartCleanupTask** (`sake dev/tasks/CartCleanupTask` bzw. Cron) löscht **nur** echte Carts
  (`Status='Cart'`), **keine** `Unpaid`-Orders — die abgebrochene SEPA-Order bleibt erhalten.

### 11.4 Auto-Storno-Task
- ☐ **Alt genug → storniert:** `Unpaid`-Order mit künstlich altem `LastEdited` (> `cancel_after_days`,
  Default 14) und **ohne** Rechnungsnummer → `sake dev/tasks/cancel-stale-unpaid-orders` setzt sie
  auf **`MemberCancelled`** und verschickt die **Hinweis-Mail** an den Kunden.
- ☐ **Jünger/`Paid` unberührt:** Orders innerhalb der Karenzzeit sowie `Paid`-Orders werden **nicht**
  angefasst.
- ☐ **Selbstheilung:** Nach dem Cancel einen Webhook simulieren/eintreffen lassen → `onCaptured()`
  lädt die (noch existierende) Order und `completePayment()` setzt sie zurück auf **`Paid`**.

### 11.5 Wieder-Bezahlen im Konto (Re-Payment-Kacheln)
- ☐ **Kacheln sichtbar:** Konto → offene Order → „Bezahlen" zeigt dieselben **Kacheln** wie im
  Checkout (SEPA/iDEAL/Kreditkarte), **nicht** die rohe Gateway-Radioliste.
- ☐ **Durchreichung:** SEPA-Re-Payment setzt `Order.UsedPaymentOptionID` und reicht die
  Mollie-Untermethode durch (Auswahlschirm übersprungen).
- ☐ **Fallback:** Ohne aktive `PaymentOption` (Feature aus) erscheint weiterhin die Standard-Liste
  und die Zahlung funktioniert unverändert.

### 11.6 Rechnungsnummern (kein Verbrennen, keine Lücke)
- ☐ **Abgebrochene Mollie/Karten-Unpaid-Order** hat **keine** Rechnungsnummer; der Auto-Storno
  trifft nur nummernlose Orders.
- ☐ **B2B „auf Rechnung":** Bei einer Kachel mit **`InvoiceOnPlacement`** (bzw. Manual-Gateway) liegt
  die Rechnungsnummer **schon bei Platzierung** (Cart→Unpaid) vor → Kunde kann referenziert
  überweisen. Diese Order wird **nicht** auto-storniert (Mahnwesen); die Nummer bleibt auf der Order
  (keine Lücke).
- ☐ **Instant-Kacheln ohne `InvoiceOnPlacement`** (Kreditkarte/iDEAL/SEPA) bekommen die Nummer
  weiterhin **erst bei `Paid`**.

### 11.7 Mails & Storno-Feature
- ☐ **Keine Bestätigungsmail bei Platzierung:** Das Platzieren (Unpaid) verschickt **keine**
  Bestellbestätigung (`OrderProcessor.send_confirmation: false`); die Beleg-Mail (Receipt) geht erst
  bei `Paid`.
- ☐ **Storno-Feature unberührt:** `AdminCancelled` erzeugt weiterhin die Stornorechnung; der
  Auto-Cancel (`MemberCancelled`) löst **kein** Storno aus.

### 11.8 🔴 Doppelzahlung / Repay bei laufender Zahlung
> **Opt-in:** nur aktiv mit `Order.manage_pending_payments: true` (Default false → dann verhält sich
> alles wie im Standard-SilverShop und dieser Abschnitt entfällt). Details & Begründung:
> `docs/SEPA_DOUBLE_PAYMENT_PLAN.md`. Weitere Config: `Order.pending_payment_grace_mins` (Default 5),
> `Payment.auto_refund_duplicate` (Default false), `Payment.duplicate_alert_email`.

- ☐ **Feature aus (Regression):** ohne `manage_pending_payments` erscheint das Repay-Formular wie
  bisher, kein Warte-Screen, keine Duplikat-Erkennung.
- ☐ **Gate greift:** Zahlung starten (Redirect zu Mollie), zurück ins Konto → offene Order: das
  Repay-Formular ist **verborgen** und ein „Zahlung wird verarbeitet…"-Hinweis erscheint
  (`HasPendingPayments`), solange die pending-Zahlung **jünger als 5 Min** ist.
- ☐ **Gate gibt frei (kein Dauer-Lockout):** dieselbe Order nach Ablauf des Grace-Fensters (bzw.
  `LastEdited`/`Created` künstlich alt) → Repay-Formular ist **wieder da** (legitimer
  Methodenwechsel SEPA→Karte möglich).
- ☐ **Idempotenz:** Repay abschicken, während eine frische pending-Zahlung existiert → Aktion bricht
  mit Hinweis ab, es entsteht **keine** zweite Zahlung.
- ☐ **Reconciliation (der SEPA-Fix):** Order per Karte bezahlen (→ `Paid`), dann eine zweite,
  bereits gestartete SEPA per Webhook `paid` simulieren → `PaymentExtension::onCaptured` erkennt das
  Duplikat: Order-Flag **`HasDuplicatePayment`** gesetzt, CMS-Warnung im Order-Tab „Main" sichtbar,
  **Admin-Mail** verschickt. Mit `auto_refund_duplicate: true` zusätzlich: Duplikat wird via omnipay
  erstattet (sofern Gateway Refund unterstützt).
- ☐ **Kein Fehlalarm:** eine normale Einzelzahlung setzt `HasDuplicatePayment` **nicht**.

---

## Offene Umgebungs-Punkte vor dem Testlauf
- ☐ Schreibrechte `public/assets` gesetzt (für `dev/build` / ErrorPage-Generierung).
- ☐ QueuedJobs-Worker läuft (sonst Mails testweise mit `use_queued_jobs: false` senden).
- ☐ Für Feature-Tests (Kap. 9/10/11): jeweilige `*.yml.example` nach `app/_config/` kopiert und
  `dev/build flush=1` ausgeführt.
- ☐ Mollie im **Testmode** konfiguriert (für Kap. 10.3 und 11).
- ☐ Für Kap. 11: Mollie-**Notify-URL öffentlich erreichbar** (Tunnel/ngrok lokal), sonst kommt die
  SEPA-Webhook-Bestätigung nie an.
