# shopextensions – Anleitung / Verwendung

Deutsch-Markt-Erweiterungen für `silvershop/core` (SilverStripe 6): Rechnungen & PDFs,
Nummernkreise, bedienbarerer Checkout, robuste Payment-Anbindung (Mollie/PayPal/Stripe),
Steuer-/Versandlogik und (optional gequeuete) Bestell-Mails mit Rechnungs-Anhang.

Inhalt:
1. [Voraussetzungen & Installation](#1-voraussetzungen--installation)
2. [Pflicht-Setup im Projekt](#2-pflicht-setup-im-projekt)
3. [Config-YAML-Variablen (Referenz)](#3-config-yaml-variablen-referenz)
4. [SiteConfig-Einstellungen (CMS)](#4-siteconfig-einstellungen-cms)
5. [Abhängigkeiten aus anderen Modulen](#5-abhängigkeiten-aus-anderen-modulen)
6. [Stolperfallen](#6-stolperfallen)
7. [Optional: Rechnungs-/Buchhaltungs-Export (DATEV-CSV & Sammel-PDF)](#7-optional-rechnungs-buchhaltungs-export-datev-csv--sammel-pdf)
8. [Optional: Zahlart-Vorauswahl im Checkout (Payment-Tiles)](#8-optional-zahlart-vorauswahl-im-checkout-payment-tiles)

---

## 1. Voraussetzungen & Installation

**Composer-Abhängigkeiten** (werden mitgezogen):
- `silvershop/core: ^6`
- `dompdf/dompdf: ^3` (PDF-Erzeugung für Rechnung/Lieferschein)
- `symbiote/silverstripe-queuedjobs: ^6` (asynchroner Mailversand)

**Installation:**
```bash
composer require innomedia/shopextensions
```

**Address-Feld-Patch (empfohlen):** Damit die Adressfelder in den Standard-Templates korrekt
angezeigt werden, den mitgelieferten `silvershop.patch` anwenden (siehe `README.md`):
```json
"require": { "cweagans/composer-patches": "^2" },
"extra": {
  "patches": {
    "silvershop/core": {
      "Made Address Fields not in composite field": "silvershop.patch"
    }
  }
}
```

Nach Installation/Änderungen immer:
```bash
vendor/bin/sake dev/build flush=1
```

---

## 2. Pflicht-Setup im Projekt

Das Modul registriert seine Extensions selbst, aber **einige Dinge muss das Projekt setzen**
(typischerweise in `app/_config/shop.yml`):

### 2.1 Steuer-Modifier aktivieren (PFLICHT)
Das Modul registriert nur die *Extension*; der Tax-Modifier muss als Order-Modifier eingetragen
werden, sonst wird keine MwSt ausgewiesen:
```yaml
SilverShop\Model\Order:
  modifiers:
    - ShopExtensions\CustomTaxModifier
    # optional zusätzlich:
    # - ShopExtensions\CustomShippingModifier
```

### 2.2 Doppelte Mails vermeiden (PFLICHT/EMPFOHLEN)
Das Modul verschickt Beleg- und Admin-Mail selbst (in `OrderExtension::onPaid` /
`onStatusChange`). silvershops eigene automatischen Mails daher deaktivieren:
```yaml
SilverShop\Checkout\OrderProcessor:
  send_confirmation: false        # sonst zusätzliche Bestätigungsmail
  send_admin_notification: false  # sonst doppelte Admin-Kopie
```

### 2.3 Steuer-Modus festlegen (PFLICHT)
```yaml
ShopExtensions\CustomTaxModifier:
  tax_mode: 'exclusive'   # 'exclusive' = zzgl. MwSt · 'inclusive' = inkl. MwSt
  per_product_tax: true   # true = Satz pro Produkt · false = ein globaler Satz
  default_tax_rate: 19
```

### 2.4 Locale / Währung (EMPFOHLEN)
```yaml
SilverStripe\i18n\i18n:
  default_locale: de_DE
```

Danach `dev/build flush=1`.

---

## 3. Config-YAML-Variablen (Referenz)

Alle Defaults stehen in `_config/shopextensions.yml`. Übersteuern im Projekt über eine eigene
YAML mit `After: '#shopextensions'`.

### `ShopConfig`
| Variable | Default | Bedeutung |
|---|---|---|
| `sendReceipt` | `true` | **Master-Schalter** für den Rechnungs-PDF-Anhang. Steht er auf `false`, hängt an **keiner** Mail ein PDF – unabhängig von den `attach_invoice_to_*`-Flags. |

### `ShopExtensions\CustomOrderEmailNotifier`
| Variable | Default | Bedeutung |
|---|---|---|
| `use_queued_jobs` | `true` | `true`: Mails laufen über einen QueuedJob (blockiert den Checkout nicht). `false`: direkter Versand (Debug). |
| `send_statuschanges` | `true` | `false`: Status-Änderungs-Mails an Kunden werden unterdrückt. |
| `attach_invoice_to_receipt` | `true` | Rechnungs-PDF an die **Beleg-Mail** (Kunde) anhängen. |
| `attach_invoice_to_admin` | `true` | Rechnungs-PDF an die **Admin-Benachrichtigung** anhängen. |
| `attach_invoice_to_confirmation` | `false` | Rechnungs-PDF an die **Bestätigungs-Mail** anhängen. |

> Anhang-Logik = `sendReceipt` **UND** das jeweilige `attach_invoice_to_*`-Flag.

### `ShopExtensions\CustomTaxModifier`
| Variable | Default | Bedeutung |
|---|---|---|
| `tax_mode` | `inclusive` | `inclusive` = Preise enthalten MwSt („Enthaltene MwSt. zu X%"), `exclusive` = MwSt kommt oben drauf („zzgl. MwSt. zu X%"). |
| `per_product_tax` | `false` | `true`: jedes Produkt hat einen eigenen Satz (Feld „Steuersatz"). `false`: alle Produkte nutzen `default_tax_rate`. |
| `default_tax_rate` | `19` | Standard-/Fallback-Satz in Prozent. |
| `country_rates` | `[]` | Optionale länderspezifische Sätze. |

### `ShopExtensions\ProductExtension`
| Variable | Default | Bedeutung |
|---|---|---|
| `default_tax_rate` | `19` | Vorbelegung des Steuersatzes bei neuen Produkten. |
| `available_tax_rates` | Liste 0/7/19 | Auswahlmöglichkeiten des „Steuersatz"-Dropdowns. **Muss eine Liste von `{rate, label}`-Maps sein** (siehe Stolperfallen). |
| `requires_shipping` | `true` | (auf `SilverShop\Page\Product`) Ob physischer Versand nötig ist. Für Digital-/Kursklassen auf `false` setzen. |

### Weitere
| Variable | Default | Bedeutung |
|---|---|---|
| `SilverStripe\ORM\FieldType\DBCurrency.currency_symbol` | `€` | Währungssymbol. |
| `ShopExtensions\SiteConfigExtension.enabled_fields` | alle | Welche SiteConfig-Felder im CMS erscheinen (siehe Abschnitt 4). |
| `SilverShop\Model\Order.issue_invoice_for_zero_total` | `false` | Ob 0,00 €-Bestellungen eine Rechnung bekommen (Nummer + Beleg-PDF + Beleg-Mail). Default `false` = kostenlose Bestellungen erhalten **keine** Rechnung und verbrauchen keine Nummer. Auf `true` setzen, um 0 €-Bestellungen wie bezahlte zu behandeln. |
| `SilverShop\Model\Order.enable_delivery_slip` | `false` | Lieferschein-Feature (CMS-Button + Route `/OrderReceipt/StreamDeliverySlip/<ID>`). Default aus (opt-in) — reine Digital-/Seminar-Shops brauchen es nicht. Aktivieren via `deliveryslip.yml.example`. Der Lieferschein zeigt nur Artikel + Menge; Template `DeliverySlip.ss` ist projektseitig überschreibbar. |

**Injector-Overrides** (setzt das Modul automatisch): `CheckoutComponentConfig` →
`CustomCheckoutComponentConfig`, `OrderEmailNotifier` → `CustomOrderEmailNotifier`.

---

## 4. SiteConfig-Einstellungen (CMS)

Zu finden unter **Einstellungen → Shop** (silvershop-Reiter), Untertabs *Rechnung & Belege*,
*Benachrichtigungen*, *Hinweise & Texte*. Sichtbarkeit steuerbar über `enabled_fields`.

| Feld | Tab | Pflicht? | Wirkung / wo sichtbar |
|---|---|---|---|
| `ReceiptFooter` | Rechnung & Belege | **Pflicht¹** | Fußzeile auf Rechnung/Lieferschein – Bankverbindung, USt-IdNr., Handelsregister, ggf. Kleinunternehmer-Hinweis. Rechtlich relevant. |
| `ReceiptLogo` | Rechnung & Belege | Optional | Logo oben auf den PDF-Belegen. |
| `ReceiptHeader` | Rechnung & Belege | Optional | Einzeilige Absenderangabe über der Empfängeradresse. |
| `ReceiptPhone` | Rechnung & Belege | Optional | Telefon in der Signatur der Status-Mails (und optional auf Belegen). |
| `InvoiceNumberPrefix` | Rechnung & Belege | Optional | Präfix vor der Rechnungsnummer. Leer = `RE`. |
| `InvoiceNumberStart` | Rechnung & Belege | Optional | Startwert des Nummernkreises. Leer = `200000`. Nur für die 1. Rechnung, danach fortlaufend. |
| `AdminNotificationMail` | Benachrichtigungen | Empfohlen | Empfänger der Bestell-Benachrichtigung (inkl. PDF). Leer = Fallback auf System-Admin-Adresse. Mehrere per Komma. |
| `CostHint` | Hinweise & Texte | Optional | Zusatztext bei Produktpreisen (z. B. „inkl. MwSt., zzgl. Versand"). |
| `HintPayment` | Hinweise & Texte | Optional | Hinweistext im Checkout **über der Auswahl der Zahlungsmittel**. |
| `HintAfterPayment` | Hinweise & Texte | Optional | Hinweistext nach dem Kauf (Danke-Seite/Mail). |

¹ Technisch optional (leer = keine Fußzeile), für rechtskonforme Rechnungen aber faktisch Pflicht.

**Felder ausblenden** (z. B. wenn ein Projekt keine Preis-Hinweise braucht):
```yaml
ShopExtensions\SiteConfigExtension:
  enabled_fields:
    - ReceiptLogo
    - ReceiptFooter
    - AdminNotificationMail
    - InvoiceNumberPrefix
    - InvoiceNumberStart
    # nicht gelistete Felder erscheinen nicht im CMS
# leeres Array = alle ausblenden:
#   enabled_fields: []
```

> Als Vorlage liegt `shopextensions/_config/siteconfig.yml.example` bei – nach
> `app/_config/siteconfig.yml` kopieren, auskommentieren/anpassen. Ohne diese Config sind
> **alle** Felder sichtbar (Default).

---

## 5. Abhängigkeiten aus anderen Modulen

Diese Werte liegen **nicht** in shopextensions, werden aber genutzt:

| Wert | Quelle | Pflicht? | Bedeutung |
|---|---|---|---|
| `AdminEmail` / `AdminName` | `moritz-sauer-13/silverstripe-mailconfig` (SiteConfig) | **Pflicht** | Absenderadresse/-name aller Bestell-Mails. Fallback: `Email.admin_email`. |
| `TermsPage` | silvershop `ShopConfigExtension` (SiteConfig → Shop → Main) | Empfohlen | AGB-Seite; wird als `serviceUrl` an den Payment-Provider übergeben. |
| Mail-Transport (SMTP) | mailconfig / `Email`-Config | **Pflicht** | Ohne konfigurierten Transport werden keine Mails versendet. |
| QueuedJobs-Worker | `symbiote/silverstripe-queuedjobs` | Pflicht bei `use_queued_jobs: true` | Cron/Worker muss laufen, sonst bleiben Mails in der Queue. |

---

## 6. Stolperfallen

- **`available_tax_rates` muss eine Liste von `{rate, label}`-Maps sein**, kein assoziatives
  Array `rate: label`. Ein Array mit ausschließlich ganzzahligen Keys (0/7/19) wird von
  SilverStripes Config-Merge als indizierte Liste behandelt und zu 0/1/2 **reindexiert** – das
  Dropdown speichert dann den Index statt des Steuersatzes.
  ```yaml
  available_tax_rates:
    - { rate: 0,  label: '0% (steuerfrei)' }
    - { rate: 7,  label: '7% (ermäßigt)' }
    - { rate: 19, label: '19% (regulär)' }
  ```
- **Doppelte Mails:** silvershops `OrderProcessor.send_confirmation` /
  `send_admin_notification` deaktivieren (Abschnitt 2.2).
- **PDF-Download/-Debug:** Belege werden über die **zugriffsgeschützte** Route
  `/OrderReceipt/StreamReceipt/<OrderID>` bzw. `/OrderReceipt/StreamDeliverySlip/<OrderID>`
  ausgeliefert (Controller `ShopExtensions\Controllers\OrderReceiptController`). Zugriff nur für
  das **besitzende Member** oder einen **CMS-User**; sonst Login-Redirect bzw. 403. Zum Isolieren
  von Render-Fehlern die Bestellung in der DB auf `Paid` setzen und als Admin aufrufen. (Früher
  waren dies ungesicherte Actions am `PageController` ohne Rechteprüfung – IDOR, jetzt behoben.)
- **Order-Status-Enum:** Wenn das Projekt eigene Status nutzt (z. B. `Refunded`), müssen diese im
  `SilverShop\Model\Order.db.Status`-Enum und ggf. in `log_status` stehen.
- **Nummernkreis:** Bereits vergebene `InvoiceNumber` werden nie überschrieben; `InvoiceNumberStart`
  greift nur, solange noch keine Rechnung existiert.
- **Steuer bei platzierten Bestellungen ist eingefroren (idiomatisch):** Der `CustomTaxModifier`
  rechnet die Steuer nur live, solange die Order ein Warenkorb ist — `value()` liefert den Beitrag
  zum Order-Total, SilverShops Basis-`modify()` friert ihn in `Amount` ein. Beim Placement wird die
  tatsächlich berechnete Steuer **pro Zeile** auf die OrderItems geschrieben (`TaxRate`/`TaxAmount`,
  analog zu `CalculatedTotal`) und der Modus auf die Order (`TaxMode`). Für placed Orders liest die
  Anzeige diese eingefrorenen Werte — eine spätere Änderung von `tax_mode`/Sätzen ändert **placed
  Orders nicht mehr** (Betrag *und* Label). Ist die Steuer-Engine aktiv, wird deren Aufschlüsselung
  pro Satz beim Einfrieren anteilig auf die Items verteilt (Coupon/Versand bleiben deckungsgleich).
  Ist die Engine aktiv, wird zusätzlich die komplette Aufschlüsselung (`Order.FrozenTaxInformation`)
  eingefroren: `getTaxInformation()` liefert für placed Orders diesen Wert, sodass **auch der
  DATEV-/PDF-Export** stabil bleibt (inkl. Coupon/Versand-Verteilung). `dev/build` legt
  `OrderItem.TaxRate/TaxAmount`, `Order.TaxMode` und `Order.FrozenTaxInformation` an; Bestandsorders
  einmalig per `sake dev/tasks/ShopExtensions-Tasks-FreezeOrderTaxTask` nachträglich einfrieren.

---

## 7. Optional: Rechnungs-/Buchhaltungs-Export (DATEV-CSV & Sammel-PDF)

Zwei zuschaltbare Bausteine für die Buchhaltung:

- **`ShopExtensions\OrderTaxExtension`** – eine Steuer-Engine auf der Order. Sie liefert
  `getTaxInformation()`: pro Steuersatz `{gross, net, tax}`, inkl. korrekter Verteilung von
  Coupons (Item- **und** Order-Coupons anteilig), Gutscheinen/Gift-Cards (zählen **nicht** als
  Erlösminderung – sie sind Zahlungsmittel) und anteiligem Versand. B2C (`inclusive`, MwSt.
  herausgerechnet) und B2B (`exclusive`, MwSt. aufgeschlagen) über
  `CustomTaxModifier.tax_mode`.
- **`ShopExtensions\Controllers\ReceiptExportController`** – Stapel-Export aller platzierten
  Bestellungen eines Zeitraums als **DATEV-EXTF-CSV** (`/csv`) und als **Sammel-PDF** (`/pdf`).

**Beides ist standardmäßig AUS.** Aktivierung durch Kopieren von
`_config/receiptexport.yml.example` nach z. B. `app/_config/receiptexport.yml` und Anpassen.

### 7.1 Aktivierung (Kurzfassung)
```yaml
# app/_config/receiptexport.yml  (aus receiptexport.yml.example)
SilverShop\Model\Order:
  extensions:
    - ShopExtensions\OrderTaxExtension
  enable_storno: true            # Stornorechnung + Stornobuchung (Nr. = Rechnungsnr. + "-S")

SilverStripe\Control\Director:
  rules:
    'shopexport//$Action/$ID/$OtherID': 'ShopExtensions\Controllers\ReceiptExportController'

ShopExtensions\Controllers\ReceiptExportController:
  enabled: true
  datev:
    berater: ''                  # von der Kanzlei
    contra_account: ''           # Gegenkonto
    cost_centre_1: ''            # KOST1
    account_by_rate:             # Erlöskonten je Satz – MIT STEUERBÜRO ABSTIMMEN!
      0: ''
      7: ''
      19: ''

Silverstripe\SiteConfig\SiteConfig:
  extensions:
    - ShopExtensions\SiteConfigExportExtension
ShopExtensions\SiteConfigExportExtension:
  enabled: true
```
Danach `vendor/bin/sake dev/build flush=1`.

### 7.2 Bedienung
- **CMS:** *Einstellungen → Shop → Rechnungsexport*: Monat/Jahr wählen, Button „DATEV-CSV" bzw.
  „Sammel-PDF". Zugriff nur für Admins.
- **URL:** `/shopexport/csv/<Monat>/<Jahr>` bzw. `/shopexport/pdf/<Monat>/<Jahr>`.
- **CLI (Cron):** `vendor/bin/sake shopexport/csv` (exportiert standardmäßig den Vormonat).

### 7.3 Config-Referenz
| Variable | Default | Bedeutung |
|---|---|---|
| `Order.enable_storno` | `false` | Beim Wechsel auf `AdminCancelled` wird ein Storno-Datum gestempelt. Dann steht im Order-Tab „Rechnungen" der Button **„Stornorechnung … herunterladen"** (Route `/OrderReceipt/StreamStorno/<ID>`, Template `Storno.ss`, projektseitig überschreibbar) und der CSV-Export erzeugt die Stornobuchung (`S`-Zeile). |
| `Order.storno_suffix` | `-S` | Suffix für die Storno-Belegnummer: die Stornorechnung übernimmt die **Rechnungsnummer + Suffix** (z. B. `RE10003` → `RE10003-S`) – kein eigener Nummernkreis. |
| `OrderTaxExtension.high_tax_rate` | `19` | Satz, unter dem Versand bei reinen 0%-Bestellungen gebucht wird. |
| `CustomTaxModifier.use_tax_engine_for_display` | `false` | Wenn `true` **und** Engine aktiv: der Modifier übernimmt die **gesamte** Steuerberechnung aus der Engine (Coupon/Giftcard/Versand je Satz) – für **Anzeige und tatsächlich berechnete Steuer** (`modify()`/`Amount`). Damit ist **berechnet = angezeigt = exportiert** identisch. Voraussetzung: Modifier läuft **nach** Versand/Rabatt (in `Order.modifiers` zuletzt). Für einfache Bestellungen identisch zur Standardrechnung. |
| `ReceiptExportController.enabled` | `false` | Master-Schalter; sonst 404. |
| `ReceiptExportController.pdf_folder` | `Rechnungsexport` | Assets-Ordner für das PDF im CLI-Modus (Web = direkter Download). |
| `ReceiptExportController.datev.*` | leer/Beispiel | EXTF-Kennzeichen/Version, Berater-Nr., Kontolänge, Gegenkonto, KOST1, Erlöskonten je Satz. **Konten projektspezifisch – vom Steuerbüro bestätigen lassen.** |
| `SiteConfigExportExtension.enabled` | `false` | Zeigt das Export-Panel im Shop-Reiter. |
| `SiteConfigExportExtension.route_segment` | `shopexport` | Muss zum registrierten Router-Segment passen. |

**Hinweise / Fallstricke:**
- Der CSV-Export benötigt die Engine (`getTaxInformation()`); Bestellungen ohne die Engine
  werden defensiv übersprungen.
- Die CSV wird als **ISO-8859-1** ausgegeben (DATEV-Anforderung), intern aus UTF-8 konvertiert.
- Die hinterlegten DATEV-**Konten sind nur Beispiele**. Ohne korrekte Konten wird das Feld `Konto`
  leer ausgegeben – bewusst, damit niemand auf fremde Konten bucht.
- Große Zeiträume beim PDF sind speicherintensiv (Controller setzt `memory_limit=1536M`,
  `max_execution_time=3600`); ggf. als QueuedJob fahren.

---

## 8. Optional: Zahlart-Vorauswahl im Checkout (Payment-Tiles)

Der Kunde wählt die konkrete Zahlart (z. B. Mollie-Untermethode wie iDEAL, Kreditkarte, PayPal,
SEPA) direkt im Checkout als **Kachel** – die Auswahl wird an den Zahlungsanbieter durchgereicht,
sodass dessen eigener Auswahlbildschirm übersprungen wird.

Umsetzung als **echter Checkout-Component** (`ShopExtensions\Checkout\PaymentMethodComponent`):
die Kacheln **sind** das Radio-Formularfeld (`OptionsetField` namens `PaymentMethod`). Die Auswahl
reist mit dem Formular-Submit, wird auf der Order gespeichert (`Order.UsedPaymentOptionID`) und
funktioniert **ohne JavaScript** – kein `.attr('checked')`-Hack, keine versteckte zweite Liste,
kein AJAX-/Session-Seitenkanal.

**Standardmäßig AUS.** Aktivierung durch Kopieren von `_config/paymenttiles.yml.example`.

### 8.1 Aktivierung
```yaml
# app/_config/paymenttiles.yml  (aus paymenttiles.yml.example)
ShopExtensions\CustomCheckoutComponentConfig:
  use_payment_tiles: true

Silverstripe\SiteConfig\SiteConfig:
  extensions:
    - ShopExtensions\SiteConfigPaymentTilesExtension
ShopExtensions\SiteConfigPaymentTilesExtension:
  enabled: true
```
Danach `vendor/bin/sake dev/build flush=1`.

### 8.2 Zahlarten pflegen
*Einstellungen → Shop → Zahlarten*: pro Kachel **Titel**, **Gateway** (z. B. `Mollie`),
optionaler **Methoden-Code** (Mollie-`method`, z. B. `ideal`, `creditcard`, `paypal`,
`directdebit`), **Icon** und **Sortierung** (Drag & Drop). Für Gateways ohne Untermethode
(`PayPal_Express`, `Manual`) den Methoden-Code leer lassen.

### 8.3 Wie die Durchreichung funktioniert
`ExtendedPurchaseService::onBeforePurchase` liest die gewählte Option von der Order und setzt
`$data['paymentMethod']` – den **korrekten** omnipay-mollie-Key (→ Mollie-`method`). (Der frühere
Code setzte fälschlich `paymentType` aus `$_SESSION` und reichte damit nichts durch.)

### 8.4 Config-Referenz
| Variable | Default | Bedeutung |
|---|---|---|
| `CustomCheckoutComponentConfig.use_payment_tiles` | `false` | Tile-Component statt Standard-Gateway-Radioliste. |
| `SiteConfigPaymentTilesExtension.enabled` | `false` | Zeigt den „Zahlarten"-Reiter (GridField) im Shop-Reiter. |
| `PaymentOption` (DataObject) | — | `PaymentGateway`, `PaymentMethod`, `Title`, `Sort`, `Enabled`, `Image`. |

**Fallback/Robustheit:** Nur Kacheln mit **unterstütztem** Gateway
(`GatewayInfo::getSupportedGateways()`) und `Enabled` werden angezeigt. Ist keine aktive Zahlart
gepflegt oder das Flag aus, bleibt automatisch der Standard-Checkout aktiv. Verfügbarkeitsregeln
je Bestellung sind über den Erweiterungspunkt `PaymentOption::canUse()` nachrüstbar.
