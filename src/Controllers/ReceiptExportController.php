<?php

namespace ShopExtensions\Controllers;

use Dompdf\Dompdf;
use SilverShop\Model\Order;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

/**
 * Batch accounting export for placed orders, ported from ss_oap_web and hardened for SS6.
 *
 * Two actions:
 *  - /csv/<month>/<year> (or /csv/<days>) — a DATEV EXTF "Buchungsstapel" CSV, one booking
 *    line per tax rate per order, plus a reversing line for cancelled (Storno) orders.
 *  - /pdf/<month>/<year> (or /pdf/<days>) — a single collected PDF of all matching receipts.
 *
 * OPT-IN: neither the route nor this controller are registered in the main shopextensions.yml.
 * A project enables them via _config/receiptexport.yml.example, which also supplies the DATEV
 * account numbers (kept out of code so nobody books against foreign accounts by accident).
 *
 * It relies on the optional {@see \ShopExtensions\OrderTaxExtension} engine being registered on
 * the Order (it calls $order->getTaxInformation()); the example config wires both together.
 *
 * For placed orders getTaxInformation() returns the breakdown frozen at placement, so the exported
 * bookings are stable — a later change of tax_mode / rates / coupon logic cannot alter what was
 * already booked. Mixed-rate orders produce one H booking line per rate (own revenue account).
 */
class ReceiptExportController extends Controller
{
    private static $allowed_actions = [
        'pdf' => true,
        'csv' => true,
    ];

    /**
     * Master on/off switch. When false, both actions return 404 even if the route is present.
     *
     * @config
     * @var bool
     */
    private static $enabled = false;

    /**
     * Assets sub-folder the collected PDF is written to in CLI mode.
     *
     * @config
     * @var string
     */
    private static $pdf_folder = 'Rechnungsexport';

    /**
     * DATEV EXTF export parameters. Account numbers are intentionally empty by default —
     * set them (verified with your tax advisor) in the project config.
     *
     * @config
     * @var array
     */
    private static $datev = [
        'kennzeichen' => 'EXTF',
        'version' => 500,
        'berater' => '',
        'account_length' => 4,
        'contra_account' => '',
        'cost_centre_1' => '',
        'account_by_rate' => [],
    ];

    /**
     * Enforce ADMIN access for web requests; CLI (cron) runs are allowed through.
     *
     * @return HTTPResponse|null Redirect response when unauthorised, otherwise null.
     */
    protected function guard()
    {
        if (!$this->config()->get('enabled')) {
            return $this->httpError(404);
        }
        if (Director::is_cli()) {
            return null;
        }
        if (!Security::getCurrentUser() || !Permission::check('ADMIN')) {
            $loginUrl = Controller::join_links(
                Security::config()->uninherited('login_url'),
                '?BackURL=' . urlencode($this->getRequest()->getURL(true))
            );
            return $this->redirect($loginUrl);
        }
        return null;
    }

    /**
     * Resolve the reporting period from the request (month/year or a trailing day count),
     * defaulting to "last month" on CLI.
     *
     * @return array{mode:string, month:string, year:string, days:string}
     */
    protected function resolvePeriod()
    {
        $request = $this->getRequest();
        $days = Convert::raw2sql($request->param('ID'));

        if (Director::is_cli()) {
            return [
                'mode' => 'monthyear',
                'month' => date('m', strtotime('first day of last month')),
                'year' => date('Y', strtotime('first day of last month')),
                'days' => $days,
            ];
        }

        return [
            'mode' => $request->param('OtherID') ? 'monthyear' : 'days',
            'month' => Convert::raw2sql($request->param('ID')),
            'year' => Convert::raw2sql($request->param('OtherID')),
            'days' => $days,
        ];
    }

