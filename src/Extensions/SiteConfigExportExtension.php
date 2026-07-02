<?php

namespace ShopExtensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;

/**
 * Adds a small "Rechnungsexport / Buchhaltung" panel to the SiteConfig "Shop" tab: a month/year
 * picker plus two buttons that trigger the DATEV-CSV and the collected-PDF export provided by
 * {@see \ShopExtensions\Controllers\ReceiptExportController}.
 *
 * OPT-IN: registered only via _config/receiptexport.yml.example, and the panel is additionally
 * gated behind the {@see self::$enabled} config flag. The buttons link to the export route
 * (default segment "shopexport"); the segment is configurable to match the project's route.
 *
 * @property \SilverStripe\SiteConfig\SiteConfig $owner
 */
class SiteConfigExportExtension extends Extension
{
    /**
     * Whether the export panel is shown in the CMS.
     *
     * @config
     * @var bool
     */
    private static $enabled = false;

    /**
     * URL segment the export controller is mounted on (see the route in receiptexport.yml.example).
     *
     * @config
     * @var string
     */
    private static $route_segment = 'shopexport';

    /**
     * Render the month/year picker and the two export buttons into the Shop tab.
     *
     * @param FieldList $fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Read this extension's own config (not the owner's) so it never collides with the
        // identically-named `enabled` flag on other SiteConfig extensions.
        if (!Config::inst()->get(static::class, 'enabled')) {
            return $fields;
        }

        // Add a sub-tab inside silvershop's existing Root.Shop.ShopTabs TabSet (created by
        // ShopConfigExtension). This extension must run AFTER silvershop's, otherwise
        // findOrMakeTab would auto-create the path and spawn a duplicate "Shop" tab — the
        // required ordering is set via `After: silvershop/config#shopconfig` in receiptexport.yml.
        $tab = 'Root.Shop.ShopTabs.Export';
        $fields->findOrMakeTab($tab)->setTitle('Rechnungsexport');
        $fields->addFieldToTab($tab, HeaderField::create('ExportHeader', 'Rechnungsexport / Buchhaltung', 3));

        $segment = trim((string) Config::inst()->get(static::class, 'route_segment'), '/') ?: 'shopexport';

        $months = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
        ];
        $lastMonth = date('m', strtotime('first day of last month'));
        $lastMonthYear = (int) date('Y', strtotime('first day of last month'));

        $monthOptions = '';
        foreach ($months as $value => $label) {
            $selected = $value === $lastMonth ? ' selected' : '';
            $monthOptions .= "<option value=\"{$value}\"{$selected}>{$label}</option>";
        }

        $yearOptions = '';
        for ($y = $lastMonthYear + 1; $y >= $lastMonthYear - 6; $y--) {
            $selected = $y === $lastMonthYear ? ' selected' : '';
            $yearOptions .= "<option value=\"{$y}\"{$selected}>{$y}</option>";
        }

        // A CMS-only mini form: two selects and two buttons whose target URL is assembled from
        // the current selection. Kept inline; this panel is admin-facing tooling, not frontend.
        $html = <<<HTML
<div class="shopexport-panel" style="padding:12px 0;">
    <p>Zeitraum wählen und exportieren. Der Export umfasst alle bezahlten/abgeschlossenen Bestellungen des Monats.</p>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
        <label>Monat
            <select id="shopexport-month" class="form-control" style="display:inline-block;width:auto;">{$monthOptions}</select>
        </label>
        <label>Jahr
            <select id="shopexport-year" class="form-control" style="display:inline-block;width:auto;">{$yearOptions}</select>
        </label>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a id="shopexport-csv" class="btn btn-primary" href="#" target="_blank" rel="noopener">DATEV-CSV exportieren</a>
        <a id="shopexport-pdf" class="btn btn-outline-primary" href="#" target="_blank" rel="noopener">Sammel-PDF exportieren</a>
    </div>
    <script>
    (function(){
        function upd(){
            var m = document.getElementById('shopexport-month').value;
            var y = document.getElementById('shopexport-year').value;
            document.getElementById('shopexport-csv').href = '/{$segment}/csv/' + m + '/' + y;
            document.getElementById('shopexport-pdf').href = '/{$segment}/pdf/' + m + '/' + y;
        }
        ['shopexport-month','shopexport-year'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', upd);
        });
        upd();
    })();
    </script>
</div>
HTML;

        $fields->addFieldToTab($tab, LiteralField::create('ShopExportButtons', $html));

        return $fields;
    }
}
