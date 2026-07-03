<?php

namespace ShopExtensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;

/**
 * SiteConfig extension that adds shop-related global settings: receipt logo,
 * header/footer and phone number, a price cost hint, payment hints (before and
 * after checkout), an admin notification e-mail, and the invoice number
 * prefix/start values.
 *
 * All fields are placed inside silvershop's own "Shop" SiteConfig tab
 * (Root.Shop.ShopTabs, created by {@see \SilverShop\Extension\ShopConfigExtension}),
 * grouped into three readable sub-tabs so every shop setting lives in one place:
 *   - "Rechnung & Belege" — logo, sender line, footer, phone, invoice number range
 *   - "Benachrichtigungen" — admin notification recipient
 *   - "Hinweise & Texte"   — price hint and checkout hints
 *
 * Which fields are actually exposed is controlled by the {@see self::$enabled_fields}
 * config array: each field is only added when its name is present in that array. This
 * lets a project selectively show or hide individual settings without changing the field
 * definitions. Setting it to an empty array hides all of them.
 *
 * shopextensions.yml sets `After: silvershop/config#shopconfig`, so this extension runs
 * after silvershop's and the Root.Shop tab already exists when the sub-tabs are added.
 *
 * @property \SilverStripe\SiteConfig\SiteConfig $owner
 */
class SiteConfigExtension extends Extension
{
    private static $db = [
        'CostHint' => 'Text',
        'HintPayment' => 'HTMLText',
        'HintAfterPayment' => 'HTMLText',
        'ReceiptHeader' => 'HTMLText',
        'ReceiptFooter' => 'HTMLText',
        'ReceiptPhone' => 'Text',
        'AdminNotificationMail' => 'Text',
        'InvoiceNumberPrefix' => 'Text',
        'InvoiceNumberStart' => 'Int',
        'SendReceiptEmail' => 'Boolean',
    ];

    private static $has_one = [
        'ReceiptLogo' => Image::class,
    ];

    /**
     * Defaults for the invoice number range. These mirror the fallback constants in
     * OrderExtension, so a fresh SiteConfig already shows sensible values.
     */
    private static array $defaults = [
        'InvoiceNumberPrefix' => 'RE',
        'InvoiceNumberStart' => 200000,
        'SendReceiptEmail' => true,
    ];

    /**
     * Configure which fields to show in CMS
     * Set to empty array to hide all, or specify field names to show
     * Default: all fields are shown
     */
    private static array $enabled_fields = [
        'CostHint',
        'AdminNotificationMail',
        'ReceiptHeader',
        'ReceiptFooter',
        'ReceiptLogo',
        'ReceiptPhone',
        'HintPayment',
        'HintAfterPayment',
        'InvoiceNumberPrefix',
        'InvoiceNumberStart',
        'SendReceiptEmail',
    ];

