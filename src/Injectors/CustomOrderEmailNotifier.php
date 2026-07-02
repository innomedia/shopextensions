<?php

namespace ShopExtensions;

use ShopExtensions\Jobs\SendOrderEmailJob;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Replaces SilverShop's OrderEmailNotifier (via Injector, see _config/shopextensions.yml)
 * to add two things the base mailer lacks:
 *
 *  1. Optional asynchronous sending: order emails are dispatched through a
 *     {@see SendOrderEmailJob} queued job instead of being sent inline, so a slow SMTP
 *     server can't block or break the checkout/payment callback. Toggle with the
 *     `use_queued_jobs` config flag (set false to send directly, e.g. for debugging).
 *
 *  2. Per-mail-type control of the invoice PDF attachment, see {@see self::shouldAttachInvoice()}.
 *
 * All public send* methods keep the parent signatures so callers are unaffected.
 */
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
     * Per-mail-type control of whether the invoice PDF is attached.
     * These act on top of the global ShopConfig.sendReceipt master switch, so a project
     * that never sets them keeps the previous behaviour (attach whenever sendReceipt is on).
     */
    private static bool $attach_invoice_to_receipt = true;
    private static bool $attach_invoice_to_admin = true;
    private static bool $attach_invoice_to_confirmation = false;

    /**
     * Decide whether the invoice PDF should be attached to a given mail type.
     * Combines the global ShopConfig.sendReceipt master switch with the per-type flag.
     *
     * @param string $type One of 'receipt', 'confirmation', 'admin_notification'
     */
    public static function shouldAttachInvoice(string $type): bool
    {
        // Master switch, kept for backwards compatibility with existing projects
        if (Config::inst()->get('ShopConfig', 'sendReceipt') == false) {
            return false;
        }

        $key = match ($type) {
            'receipt' => 'attach_invoice_to_receipt',
            'confirmation' => 'attach_invoice_to_confirmation',
            'admin_notification' => 'attach_invoice_to_admin',
            default => null,
        };

        // Unknown type: preserve prior behaviour (attach when sendReceipt is on)
        if ($key === null) {
            return true;
        }

        return (bool) self::config()->get($key);
    }

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

        $siteConfig = SiteConfig::current_site_config();
        
        $email = Email::create()
            ->setHTMLTemplate('SilverShop/Model/Order_AdminNotificationEmail')
            ->setTo($to)
            ->setSubject($subject)
            ->setFrom(
                $siteConfig->AdminEmail ?: Email::config()->admin_email,
                $siteConfig->AdminName ?: null
            );

        if (self::shouldAttachInvoice('admin_notification')) {
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
        $siteConfig = SiteConfig::current_site_config();
        $to = $this->order->getLatestEmail();
        $checkoutpage = CheckoutPage::get()->first();
        $completemessage = $checkoutpage ? $checkoutpage->PurchaseComplete : '';
        $fromEmail = $siteConfig->AdminEmail ?: Email::config()->admin_email;
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
                ->setTo($to)
                ->setSubject($subject)
                ->setFrom(
                    $fromEmail
                );
            if(Config::inst()->get('ShopConfig', 'sendReceipt') != false){
                $email->addAttachmentFromData($this->order->PDFReceipt('binary'), $filename, 'application/pdf');
            }
        } else {
//            $subject = "Bestellung Nr. ".$this->order->getReference();
            $email = Email::create()
                ->setHTMLTemplate($template)
                ->setTo($to)
                ->setSubject($subject)
                ->setFrom(
                    $fromEmail
                );
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
