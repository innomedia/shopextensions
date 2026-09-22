# Änderungen

Format: neueste Version zuerst. „Migration" = was ein Projekt beim Update tun muss.

## 6.1.2 (2026-09-22)

### Abgelehnter Zahlungsstart bleibt ein Fehler, auch mit asynchronen Benachrichtigungen (Bugfix, standardmäßig an)

Mit `GatewayInfo.<Gateway>.use_async_notification: true` (nötig für SEPA/Überweisung) markiert
silverstripe-omnipay eine Zahlungsanfrage, die der Anbieter **ablehnt**, nie als Fehler
(`PaymentService::wrapOmnipayResponse()` setzt `SERVICE_PENDING`). Lehnt Mollie ab (HTTP 422:
ungültige Webhook-URL, Betrag unter dem Minimum der Methode, Methode nicht aktiv, …), stand die
Zahlung auf „PendingPurchase" und die Kundin landete auf der **Erfolgsseite** – ohne dass beim
Anbieter eine Zahlung existierte oder je ein Webhook käme.

Neu: `ShopExtensions\Payment\AsyncPurchaseErrorExtension` am `PurchaseService`. Bei Mollie kommt eine
angelegte Zahlung immer als Weiterleitung auf die Bezahlseite zurück (auch Überweisung/SEPA); „weder
erfolgreich noch Weiterleitung" beim **Start** ist also eine Ablehnung und wird wieder zum Fehler:
omnipay schreibt eine `PurchaseError`-Meldung, die Zahlung bleibt `Created`, und die Kasse behandelt
es wie jede gescheiterte Zahlung (6.1.1: Bestellseite + `$CheckoutPaymentNotice`). Gilt nur für die
Gateways in `AsyncPurchaseErrorExtension.gateways` (Vorgabe `[Mollie]`).

### `ErrorSummary.ss`: kein leerer Zahlungshinweis mehr

`<% with $CheckoutPaymentNotice %>` rendert in SS6 auch bei `null` – ein leerer Hinweis stand in jeder
Kasse. Jetzt mit `<% if %>` davor. Projekte mit eigener Vorlage prüfen dieselbe Stelle.

Migration: `sake db:build flush=1` (neue Extension). Sonst nichts.

## 6.1.1 (2026-09-22)

### Länderauswahl alphabetisch nach deutschem Namen (Bugfix, wirkt sofort)

`AddressExtension::updateCountryField()` hat die Ländernamen übersetzt, aber die Reihenfolge von
silvershop behalten – die ist nach dem **englischen** Namen sortiert („Österreich" zwischen
„Australien" und „Bahamas"). Jetzt per `Collator('de_DE')` neu sortiert.

Migration: keine.

### Gescheiterter Zahlungsstart führt nicht mehr in die leere Kasse (Bugfix, standardmäßig an)

Mit `Order.place_before_payment: true` (nötig für SEPA/verzögerte Zahlungen) ist die Bestellung
schon „Unpaid", wenn der Zahlungsstart scheitert (Fehler beim Anbieter, abgelehnte Webhook-URL, …).
silvershop leitete dann zurück in die Kasse, deren Warenkorb inzwischen leer ist: Kundinnen sahen
„Es befinden sich keine Produkte im Warenkorb" und nie die Fehlermeldung.

Neu: `CheckoutPageControllerExtension::processPaymentResponse()` (Hook von `PaymentForm`) leitet eine
platzierte Bestellung auf `<kasse>/order/<ID>` um; dort bietet `OrderActionsForm` das erneute Bezahlen
an, und `$CheckoutPaymentNotice` erklärt den Fall auch auf dieser Seite (eigener Text
`PaymentFailedPlacedText`). Nicht platzierte Bestellungen verhalten sich wie bisher.

Migration: `$CheckoutPaymentNotice` ins Template der Bestellseite (`CheckoutPage_order.ss`) aufnehmen.
Abschalten mit `CheckoutPageControllerExtension.failed_payment_to_order: false`.

## 6.1.0 (2026-09-22)

### Betreiber-Mail „Neue Bestellung" nur noch einmal (Bugfix, wirkt nur mit dem Vorkasse-Opt-in)

Mit `CustomOrderEmailNotifier.send_confirmation_for_manual_payment: true` (Vorkasse/Rechnung: Kunden-
und Betreibermail schon bei der Bestellung) kam dieselbe Mail „Neue Bestellung Nr. X" ein zweites Mal,
sobald jemand die Bestellung im CMS auf „Bezahlt" setzte — im Postfach nicht von der ersten zu
unterscheiden.