    /**
     * Add the shop settings fields to the SiteConfig CMS form.
     *
     * Fields are nested inside silvershop's "Shop" tab (Root.Shop.ShopTabs) and split
     * into three sub-tabs. Only fields listed in the enabled_fields config are added, and
     * a sub-tab (and its heading) is only created when at least one of its fields is enabled.
     *
     * @param  FieldList $fields The SiteConfig CMS field list.
     * @return FieldList The updated field list.
     */
    public function updateCMSFields(FieldList $fields)
    {
        $enabledFields = $this->owner->config()->get('enabled_fields');

        // Base path: silvershop creates Root.Shop.ShopTabs (a TabSet). We add sub-tabs there
        // so all shop-related settings sit together in the CMS "Shop" tab.
        $invoiceTab = 'Root.Shop.ShopTabs.Invoicing';
        $notifyTab  = 'Root.Shop.ShopTabs.Notifications';
        $hintsTab   = 'Root.Shop.ShopTabs.Hints';

        $enabled = fn (string $name): bool => in_array($name, $enabledFields);

        // --- Sub-tab: Rechnung & Belege -------------------------------------------------
        $invoiceGroup = ['ReceiptLogo', 'ReceiptHeader', 'ReceiptFooter', 'ReceiptPhone', 'InvoiceNumberPrefix', 'InvoiceNumberStart'];
        if (array_filter($invoiceGroup, $enabled)) {
            $fields->findOrMakeTab($invoiceTab)->setTitle('Rechnung & Belege');

            // Belege (PDF)
            if (array_filter(['ReceiptLogo', 'ReceiptHeader', 'ReceiptFooter', 'ReceiptPhone'], $enabled)) {
                $fields->addFieldToTab($invoiceTab, HeaderField::create('BelegeHeader', 'Belege (Rechnung & Lieferschein als PDF)', 3));
            }

            if ($enabled('ReceiptLogo')) {
                $fields->addFieldToTab($invoiceTab, UploadField::create('ReceiptLogo', 'Logo')
                    ->setDescription('Firmenlogo oben auf Rechnung und Lieferschein (PDF). Empfohlene Breite zwischen 400 und 1000 px. Zu große Dateien behindern die PDF-Generierung und können Probleme verursachen.'));
            }

            if ($enabled('ReceiptHeader')) {
                $fields->addFieldToTab($invoiceTab, TextField::create('ReceiptHeader', 'Absenderzeile')
                    ->setDescription('Kleine einzeilige Absenderangabe direkt über der Empfängeradresse auf dem Beleg, z. B. „Musterfirma GmbH · Musterstr. 1 · 12345 Musterstadt".'));
            }

            if ($enabled('ReceiptFooter')) {
                $fields->addFieldToTab($invoiceTab, HTMLEditorField::create('ReceiptFooter', 'Fußzeile')
                    ->setDescription('Fußzeile unten auf Rechnung/Lieferschein. Hier gehören z. B. Bankverbindung, USt-IdNr., Handelsregister, Geschäftsführer und ggf. der Kleinunternehmer-Hinweis hin.')
                    ->setRows(4));
            }

            if ($enabled('ReceiptPhone')) {
                $fields->addFieldToTab($invoiceTab, TextField::create('ReceiptPhone', 'Telefonnummer')
                    ->setDescription('Erscheint in der Signatur der Status-E-Mails an Kunden (und optional auf Belegen).'));
            }

            // Nummernkreis
            if (array_filter(['InvoiceNumberPrefix', 'InvoiceNumberStart'], $enabled)) {
                $fields->addFieldToTab($invoiceTab, HeaderField::create('NummernkreisHeader', 'Nummernkreis (Rechnungsnummern)', 3));
            }

            if ($enabled('InvoiceNumberPrefix')) {
                $fields->addFieldToTab($invoiceTab, TextField::create('InvoiceNumberPrefix', 'Rechnungsnummer-Präfix')
                    ->setDescription('Wird jeder Rechnungsnummer vorangestellt, z. B. „RE" → RE200000. Leer = Standard „RE".'));
            }

            if ($enabled('InvoiceNumberStart')) {
                $fields->addFieldToTab($invoiceTab, NumericField::create('InvoiceNumberStart', 'Erste Rechnungsnummer')
                    ->setDescription('Startwert des Nummernkreises. Wird nur für die allererste Rechnung genutzt; danach fortlaufend +1. Leer = 200000. Bereits vergebene Nummern werden nicht verändert.'));
            }

            if ($enabled('SendReceiptEmail')) {
                $fields->addFieldToTab($invoiceTab, CheckboxField::create('SendReceiptEmail', 'Rechnung als PDF-Anhang versenden')
                    ->setDescription('Wenn aktiviert, wird die Rechnung als PDF an die Bestell-E-Mails angehängt (je nach Konfiguration pro Mail-Typ). Ersetzt den YAML-Schalter ShopConfig.sendReceipt.'));
            }
        }

        // --- Sub-tab: Benachrichtigungen ------------------------------------------------
        if ($enabled('AdminNotificationMail')) {
            $fields->findOrMakeTab($notifyTab)->setTitle('Benachrichtigungen');
            $fields->addFieldToTab($notifyTab, TextField::create('AdminNotificationMail', 'Admin-Benachrichtigungsmail')
                ->setDescription('Empfänger der Bestell-Benachrichtigung (inkl. Rechnungs-PDF), sobald eine Bestellung bezahlt wurde. Mehrere Adressen per Komma trennen. Leer = Fallback auf die System-Admin-Adresse.'));
        }

        // --- Sub-tab: Hinweise & Texte --------------------------------------------------
        $hintsGroup = ['CostHint', 'HintPayment', 'HintAfterPayment'];
        if (array_filter($hintsGroup, $enabled)) {
            $fields->findOrMakeTab($hintsTab)->setTitle('Hinweise & Texte');

            if ($enabled('CostHint')) {
                $fields->addFieldToTab($hintsTab, TextareaField::create('CostHint', 'Preis-Hinweis')
                    ->setDescription('Zusatztext bei Produktpreisen, z. B. „inkl. MwSt., zzgl. Versandkosten".')
                    ->setRows(2));
            }

            if ($enabled('HintPayment')) {
                $fields->addFieldToTab($hintsTab, HTMLEditorField::create('HintPayment', 'Hinweis im Checkout (Zahlung)')
                    ->setDescription('Hinweistext im Checkout, der über der Auswahl der Zahlungsmittel angezeigt wird.')
                    ->setRows(3));
            }

            if ($enabled('HintAfterPayment')) {
                $fields->addFieldToTab($hintsTab, HTMLEditorField::create('HintAfterPayment', 'Hinweis nach Bestellabschluss')
                    ->setDescription('Wird dem Kunden nach dem Kauf angezeigt (Danke-Seite und Bestätigungs-/Beleg-Mail). Hier machen sich z. B. Infos gut, wie es weitergeht.')
                    ->setRows(3));
            }
        }

        return $fields;
    }

