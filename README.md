# shopextensions

German-market extensions for [silvershop/core](https://github.com/silvershop/silverstripe-shop).
The base module covers a shop, but not everything the German market needs — this package fills
those gaps without forking silvershop, so projects ported from SilverStripe 4/5 to 6 keep working.

> **Setup- & Konfigurationsanleitung:** siehe [`docs/USAGE.md`](docs/USAGE.md)
> (Installation, Pflicht-Setup, alle Config-YAML-Variablen, SiteConfig-Felder).

## What it does

**Invoices & PDFs** — `OrderExtension` adds invoice/billing meta fields, assigns sequential
invoice numbers from a configurable number range, and renders invoice + delivery-slip PDFs via
dompdf (`Receipt.ss` / `DeliverySlip.ss`). The PDFs are streamed by the access-controlled
`OrderReceiptController` (route `OrderReceipt/StreamReceipt/<ID>`, plus a `PreviewReceipt/<ID>`
action that rasterises the first page to a PNG via ImageMagick for the CMS preview; download and
preview restricted to the owning member or a CMS user) and, for customers, by
`AccountPageControllerExtension`. Zero-value (0 €) orders get no invoice unless
`Order.issue_invoice_for_zero_total` is enabled. Also provides `getDescription()`, which is
**required** by payment gateways such as Mollie/PayPal.

**Checkout usability** — `CheckoutPageControllerExtension` + `Checkout\ShopCheckoutComponentConfig`
swap in a cleaner checkout form (CompositeFields flattened, shipping address hidden when nothing
ships, zero-total redirect fixed). Projects that need extra checkout fields subclass
`ShopCheckoutComponentConfig`, override `addProjectComponents()` (runs between Notes and Terms)
and point `CheckoutPageControllerExtension.checkout_component_config_class` at their own class —
project-specific components never live in this module. `AddressExtension` localises country names and adds
Company/Name fields. `AjaxCartController` / `ShoppingCartControllerExtension` /
`ProductControllerExtension` power the AJAX mini-cart and live variant prices.

**Payment robustness** — `ExtendedPurchaseService` injects the order description, terms-page
service URL and language into the gateway request and removes reserved params (a common Mollie
failure). 

**Tax & shipping** — `CustomTaxModifier` supports inclusive/exclusive tax and per-product or
static rates; `ProductExtension` adds the per-product tax field. `CustomShippingModifier` is a
deprecated legacy calculator (session billing country, flat domestic rate, weight tiers abroad;
rates configurable) that is not registered by default; new shops should use their own modifier.

**Notifications** — `CustomOrderEmailNotifier` (Injector override) sends order emails through the
`SendOrderEmailJob` queued job and attaches the invoice PDF; attachment is configurable per mail
type.

**Configuration** — `SiteConfigExtension` moves operator-facing settings (receipt logo, header,
footer, phone, admin notification recipient, invoice number prefix/start, checkout hints) into
the CMS SiteConfig. The `enabled_fields` config array selects which fields are shown per project;
see `_config/siteconfig.yml.example`.

**Optional add-ons** (shipped but off by default, opt-in via YAML — see `docs/USAGE.md` §7–8):
- **Accounting export** — `OrderTaxExtension` (per-tax-rate breakdown with coupon/gift-card/
  shipping distribution, B2C & B2B) + `ReceiptExportController` (DATEV-EXTF CSV + collected PDF),
  with a month/year trigger panel in the Shop SiteConfig tab. Enable via
  `_config/receiptexport.yml.example`.
- **Checkout payment tiles** — `PaymentMethodComponent` lets the customer pick the concrete
  payment method (e.g. a Mollie sub-method) as a JS-free radio tile; the choice is persisted on
  the order and forwarded to the provider (`paymentMethod`) to skip its selection screen. Enable
  via `_config/paymenttiles.yml.example`.

## Debug

Overwrite `Receipt.ss` and `DeliverySlip.ss` in templates.
On 404 after e.g. Mollie returns "paid" and the order is not found,
check if emails can be sent without error.

Debug Hint:
set Order to paid in Database
and call
/OrderReceipt/StreamDeliverySlip/$OrderID
and
/OrderReceipt/StreamReceipt/$OrderID
to test for errors


You may also need to apply the silvershop.patch for Address Fields to correctly display in default templates
just add/require "cweagans/composer-patches" -> composer update -> add  
"patches": {
    "silvershop/core": {
        "Made Address Fields not in composite field": "silvershop.patch"
    }
},
to your "extra" in composer.json
and update again (first one needed for composer-patches to install second needed for the patch to be applied)