Neu: `Order.AdminNotificationSent` (Datetime) wird in `CustomOrderEmailNotifier::sendAdminNotification()`
gesetzt; der Paid-Zweig in `OrderExtension::onStatusChange()` verschickt die Benachrichtigung nur noch,
wenn sie für diese Bestellung noch nicht verschickt wurde. **Ohne das Opt-in ändert sich nichts**: dort
ist die Paid-Mail weiterhin die erste und einzige Betreibermail.

Migration: `vendor/bin/sake db:build flush=1` (neues Feld). Sonst nichts.

### Beleg zeigt die gekaufte Artikelnummer und den richtigen Zahlungsstatus (Bugfix, standardmäßig an)

- Rechnung, Stornorechnung und Lieferschein haben `$Product(true).InternalItemID` ausgegeben, also bei
  Varianten die Nummer des **Produkts** („GHEE") statt der Varianten-SKU („GHEE-ROSA-500") — die
  HTML-Fassung derselben Rechnung zeigte die Variante. Neu: `OrderItemExtension::ArticleNumber()`
  nimmt die Nummer des gekauften Buyables (versioniert, also wie beim Kauf) und fällt auf das Produkt
  zurück; die drei Vorlagen nutzen nur noch `$ArticleNumber`.
- Eine per Hand auf „Bezahlt" gesetzte Vorkasse-Bestellung ließ ihre Manual-Zahlung auf „Autorisiert"
  stehen; Rechnung und HTML-Beleg zeigten dann „Autorisiert" unter der Überschrift „Bezahlt". Neu
  erfasst `OrderExtension::onAfterWrite()` die autorisierte Manual-Zahlung über Omnipay (Config
  `Order.capture_manual_payment_on_paid`, Vorgabe an). Bewusst **nach** dem Schreiben: dann ist die
  Bestellung in der Datenbank bezahlt, und `OrderProcessor::completePayment()` aus dem
  `onCaptured`-Hook von silvershop läuft nicht ein zweites Mal durch den Unpaid→Paid-Übergang.

Migration: keine. Wer den bisherigen Zustand braucht, setzt `capture_manual_payment_on_paid: false`.

### Rechnungsnummern werden nie wiederverwendet (Bugfix, wirkt sofort)

`OrderExtension::assignInvoiceNumber()` hat die nächste Nummer als `MAX(InvoiceNumber) + 1` über die
vorhandenen Bestellungen gebildet. Wird die Bestellung mit der höchsten Nummer gelöscht (Testdaten,
DSGVO-Löschung, Papierkorb im CMS), sinkt das Maximum und die nächste Bestellung bekommt **dieselbe
Rechnungsnummer noch einmal** (in einem Testshop beobachtet: zwei Bestellungen mit `GV-1002`). Zwei
gleichzeitige Zahlungsabschlüsse konnten ebenfalls dieselbe Nummer lesen.

Neu: ein persistenter Zähler in der Tabelle `ShopExtensions_NumberSequence`
(`ShopExtensions\Model\NumberSequence`). Er wird atomar hochgezählt (MySQL/MariaDB per
`UPDATE … LAST_INSERT_ID(…)`, sonst `UPDATE` + `SELECT` in einer Transaktion), sinkt nie und liegt
immer über der höchsten noch vorhandenen Rechnungsnummer (so heilt er importierte oder von Hand
gesetzte Nummern). `SiteConfig.InvoiceNumberStart` wirkt wie bisher nur, solange noch keine Rechnung
existiert.

Migration:
- `vendor/bin/sake db:build flush=1` (legt Tabelle und den Index auf `Order.InvoiceNumber` an).
- Der Zähler startet beim ersten Gebrauch automatisch bei der höchsten vorhandenen Nummer.
- Bestandsdaten prüfen (Doppel bleiben bestehen, sie werden nicht umnummeriert — eine ausgegebene
  Rechnungsnummer darf sich nicht ändern):
  ```sql
  SELECT InvoiceNumber, COUNT(*) c, GROUP_CONCAT(ID) FROM SilverShop_Order
  WHERE InvoiceNumber > 0 GROUP BY InvoiceNumber HAVING c > 1;
  ```
  Treffer gehören in die Buchhaltung (Storno + neue Rechnung), nicht ins Deployment.
