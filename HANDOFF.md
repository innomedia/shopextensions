# Handoff: SendReceiptEmail — SiteConfig Migration

## Summary

Moved the `ShopConfig.sendReceipt` master switch (YAML config) to a CMS-editable
`Boolean` field `SendReceiptEmail` on `SiteConfig`. Admins can now toggle invoice PDF
attachment directly in the CMS (Settings → Shop → Rechnung & Belege) without touching
YAML files or doing a deploy.

---

## Files Changed

### `shopextensions/src/Extensions/SiteConfigExtension.php`

- Added `'SendReceiptEmail' => 'Boolean'` to `$db`
- Added `'SendReceiptEmail' => true` to `$defaults` (on by default, preserving previous behaviour)
- Added `'SendReceiptEmail'` to `$enabled_fields`
- Added `CheckboxField` import
- Added CMS checkbox field in the "Rechnung & Belege" sub-tab (inside Root.Shop.ShopTabs.Invoicing)

### `shopextensions/src/Injectors/CustomOrderEmailNotifier.php`

- `shouldAttachInvoice()`: master switch now reads `SiteConfig::current_site_config()->SendReceiptEmail`
  instead of `Config::inst()->get('ShopConfig', 'sendReceipt')`
- Legacy `buildEmail()`: same switch updated from `Config::inst()->get('ShopConfig', 'sendReceipt') != false`
  to `SiteConfig::current_site_config()->SendReceiptEmail`
- Updated inline comments/docblocks to reference the new location

### `shopextensions/_config/shopextensions.yml`

- Removed `ShopConfig: sendReceipt: true` block (no longer needed)
- Updated comment for `attach_invoice_to_*` flags to reference the new master switch

### `app/_config/shop.yml`

- Removed `sendReceipt: false` from the `ShopConfig:` block

---

## Required Action After Deploy

```bash
vendor/bin/sake dev/build flush=1
```

This creates the `SendReceiptEmail` column on the `SiteConfig` table.
Then go to **CMS → Settings → Shop → Rechnung & Belege** and verify the
"Rechnung als PDF-Anhang versenden" checkbox is ticked as desired.

---

## Behaviour

| Condition | Result |
|-----------|--------|
| `SendReceiptEmail` unchecked | No PDF attached to any order email |
| `SendReceiptEmail` checked | PDF attached per the `attach_invoice_to_*` YAML flags |
| `attach_invoice_to_receipt: true` (default) | PDF attached to customer receipt email |
| `attach_invoice_to_admin: true` (default) | PDF attached to admin notification email |
| `attach_invoice_to_confirmation: false` (default) | No PDF on order confirmation email |
