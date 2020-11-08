<?php
namespace ShopExtensions;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverShop\Model\Order;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\Director;
use Dompdf\Dompdf;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverShop\Page\AccountPage;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Config\Config;

class OrderExtension extends DataExtension{
    private static $db = [
        'BillingName' => 'Varchar',
        'VATNumber' => 'Varchar',
        'InvoiceNumber' => 'Int',
        'InvoiceHTML' => 'HTMLText',
        'InvoicePrefix' => 'Text',
        'InvoiceDate' => 'Date',
        'ReferencePrefix' => 'Text',
        'Newsletter' => 'Boolean(0)'
    ];

    /* public function onPaid(){

         if (isset($_SESSION['campaign'])){
             $campaignOrder = CampaignOrder::create();
             $campaignOrder->Order = $this->owner->ID;
             $campaignOrder->Campaign = $_SESSION['campaign'];
             $campaignOrder->write();
         }
     }*/

    public function updateCMSFields(FieldList $fields)
    {
        $receiptLink = "/OrderReceipt/StreamReceipt/" . $this->owner->ID;
        $deliverySlipLink = "/OrderReceipt/StreamDeliverySlip/" . $this->owner->ID;
        $fields->addFieldToTab("Root.Rechnungen",LiteralField::create("DownloadReceipt", "<a class='btn btn-primary' href='" . $receiptLink . "'>Rechnung herunterladen</a>"));
        $fields->addFieldToTab("Root.Rechnungen",LiteralField::create("DownloadDeliverySlip", "<a class='btn btn-primary' href='" . $deliverySlipLink . "'>Lieferschein herunterladen</a>"));
    }

    public function canPay(){
        $discounts = $this->owner->Discounts();
        $totaldiscount = 0;
        if(count($discounts) > 0 ){
            foreach ($discounts as $discount){
                $totaldiscount += $discount->Amount;
            }
        }

        if($totaldiscount > 0){
            if (!in_array($this->owner->Status, Order::config()->payable_status)) {
                return false;
            }
            if (empty($this->owner->Paid)) {
                return true;
            }
            return true;
        }
    }

    public function HasBeenPlaced(){
        if(!isset($_REQUEST['fs'])){
            return true;
        } else {
            return false;
        }
    }

    public function canCancel($member)
    {
        // TODO check if order was placed more than 24 hrs ago and return false if true
        $created = DBDatetime::create()->setValue($this->owner->Created);
        if ((int) $created->TimeDiffIn('hours') > 24) {
            return false;
        }
    }


    public function onPaid()
    {
        // TODO Create Invoice Number (DRO)
        $maxRef = Order::get()->sort('InvoiceNumber DESC')->first();
        if (!$this->owner->InvoiceNumber) {
            if ($maxRef->InvoiceNumber) {
                $this->owner->InvoiceNumber = (int) $maxRef->InvoiceNumber + 1;
            } else {
                $this->owner->InvoiceNumber = 200000;
            }
        }
        $this->owner->InvoiceDate = date('Y-m-d H:i:s');

        if (!$this->owner->ReceiptSent) {
            $notifier = CustomOrderEmailNotifier::create($this->owner)->sendReceipt();
            $this->owner->ReceiptSent = DBDatetime::now()->Rfc2822();
        }
    }

    public function onStatusChange($fromStatus, $toStatus)
    {
        $payment = '';
        $payments = Payment::get()->filter('OrderID', $this->owner->ID)->sort('ID', 'ASC');
        if (count($payments) > 0) {
            $payment = $payments->last();
        }

        if ($fromStatus == 'Cart ' && $toStatus == 'Unpaid' && in_array($payment->Gateway, ['Manual'])) {
            // create invoice number
            $maxRef = Order::get()->sort('InvoiceNumber DESC')->first();
            if (!$this->owner->InvoiceNumber) {
                if ($maxRef->InvoiceNumber) {
                    $this->owner->InvoiceNumber = (int)$maxRef->InvoiceNumber + 1;
                } else {
                    $this->owner->InvoiceNumber = 200000;
                }
            }
            $this->owner->InvoiceDate = date('Y-m-d H:i:s');
        }

        if ($toStatus == 'Paid' && in_array($payment->Gateway, ['Stripe', 'PayPal_Express', 'PayPal_Pro', 'Mollie'])  ) {
            $this->owner->Paid = DBDatetime::now()->Rfc2822();
            foreach ($this->owner->Items() as $item) {
                $item->onPlacement();
                $item->onPayment();
                $item->write();
            }

            //all payment is settled
            $this->onPaid();

            $notifier = CustomOrderEmailNotifier::create($this->owner);
            $notifier->sendAdminNotification();
        }
    }