- Wer den Zähler bewusst höher setzen will (neuer Nummernkreis), setzt `LastValue` in
  `ShopExtensions_NumberSequence` (Name `invoice`) einmalig per SQL.

### Zahlungsabschluss robuster (Bugfix, standardmäßig an)

- `ShopExtensions\Payment\PaymentReferenceExtension`: sichert die Transaktionsreferenz beim Start der
  Zahlung und gibt sie beim Abschluss mit. Ohne sie bricht der Mollie-Rücksprung mit „The
  transactionReference parameter is required" ab, obwohl der Kunde bezahlt hat. Füllt nur eine leere
  Referenz.
- `ShopExtensions\Payment\PaymentCompletionLockExtension`: ein MySQL-`GET_LOCK` je Bestellung,
  solange ein Zahlungsabschluss läuft. Webhook und Rücksprung kommen gleichzeitig an und verschicken
  sonst jede Bestell-Mail doppelt. Config `enabled`, `lock_timeout`, `lock_prefix`, Hook
  `updatePaymentLockName`.

Migration: beide Extensions sind in `shopextensions.yml` registriert. Projekte mit eigener Kopie
(`Tietge\Books\Checkout\Payment*Extension`, `Ghee\Shop\Checkout\Payment*Extension`) **löschen Klasse
und YAML-Eintrag im selben Schritt**.

### Warenkorb-Mengenfeld (Bugfix, standardmäßig an)

`ShopExtensions\Forms\ShopQuantityField` behebt den TypeError aus silvershop/core 6.0.x
(`forTemplate(): string` gibt `DBHTMLText` zurück → jede Warenkorbseite mit Position = 500). Per
Injector registriert.

Migration: Projektkopien (`Tietge\Books\Form\ShopQuantityField`, `CustomShopQuantityField`,
`Ghee\Shop\Form\ShopQuantityField`) samt Injector-Eintrag löschen.

### Zahlart-Kacheln: Verfügbarkeit, Kurzbeschreibung, Methoden-Dropdown

- `PaymentOption::canUse()` nimmt jetzt optional die Bestellung entgegen
  (`canUse(?Order $order = null)`, Hook `canUse($order)`) — Filter können den Warenkorb ansehen.
- Neue Config `required_gateway_parameters` (z. B. `{Mollie: [apiKey]}`): Kacheln, deren Gateway
  keine Zugangsdaten hat, werden ausgeblendet statt erst nach dem Klick auf „bestellen" zu scheitern.
- Neues Feld `TileDescription` (Varchar 160): ein Satz unter dem Kacheltitel, im mitgelieferten
  Template mit `aria-describedby` verknüpft.
- Neue Config `gateway_methods` (z. B. `{Mollie: {creditcard: Kreditkarte, …}}`): im CMS wird aus dem
  freien „Methoden-Code" ein Dropdown; ohne Eintrag bleibt das Textfeld.
- `OrderActionsFormExtension` (Wieder-Bezahlen im Konto): Config `repay_exclude_manual_gateways`
  (Standard false). Auf true fallen Vorkasse/Rechnung als Wiederholungszahlung weg; bleibt keine
  Online-Zahlart übrig, verschwindet der Zahlungsblock samt Button.

Migration: nur Config; das Feld `TileDescription` entsteht beim `db:build`. Projekte mit eigener
Extension für Kurzbeschreibung/Methodenliste (`Ghee\Shop\Extensions\PaymentOptionExtension`,
`Ghee\Shop\Checkout\MollieAvailabilityExtension`) löschen sie und setzen die Config-Werte.

### Ratenbegrenzung

`ShopExtensions\Security\RequestThrottle::attempt($request, $bucket, ?$key)` — Buckets als
`[Versuche, Minuten]`, Schlüssel je IP oder je übergebener Identität (z. B. `member-12`). Wird von den
Opt-in-Features benutzt (Kontoanlage im Checkout, Summary-Endpunkt, Registrierung, Cart-API) und kann
von Projekten/Modulen mitbenutzt werden. Ersetzt `Ghee\Security\RequestThrottle`.

### Gast-Rechnung `/rechnung/<token>` (opt-in)