    /**
     * The set of placed orders for the resolved period.
     *
     * @return \SilverStripe\ORM\DataList<Order>
     */
    protected function ordersForPeriod(): \SilverStripe\ORM\DataList
    {
        $period = $this->resolvePeriod();
        $statuses = ['Paid', 'Sent', 'Complete', 'Processing', 'AdminCancelled'];

        if ($period['mode'] === 'monthyear') {
            $month = (int) $period['month'];
            $year = (int) $period['year'];
            return Order::get()
                ->filter(['Status' => $statuses])
                ->exclude(['InvoiceNumber' => ''])
                ->where(sprintf('MONTH("ReceiptSent") = %d AND YEAR("ReceiptSent") = %d', $month, $year))
                ->sort(['InvoiceNumber' => 'ASC', 'ReceiptSent' => 'ASC']);
        }

        $days = (int) $period['days'];
        return Order::get()
            ->filter([
                'ReceiptSent:GreaterThan' => date('Y-m-d', strtotime("-$days day")),
                'Status' => $statuses,
            ])
            ->sort(['InvoiceNumber' => 'ASC']);
    }

    /**
     * Render all matching receipts into one PDF. Streams the download for web requests,
     * writes into the configured assets folder for CLI runs.
     *
     * @return HTTPResponse|void
     */
    public function pdf()
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        ini_set('max_execution_time', '3600');
        ini_set('memory_limit', '1536M');

        SSViewer::set_themes(\SilverStripe\Core\Config\Config::inst()->get(SSViewer::class, 'themes'));

