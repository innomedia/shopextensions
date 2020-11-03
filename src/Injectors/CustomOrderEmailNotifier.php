<?php

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Extension\ShopConfigExtension;
use SilverShop\Model\OrderStatusLog;

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
        $subject = "";

        $filename = 'Rechnung '.$this->order->InvoiceNumber().'.pdf';
        $subject = 'Rechnung '.$this->order->InvoiceNumber();

        $email = Email::create()
            ->setHTMLTemplate($template)
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->addAttachmentFromData($this->order->PDFReceipt('binary'), $filename, 'application/pdf');

        $email->setData(
            [
                'PurchaseCompleteMessage' => $completemessage,
                'Order' => $this->order,
                'BaseURL' => Director::absoluteBaseURL(),
            ]
        );

        return $email;
    }

    public function sendAdminNotification()
    {
        $subject = _t(
            'SilverShop\ShopEmail.AdminNotificationSubject',
            'Order #{OrderNo} notification',
            '',
            array('OrderNo' => $this->order->Reference)
        );

        $filename = 'Lieferschein '.$this->order->InvoiceNumber().'.pdf';

        $email = $this->buildEmail('SilverShop/Model/Order_AdminNotificationEmail', $subject)
            ->setTo(Email::config()->admin_email)
            ->addAttachmentFromData($this->order->PDFDeliverySlip('binary'), $filename, 'application/pdf');;
        if ($this->debugMode) {
            return $this->debug($email);
        } else {
            return $email->send();
        }
    }
}