    public function onBeforeWrite()
    {
        if(isset($_REQUEST['Newsletter'])){
            if($_REQUEST['Newsletter'] == 1){
                $this->owner->Newsletter = true;
                $this->signupForNewsletter($this->owner);
            }
        }
    }

    public function ReceiptLink()
    {
        if ($page = AccountPage::get()->first()) {
            $base = $page->Link();
            return Controller::join_links($base, 'receipt', $this->owner->ID);
        }
    }

    public function ReferencePrefix(){
        return 'RE';
    }

    public function InvoiceNumber()
    {
        if ($this->owner->InvoiceNumber == 0 || $this->owner->InvoiceNumber == "" || $this->owner->InvoiceNumber == null) {
            return false;
        } else {
            return $this->ReferencePrefix().$this->owner->InvoiceNumber;
        }

        /*
        if ($this->owner->InvoiceNumber == 0 || $this->owner->InvoiceNumber == "" || $this->owner->InvoiceNumber == null) {
            $filter = [];
            $siteconfig = SiteConfig::current_site_config();
            if ($siteconfig->InvoiceNumberPrefix == "DBN") {
                $filter = ["", "DBN"];
            } else {
                $filter = [$siteconfig->InvoiceNumberPrefix];
            }
            $maxRef = Order::get()->filter(["InvoicePrefix" => $filter])->sort('InvoiceNumber', 'DESC')->first();

            $this->owner->InvoiceNumber = str_pad(((int) $maxRef->InvoiceNumber + (int) 1), 7, '0', STR_PAD_LEFT);
            $this->owner->write();
        }
        return $this->owner->InvoicePrefix . $this->owner->InvoiceNumber;
        */
    }

    public function PDFReceipt($type_ = 'stream')
    {
        $controller = \SilverStripe\CMS\Controllers\ContentController::create();

        if ($this->owner->canView()) {
            $siteconfig = SiteConfig::current_site_config();

            \SilverStripe\View\SSViewer::set_themes(Config::inst()->get('SilverStripe\View\SSViewer', 'themes'));
            $content = $controller->customise([
                'Order' => $this->owner,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('Receipt');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);


            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('A4', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            if ($type_ == 'stream') {
                $dompdf->stream('Rechnung ' . $this->owner->Reference);
            } else {
                return $dompdf->output();
            }
        } else {
            return;
        }
    }

    public function PDFDeliverySlip($type_ = 'stream')
    {
        $controller = \SilverStripe\CMS\Controllers\ContentController::create();

        if ($this->owner->canView()) {
            $siteconfig = SiteConfig::current_site_config();

            \SilverStripe\View\SSViewer::set_themes(Config::inst()->get('SilverStripe\View\SSViewer', 'themes'));
            $content = $controller->customise([
                'Order' => $this->owner,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('DeliverySlip');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);


            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('A4', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            if ($type_ == 'stream') {
                $dompdf->stream('Lieferschein ' . $this->owner->Reference);
            } else {
                return $dompdf->output();
            }
        } else {
            return;
        }
    }

    public function getDescription()
    {
        /** @var \SilverShop\Model\Order $order */
        $order = $this->owner;
        $parts = [
            'ID' =>$order->ID,
            'Name' => $order->getName(),
            'Email' => $order->getLatestEmail()
        ];

        $this->owner->extend('updateDescriptionParts', $parts);

        return implode(' | ', array_filter($parts));
    }


    public function signupForNewsletter(Order $order){
        /*
        $mailchimp = new MailChimp('f3d42bd4ccf983c1630048d72f60e77d-us19');

        $list_id = '266204f431';

        $result = $mailchimp->post("lists/$list_id/members", [
            'email_address' => $order->Email,
            'status'        => 'pending',
            'merge_fields'  => [
                'FNAME' => $order->FirstName,
                'LNAME' => $order->Surname
            ]
        ]);

        return;
        */
    }
}