    /**
     * The receipt logo as a base64 `data:` URI (resized to fit 770×770).
     *
     * dompdf fetches `<img src="http://…">` URLs over HTTP while rendering, which is flaky
     * (auth, hostname/SSL, timeouts) and often produces a broken logo in the PDF. Embedding the
     * image bytes inline as a data URI removes that network round-trip entirely, so the logo
     * renders reliably. Used by Receipt.ss / DeliverySlip.ss.
     *
     * @return string|null data URI, or null when no (usable) logo is set.
     */
    public function ReceiptLogoDataURI(): ?string
    {
        $logo = $this->owner->ReceiptLogo();
        if (!$logo || !$logo->exists()) {
            return null;
        }

        // Downscale to the same box the template used before; keeps the embedded payload small.
        $resized = $logo->Pad(770, 770);
        if (!$resized || !$resized->exists()) {
            return null;
        }

        $data = $resized->getString();
        if ($data === null || $data === false || $data === '') {
            return null;
        }

        $mime = $resized->getMimeType() ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * Short VAT hint for price displays that adapts to the configured tax mode: "zzgl. MwSt."
     * when tax is added on top (exclusive) and "inkl. MwSt." when it is already included
     * (inclusive). Single source of truth is CustomTaxModifier.tax_mode, so the product/seminar
     * price hints never contradict the tax shown in the cart/checkout modifier.
     *
     * Globally available in templates as $SiteConfig.TaxHint.
     *
     * @return string
     */
    public function TaxHint(): string
    {
        $mode = CustomTaxModifier::config()->get('tax_mode');
        if ($mode === 'exclusive') {
            return _t('ShopExtensions\\Tax.PLUS_VAT', 'zzgl. MwSt.');
        }
        return _t('ShopExtensions\\Tax.INCL_VAT', 'inkl. MwSt.');
    }
}