        $content = '';
        foreach ($this->ordersForPeriod() as $order) {
            $content .= $this->customise([
                'Order' => $order,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('Receipt');
        }

        $dompdf = new Dompdf();
        $dompdf->loadHtml($content ?: '<p>Keine Rechnungen im Zeitraum.</p>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Rechnungen' . date('Y-m-d_H-i-s') . '.pdf';

        if (Director::is_cli()) {
            $folderName = $this->config()->get('pdf_folder');
            $folder = Folder::find_or_make($folderName);
            $file = File::create();
            $file->setFromString($dompdf->output(), $folderName . '/' . $filename, hash('sha256', $filename . microtime()));
            $file->ParentID = $folder->ID;
            $file->write();
            echo "PDF geschrieben: assets/{$folderName}/{$filename}\n";
            return;
        }

        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setBody($dompdf->output());
        return $response;
    }

    /**
     * Build and stream the DATEV EXTF CSV for the resolved period.
     *
     * @return HTTPResponse|void
     */
    public function csv()
    {
        if ($guard = $this->guard()) {
            return $guard;
        }

        ini_set('max_execution_time', '3600');

        $datev = $this->config()->get('datev');
        $accountByRate = (array) ($datev['account_by_rate'] ?? []);
        $period = $this->resolvePeriod();
        $orders = $this->ordersForPeriod();

        $rows = $this->datevHeaderRows($period, $datev);

        foreach ($orders as $order) {
            if (!$order->hasMethod('getTaxInformation')) {
                // Engine not registered — cannot break down tax; skip defensively.
                continue;
            }

            $payment = Payment::get()
                ->filter(['OrderID' => $order->ID, 'Status' => 'Captured'])
                ->sort('ID ASC')
                ->last();

            $customer = $this->customerName($order);

            foreach ($order->getTaxInformation() as $rate => $taxinfo) {
                if (floatval(str_replace(',', '.', $taxinfo['gross'])) == 0) {
                    continue;
                }

                $row = [];
                $row['Umsatz (ohne Soll/Haben-Kz)'] = $taxinfo['gross'];
                $row['Soll/Haben-Kennzeichen'] = 'H';
                if (isset($accountByRate[$rate])) {
                    $row['Konto'] = $accountByRate[$rate];
                }
                $row['Gegenkonto (ohne BU-Schlüssel)'] = $datev['contra_account'] ?? '';
                $row['Belegdatum'] = date('dm', strtotime($order->InvoiceDate));
                $row['Belegfeld 1'] = $order->InvoiceNumber();
                $row['Buchungstext'] = $customer;
                $row['KOST1 - Kostenstelle'] = $datev['cost_centre_1'] ?? '';
                $row['Beleginfo - Art 1'] = 'Beschreibung';
                $row['Beleginfo - Inhalt 1'] = $customer;
                $row['Beleginfo - Art 2'] = 'Umsatzsteuerprozent';
                $row['Beleginfo - Inhalt 2'] = $rate;
                $row['Beleginfo - Art 3'] = 'Zahlungsanbieter';
                $row['Beleginfo - Inhalt 3'] = $payment ? $payment->Gateway : '';
                $row['Beleginfo - Art 4'] = 'Bestellnummer';
                $row['Beleginfo - Inhalt 4'] = $order->ID;
                $row['Beleginfo - Art 5'] = 'Nettobetrag';
                $row['Beleginfo - Inhalt 5'] = $taxinfo['net'];
                $row['Beleginfo - Art 6'] = 'Steuerbetrag';
                $row['Beleginfo - Inhalt 6'] = $taxinfo['tax'];
                $row['Beleginfo - Art 8'] = 'Kundennummer';
                $row['Beleginfo - Inhalt 8'] = $order->MemberID ? 'M-' . $order->MemberID : 'A-' . $order->BillingAddressID;

                $rows .= implode(';', array_values($this->buildRow($row))) . "\r\n";

                // Reversing entry for cancelled orders (only when a Storno number exists).
                // The credit note reuses the invoice number with the "-S" suffix (e.g. RE10003-S,
                // see OrderExtension::StornoNumber()) as its Belegfeld, dated with the Storno date.
                // The reversal flips the revenue booking from Haben (H) to Soll (S) — the DATEV way
                // of cancelling the original entry — so the same gross amount nets to zero.
                if ($order->Status === 'AdminCancelled' && $order->StornoNumber()) {
                    $reverse = $this->buildRow($row);
                    $reverse['Belegdatum'] = date('dm', strtotime($order->StornoDate));
                    $reverse['Belegfeld 1'] = $order->StornoNumber();
                    $reverse['Soll/Haben-Kennzeichen'] = 'S';
                    $rows .= implode(';', array_values($reverse)) . "\r\n";
                }
            }
        }

        $filename = 'Rechnungen' . date('Y-m-d_H-i-s') . '.csv';

        if (Director::is_cli()) {
            echo $rows;
            return;
        }

        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'text/csv; charset=iso-8859-1');
        $response->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        // DATEV expects Windows-1252/ISO-8859-1; convert from the UTF-8 source strings.
        $response->setBody(mb_convert_encoding($rows, 'ISO-8859-1', 'UTF-8'));
        return $response;
    }

    /**
     * Resolve a readable customer name for the booking text.
     *
     * @param Order $order
     * @return string
     */
    protected function customerName(Order $order): string
    {
        if ($order->FirstName && $order->Surname) {
            return $order->FirstName . ' ' . $order->Surname;
        }
        if ($order->MemberID && $order->Member()) {
            return $order->Member()->FirstName . ' ' . $order->Member()->Surname;
        }
        if ($order->BillingAddressID && $order->BillingAddress()) {
            return $order->BillingAddress()->FirstName . ' ' . $order->BillingAddress()->Surname;
        }
        return '';
    }

    /**
     * Build the two DATEV EXTF header lines from config and the reporting period.
     *
     * @param array $period
     * @param array $datev
     * @return string
     */
    protected function datevHeaderRows(array $period, array $datev): string
    {
        $year = $period['year'] ?: date('Y', strtotime('first day of last month'));
        $month = str_pad((string) ($period['month'] ?: date('m', strtotime('first day of last month'))), 2, '0', STR_PAD_LEFT);
        $shortyear = substr((string) $year, -2);
        $lastDay = (new \DateTime('last day of ' . $year . '-' . $month))->format('d');

        $kennzeichen = $datev['kennzeichen'] ?? 'EXTF';
        $version = $datev['version'] ?? 500;
        $berater = $datev['berater'] ?? '';
        $accountLength = $datev['account_length'] ?? 4;

        // Leading, meaningful fields are built from config; the long trailing field list is
        // kept verbatim from the DATEV EXTF "Buchungsstapel" spec that oap used.
        $tail = ';;;;0;EUR;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;';
        $headerRow = $kennzeichen . ';' . $version . ';' . $shortyear . ';Buchungsstapel;7;;;;;;1;'
            . $berater . ';' . $year . '0101;' . $accountLength . ';'
            . $year . $month . '01;' . $year . $month . $lastDay . $tail . "\r\n";

        $secondHeaderRow = "Umsatz (ohne Soll/Haben-Kz);Soll/Haben-Kennzeichen;WKZ Umsatz;Kurs;Basis-Umsatz;WKZ Basis-Umsatz;Konto;Gegenkonto (ohne BU-Schlüssel);BU-Schlüssel;Belegdatum;Belegfeld 1;Belegfeld 2;Skonto;Buchungstext;Postensperre;Diverse Adressnummer;Geschäftspartnerbank;Sachverhalt;Zinssperre;Beleglink;Beleginfo - Art 1;Beleginfo - Inhalt 1;Beleginfo - Art 2;Beleginfo - Inhalt 2;Beleginfo - Art 3;Beleginfo - Inhalt 3;Beleginfo - Art 4;Beleginfo - Inhalt 4;Beleginfo - Art 5;Beleginfo - Inhalt 5;Beleginfo - Art 6;Beleginfo - Inhalt 6;Beleginfo - Art 7;Beleginfo - Inhalt 7;Beleginfo - Art 8;Beleginfo - Inhalt 8;KOST1 - Kostenstelle;KOST2 - Kostenstelle;Kost-Menge;EU-Land u. UStID;EU-Steuersatz;Abw. Versteuerungsart;Sachverhalt L+L;Funktionsergänzung L+L;BU 49 Hauptfunktionstyp;BU 49 Hauptfunktionsnummer;BU 49 Funktionsergänzung;Zusatzinformation - Art 1;Zusatzinformation- Inhalt 1;Zusatzinformation - Art 2;Zusatzinformation- Inhalt 2;Zusatzinformation - Art 3;Zusatzinformation- Inhalt 3;Zusatzinformation - Art 4;Zusatzinformation- Inhalt 4;Zusatzinformation - Art 5;Zusatzinformation- Inhalt 5;Zusatzinformation - Art 6;Zusatzinformation- Inhalt 6;Zusatzinformation - Art 7;Zusatzinformation- Inhalt 7;Zusatzinformation - Art 8;Zusatzinformation- Inhalt 8;Zusatzinformation - Art 9;Zusatzinformation- Inhalt 9;Zusatzinformation - Art 10;Zusatzinformation- Inhalt 10;Zusatzinformation - Art 11;Zusatzinformation- Inhalt 11;Zusatzinformation - Art 12;Zusatzinformation- Inhalt 12;Zusatzinformation - Art 13;Zusatzinformation- Inhalt 13;Zusatzinformation - Art 14;Zusatzinformation- Inhalt 14;Zusatzinformation - Art 15;Zusatzinformation- Inhalt 15;Zusatzinformation - Art 16;Zusatzinformation- Inhalt 16;Zusatzinformation - Art 17;Zusatzinformation- Inhalt 17;Zusatzinformation - Art 18;Zusatzinformation- Inhalt 18;Zusatzinformation - Art 19;Zusatzinformation- Inhalt 19;Zusatzinformation - Art 20;Zusatzinformation- Inhalt 20;Stück;Gewicht;Zahlweise;Forderungsart;Veranlagungsjahr;Zugeordnete Fälligkeit;Skontotyp;Auftragsnummer;Buchungstyp;Ust-Schlüssel (Anzahlungen);EU-Land (Anzahlungen);Sachverhalt L+L (Anzahlungen);EU-Steuersatz (Anzahlungen);Erlöskonto (Anzahlungen);Herkunft-Kz;Buchungs GUID;KOST-Datum;Mandatsreferenz;Skontosperre;Gesellschaftername;Beteiligtennummer;Identifikationsnummer;Zeichnernummer;Postensperre bis;BezeichnungSoBil-Sachverhalt;KennzeichenSoBil-Buchung;Festschreibung;Leistungsdatum;Datum Zuord. Steuerperiode;Netto;Umsatzsteuer\r\n";

        return $headerRow . $secondHeaderRow;
    }

    /**
     * Overlay the given values onto the full ordered DATEV column skeleton so every emitted
     * line has exactly the same column count/order as the header.
     *
     * @param array $data
     * @return array
     */
    public function buildRow($data)
    {
        $row = array_fill_keys([
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs', 'Basis-Umsatz',
            'WKZ Basis-Umsatz', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'BU-Schlüssel', 'Belegdatum',
            'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext', 'Postensperre', 'Diverse Adressnummer',
            'Geschäftspartnerbank', 'Sachverhalt', 'Zinssperre', 'Beleglink',
            'Beleginfo - Art 1', 'Beleginfo - Inhalt 1', 'Beleginfo - Art 2', 'Beleginfo - Inhalt 2',
            'Beleginfo - Art 3', 'Beleginfo - Inhalt 3', 'Beleginfo - Art 4', 'Beleginfo - Inhalt 4',
            'Beleginfo - Art 5', 'Beleginfo - Inhalt 5', 'Beleginfo - Art 6', 'Beleginfo - Inhalt 6',
            'Beleginfo - Art 7', 'Beleginfo - Inhalt 7', 'Beleginfo - Art 8', 'Beleginfo - Inhalt 8',
            'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle', 'Kost-Menge', 'EU-Land u. UStID', 'EU-Steuersatz',
            'Abw. Versteuerungsart', 'Sachverhalt L+L', 'Funktionsergänzung L+L', 'BU 49 Hauptfunktionstyp',
            'BU 49 Hauptfunktionsnummer', 'BU 49 Funktionsergänzung',
            'Zusatzinformation - Art 1', 'Zusatzinformation- Inhalt 1', 'Zusatzinformation - Art 2', 'Zusatzinformation- Inhalt 2',
            'Zusatzinformation - Art 3', 'Zusatzinformation- Inhalt 3', 'Zusatzinformation - Art 4', 'Zusatzinformation- Inhalt 4',
            'Zusatzinformation - Art 5', 'Zusatzinformation- Inhalt 5', 'Zusatzinformation - Art 6', 'Zusatzinformation- Inhalt 6',
            'Zusatzinformation - Art 7', 'Zusatzinformation- Inhalt 7', 'Zusatzinformation - Art 8', 'Zusatzinformation- Inhalt 8',
            'Zusatzinformation - Art 9', 'Zusatzinformation- Inhalt 9', 'Zusatzinformation - Art 10', 'Zusatzinformation- Inhalt 10',
            'Zusatzinformation - Art 11', 'Zusatzinformation- Inhalt 11', 'Zusatzinformation - Art 12', 'Zusatzinformation- Inhalt 12',
            'Zusatzinformation - Art 13', 'Zusatzinformation- Inhalt 13', 'Zusatzinformation - Art 14', 'Zusatzinformation- Inhalt 14',
            'Zusatzinformation - Art 15', 'Zusatzinformation- Inhalt 15', 'Zusatzinformation - Art 16', 'Zusatzinformation- Inhalt 16',
            'Zusatzinformation - Art 17', 'Zusatzinformation- Inhalt 17', 'Zusatzinformation - Art 18', 'Zusatzinformation- Inhalt 18',
            'Zusatzinformation - Art 19', 'Zusatzinformation- Inhalt 19', 'Zusatzinformation - Art 20', 'Zusatzinformation- Inhalt 20',
            'Stück', 'Gewicht', 'Zahlweise', 'Forderungsart', 'Veranlagungsjahr', 'Zugeordnete Fälligkeit', 'Skontotyp',
            'Auftragsnummer', 'Buchungstyp', 'Ust-Schlüssel (Anzahlungen)', 'EU-Land (Anzahlungen)',
            'Sachverhalt L+L (Anzahlungen)', 'EU-Steuersatz (Anzahlungen)', 'Erlöskonto (Anzahlungen)', 'Herkunft-Kz',
            'Buchungs GUID', 'KOST-Datum', 'Mandatsreferenz', 'Skontosperre', 'Gesellschaftername', 'Beteiligtennummer',
            'Identifikationsnummer', 'Zeichnernummer', 'Postensperre bis', 'BezeichnungSoBil-Sachverhalt',
            'KennzeichenSoBil-Buchung', 'Festschreibung', 'Leistungsdatum', 'Datum Zuord. Steuerperiode',
            'Netto', 'Umsatzsteuer',
        ], '');

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $value;
            }
        }
        return $row;
    }
}
