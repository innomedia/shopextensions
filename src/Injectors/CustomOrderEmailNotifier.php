<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Extension\ShopConfigExtension;
use SilverShop\Model\OrderStatusLog;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Config\Config;

class CustomOrderEmailNotifier extends OrderEmailNotifier{
    public $receipt = null;

    protected function buildEmail($template, $subject)
    {
        $from = ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email;
        $to = $this->order->getLatestEmail();
        $checkoutpage = CheckoutPage::get()->first();
        $completemessage = $checkoutpage ? $checkoutpage->PurchaseComplete : '';

        /**
         * @var Email $email
         */
        $filename = "";
//        $subject = "";

        if($this->order->InvoiceNumber()){
            $filename = 'Rechnung '.$this->order->InvoiceNumber().'.pdf';
//            $subject = 'Rechnung '.$this->order->InvoiceNumber();

            $email = Email::create()
                ->setHTMLTemplate($template)
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject);
            if(Config::inst()->get('ShopConfig', 'sendReceipt') != false){
                $email->addAttachmentFromData($this->order->PDFReceipt('binary'), $filename, 'application/pdf');
            }
        } else {
//            $subject = "Bestellung Nr. ".$this->order->getReference();
            $email = Email::create()
                ->setHTMLTemplate($template)
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject);
        }

        $email->setData(
            [
                'PurchaseCompleteMessage' => $completemessage,
                'Order' => $this->order,
                'BaseURL' => Director::absoluteBaseURL(),
            ]
        );

        return $email;
    }
    public function sendStatusChange($title, $note = null): bool|string
    {
        //if opting out of status changes
        if(!Config::inst()->get(OrderProcessor::class, 'send_statuschanges'))
        {
            return true;
        }
        //default shop behavios
        return parent::sendStatusChange($title,$note);
    }
    public function sendAdminNotification(): bool|string
    {
        $subject = _t(
            'SilverShop\ShopEmail.AdminNotificationSubject',
            'Order #{OrderNo} notification',
            '',
            array('OrderNo' => $this->order->Reference)
        );

        $filename = 'Lieferschein '.$this->order->InvoiceNumber().'.pdf';
        $to = "";
        if(SiteConfig::current_site_config()->AdminNotificationMail != '')
        {
            $to = SiteConfig::current_site_config()->AdminNotificationMail;
        } else if(Email::config()->shop_adminemail != null)
        {
            $to = Email::config()->shop_adminemail;
        }
        else
        {
             $to = Email::config()->admin_email;  
        }
        /*$email = $this->buildEmail('SilverShop/Model/Order_AdminNotificationEmail', $subject)
            ->setTo($to)
            ->addAttachmentFromData($this->order->PDFDeliverySlip('binary'), $filename, 'application/pdf');;*/
        $email = Email::create()
            ->setHTMLTemplate('SilverShop/Model/Order_AdminNotificationEmail')
            ->setFrom(ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email)
            ->setTo($to)
            ->setSubject($subject);
        if(Config::inst()->get('ShopConfig', 'sendReceipt') != false){
                $email->addAttachmentFromData($this->order->PDFReceipt('binary'), $filename, 'application/pdf');
            }
        $checkoutpage = CheckoutPage::get()->first();
        $email->setData(
            [
                'PurchaseCompleteMessage' => $checkoutpage ? $checkoutpage->PurchaseComplete : '',
                'Order' => $this->order,
                'BaseURL' => Director::absoluteBaseURL(),
            ]
        );

        if ($this->debugMode) {
            return $this->debug($email);
        } else {
            return $email->send();
        }
    }
}