`ShopExtensions\Receipt\OrderReceiptTokenExtension` + `ShopExtensions\Controllers\GuestReceiptController`
(siehe `_config/guestreceipt.yml.example`): unerratbares Token je Bestellung, PDF und optional eine
barrierefreie HTML-Ansicht (`?format=html`, Template `ShopExtensions/Layout/GuestReceipt.ss`), gleiche
404-Antwort für jeden Fehlerfall, kein Caching, `noindex` + `no-referrer`, Locale der Bestellung.

Migration: Spaltenname `ReceiptToken` wie in allen bisherigen Projektkopien — bestehende Mail-Links
bleiben gültig. Projektkopie (Extension **und** Controller **und** Director-Regel) im selben Schritt
löschen. `absolute_links: true` stellt das vogelwerk-Verhalten her (absolute `GuestReceiptLink()`).

### Checkout (opt-in, siehe `_config/checkout.yml.example`)

- `ShopExtensions\Checkout\CheckoutComponentValidator`: Pflichtfelder (core initialisiert `$required`
  nie), Feldbezug der Komponenten-Meldungen (Code-Map), E-Mail-Format, PLZ je Land, Zahlart-Prüfung,
  Hook `updateCheckoutValidation($data, $validator, &$valid)`.
- `ShopExtensions\Checkout\Component\BillingSameAsShippingAddress` und `…\Component\OptionalMembership`,
  einsetzbar über die neue Map `ShopCheckoutComponentConfig.component_classes` (kein eigener Subclass
  mehr nötig) plus `shipping_add_to_address_book` / `billing_add_to_address_book`.
- `ShopExtensions\CheckoutPageControllerExtension`: `$CheckoutPaymentNotice` (omnipay-6-tauglicher
  Ersatz für das kaputte `PaymentErrorMessage`), `$CheckoutErrorSummary` (Fehlerliste für A11Y),
  `restore_unchecked_checkboxes`, `hide_raw_gateway_messages`, `success_link_to_confirmation`,
  `terms_label_with_legal_links`, `POST <kasse>/summary` (`enable_summary_endpoint`) und
  `disable_cache` (Standard an: die Kasse ist sitzungsgebunden).
- `ShopExtensions\Checkout\SiteConfigCheckoutExtension`: Seiten für Widerruf/Datenschutz im
  AGB-Häkchen und die einmalige Migration `migrate_send_receipt_email`.

### Adresse: Straße + Hausnummer (opt-in)

`ShopExtensions\Address\StreetAddressExtension` (siehe `_config/streetaddress.yml.example`): getrennte
Felder `StreetName`/`HouseNumber` (Etiketten-APIs), synchrone einspaltige Kern-Adresse, Pflichtfelder
und PLZ-Prüfung je Land. Nicht zusammen mit einer projekteigenen Address-Extension registrieren.

### Lieferdatum (opt-in)

`ShopExtensions\Delivery\DeliveryEstimate` + `SiteConfigDeliveryExtension` (siehe
`_config/delivery.yml.example`): Versand- und Lieferdatum in Werktagen, Hook `updateIsBusinessDay`,
Template `ShopExtensions/Includes/DeliveryEstimate.ss`.

### Neue Schnittstellen für Fremdmodule (z. B. Abos)

- `ExtendedPurchaseService`: Hook `updatePurchaseData(&$data, $order, $payment)` — zusätzliche
  Gateway-Felder (Mollie `customerReference`, `sequenceType`, `mandateId`) ohne zweite
  PurchaseService-Extension mit unklarer Reihenfolge.
- `SilverShop\Model\Order.paid_status_gateways`: die bisher fest verdrahtete Gateway-Liste in
  `OrderExtension::onStatusChange()` ist konfigurierbar.
- `Order`-Hook `updateOrderEmail($email, $type)` in `SendOrderEmailJob` und `CustomOrderEmailNotifier`
  (`$type` = confirmation | receipt | admin_notification | cancel_notification | status_change).
- `ShopExtensions\Service\OrderPlacer`: Bestellungen ohne Checkout/Session/Request platzieren,
  Zahlung erfassen und auf „Bezahlt" setzen (Abo-Folgelieferungen aus Webhook/Cron; löst die normale
  Pipeline aus: Rechnungsnummer, Beleg-Mail, Admin-Mail).
- `ShopExtensions\AccountPageControllerExtension::AccountNavItems()` mit Hook
  `updateAccountNavItems($items)` — Fremdmodule hängen eigene Kontobereiche in die Navigation.
- `PaymentOption::canUse($order)` mit Bestellkontext (s. o.).
