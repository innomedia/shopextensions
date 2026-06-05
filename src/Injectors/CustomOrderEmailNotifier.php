<?php

namespace ShopExtensions;

use ShopExtensions\Jobs\SendOrderEmailJob;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Extension\ShopConfigExtension;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CustomOrderEmailNotifier extends OrderEmailNotifier
{
    public $receipt = null;

    /**
     * Whether to queue emails or send immediately
     * Set to false for debugging or if QueuedJobs is not available
     */
    private static bool $use_queued_jobs = true;

    /**
     * Whether to send status change emails
     */
    private static bool $send_statuschanges = true;

    /**
     * Queue or send email based on configuration
     */
    protected function queueOrSendEmail(string $emailType, array $params = []): bool|string
    {
        // If queued jobs disabled, send directly
        if (!self::config()->get('use_queued_jobs')) {
            return match($emailType) {
                'confirmation' => parent::sendConfirmation(),
                'receipt' => parent::sendReceipt(),
                'admin_notification' => $this->sendAdminNotificationDirect(),
                'cancel_notification' => parent::sendCancelNotification(),
                'status_change' => parent::sendStatusChange($params['title'] ?? '', $params['note'] ?? ''),
                default => false,
            };
        }

        // Queue the email job
        $job = Injector::inst()->create(
            SendOrderEmailJob::class,
            $this->order->ID,
            $emailType,
            $params
        );

        $queuedJobService = Injector::inst()->get(QueuedJobService::class);
        $queuedJobService->queueJob($job);

        return true;
    }

    /**
     * Send confirmation email via queued job
     */
    public function sendConfirmation(): bool|string
    {
        return $this->queueOrSendEmail('confirmation');
    }

    /**
     * Send receipt email via queued job
     */
    public function sendReceipt(): bool|string
    {
        return $this->queueOrSendEmail('receipt');
    }

    /**
     * Send admin notification via queued job
     */
    public function sendAdminNotification(): bool|string
    {
        return $this->queueOrSendEmail('admin_notification');
    }

    /**
     * Send cancel notification via queued job
     */
    public function sendCancelNotification(): bool|string
    {
        return $this->queueOrSendEmail('cancel_notification');
    }

    /**
     * Send status change email via queued job
     */
    public function sendStatusChange($title, $note = null): bool|string
    {
        // Check if status changes are enabled
        if (!self::config()->get('send_statuschanges')) {
            return true;
        }

        return $this->queueOrSendEmail('status_change', [
            'title' => $title,
            'note' => $note ?? ''
        ]);
    }

    /**
     * Direct send for admin notification (used when debugging or queued jobs disabled)
     */
    protected function sendAdminNotificationDirect(): bool|string
    {
        $subject = _t(
            'SilverShop\ShopEmail.AdminNotificationSubject',
            'Order #{OrderNo} notification',
            '',
            ['OrderNo' => $this->order->Reference]
        );

        $filename = 'Lieferschein ' . $this->order->InvoiceNumber() . '.pdf';
        $to = "";

        if (SiteConfig::current_site_config()->AdminNotificationMail != '') {
            $to = SiteConfig::current_site_config()->AdminNotificationMail;
        } else if (Email::config()->shop_adminemail != null) {
            $to = Email::config()->shop_adminemail;
        } else {
            $to = Email::config()->admin_email;
        }

        $email = Email::create()
            ->setHTMLTemplate('SilverShop/Model/Order_AdminNotificationEmail')
            ->setFrom(ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email)
            ->setTo($to)
            ->setSubject($subject);

        if (Config::inst()->get('ShopConfig', 'sendReceipt') != false) {
            $email->addAttachmentFromData($this->order->PDFReceipt('binary'), $filename, 'application/pdf');
        }

        $checkoutpage = CheckoutPage::get()->first();
        $email->setData([
            'PurchaseCompleteMessage' => $checkoutpage ? $checkoutpage->PurchaseComplete : '',
            'Order' => $this->order,
            'BaseURL' => Director::absoluteBaseURL(),
        ]);

        if ($this->debugMode) {
            return $this->debug($email);
        }

        try {
            $email->send();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('OrderEmailNotifier.sendAdminNotification: error sending email in ' . __FILE__ . ' line ' . __LINE__ . ": {$e->getMessage()}");
            return false;
        }

        return true;
    }

    /**
     * Legacy buildEmail method - kept for compatibility
     */
    protected function buildEmail($template, $subject): \SilverStripe\Control\Email\Email
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
}
