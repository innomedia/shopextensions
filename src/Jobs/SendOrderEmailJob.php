<?php

namespace ShopExtensions\Jobs;

use Psr\Log\LoggerInterface;
use SilverShop\Extension\ShopConfigExtension;
use SilverShop\Model\Order;
use SilverShop\Model\OrderStatusLog;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\SSViewer;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Queued job for sending order emails asynchronously
 */
class SendOrderEmailJob extends AbstractQueuedJob
{
    private static array $dependencies = [
        'Logger' => '%$' . LoggerInterface::class,
    ];

    protected LoggerInterface $logger;

    /**
     * @param int $orderID The Order ID
     * @param string $emailType Type of email: 'confirmation', 'receipt', 'admin_notification', 'cancel_notification', 'status_change'
     * @param array $params Additional parameters (e.g., title and note for status change)
     */
    public function __construct(?int $orderID = null, ?string $emailType = null, array $params = [])
    {
        if ($orderID) {
            $this->orderID = $orderID;
            $this->emailType = $emailType;
            $this->params = $params;
        }
    }

    public function getTitle(): string
    {
        $order = Order::get()->byID($this->orderID);
        if (!$order) {
            return 'Send Order Email (Order not found)';
        }

        return sprintf(
            'Send %s email for Order #%s',
            ucwords(str_replace('_', ' ', $this->emailType)),
            $order->Reference
        );
    }
    //DO NOT CHANGE THIS TO OUTPUT INT IT MUST BE STRING
    public function getJobType()
    {
        return QueuedJob::IMMEDIATE;
    }

    public function process(): void
    {
        $order = Order::get()->byID($this->orderID);

        if (!$order) {
            $this->addMessage("Order #{$this->orderID} not found");
            $this->isComplete = true;
            return;
        }

        try {
            switch ($this->emailType) {
                case 'confirmation':
                    $this->sendConfirmation($order);
                    break;
                case 'receipt':
                    $this->sendReceipt($order);
                    break;
                case 'admin_notification':
                    $this->sendAdminNotification($order);
                    break;
                case 'cancel_notification':
                    $this->sendCancelNotification($order);
                    break;
                case 'status_change':
                    $this->sendStatusChange($order, $this->params['title'] ?? '', $this->params['note'] ?? '');
                    break;
                default:
                    $this->addMessage("Unknown email type: {$this->emailType}");
                    break;
            }

            $this->isComplete = true;
        } catch (\Exception $e) {
            $this->addMessage("Error sending email: " . $e->getMessage());
            $this->logger->error("SendOrderEmailJob error: " . $e->getMessage());
            $this->isComplete = true;
        }
    }

    protected function sendConfirmation(Order $order): void
    {
        $subject = _t(
            'SilverShop\ShopEmail.ConfirmationSubject',
            'Order #{OrderNo} confirmation',
            '',
            ['OrderNo' => $order->Reference]
        );

        $email = $this->buildEmail($order, 'SilverShop/Model/Order_ConfirmationEmail', $subject);

        if (Config::inst()->get('SilverShop\Checkout\OrderEmailNotifier', 'bcc_confirmation_to_admin')) {
            $email->setBCC(Email::config()->admin_email);
        }

        $this->sendEmail($email, 'confirmation');
    }

    protected function sendReceipt(Order $order): void
    {
        $subject = _t(
            'SilverShop\ShopEmail.ReceiptSubject',
            'Order #{OrderNo} receipt',
            '',
            ['OrderNo' => $order->Reference]
        );

        $email = $this->buildEmail($order, 'SilverShop/Model/Order_ReceiptEmail', $subject);

        if (Config::inst()->get('SilverShop\Checkout\OrderEmailNotifier', 'bcc_receipt_to_admin')) {
            $email->setBCC(Email::config()->admin_email);
        }

        $this->sendEmail($email, 'receipt');
    }

    protected function sendAdminNotification(Order $order): void
    {
        $subject = _t(
            'SilverShop\ShopEmail.AdminNotificationSubject',
            'Order #{OrderNo} notification',
            '',
            ['OrderNo' => $order->Reference]
        );

        $filename = 'Lieferschein ' . $order->InvoiceNumber() . '.pdf';
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
            $email->addAttachmentFromData($order->PDFReceipt('binary'), $filename, 'application/pdf');
        }

        $checkoutpage = CheckoutPage::get()->first();
        $email->setData([
            'PurchaseCompleteMessage' => $checkoutpage ? $checkoutpage->PurchaseComplete : '',
            'Order' => $order,
            'BaseURL' => Director::absoluteBaseURL(),
        ]);

        $this->sendEmail($email, 'admin_notification');
    }

    protected function sendCancelNotification(Order $order): void
    {
        $email = Email::create()
            ->setSubject(_t(
                'SilverShop\ShopEmail.CancelSubject',
                'Order #{OrderNo} cancelled by member',
                '',
                ['OrderNo' => $order->Reference]
            ))
            ->setFrom(
                ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email
            )
            ->setTo(Email::config()->admin_email)
            ->setBody($order->renderWith(Order::class));

        $this->sendEmail($email, 'cancel_notification');
    }

    protected function sendStatusChange(Order $order, string $title, string $note = ''): void
    {
        $latestLog = null;

        if ($note === '' || $note === '0') {
            // Find the latest log message that hasn't been sent to the client yet
            $latestLog = OrderStatusLog::get()
                ->filter(["OrderID" => $order->ID])
                ->filter(["SentToCustomer" => 0])
                ->filter(["VisibleToCustomer" => 1])
                ->first();

            if ($latestLog) {
                $note = $latestLog->Note;
                $title = $latestLog->Title;
            }
        }

        // Save currently loaded theme stack and load frontend stack
        $adminThemeset = SSViewer::get_themes();
        SSViewer::set_themes(SSViewer::config()->uninherited('themes'));

        $from = ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email;
        $email = Email::create()
            ->setFrom($from)
            ->setSubject(_t('SilverShop\ShopEmail.StatusChangeSubject', 'SilverShop – {Title}', ['Title' => $title]))
            ->setTo($order->getLatestEmail())
            ->setHTMLTemplate('SilverShop/Model/Order_StatusEmail')
            ->setData([
                'Order' => $order,
                'Note' => $note,
                'FromEmail' => $from
            ]);

        if (Config::inst()->get('SilverShop\Checkout\OrderEmailNotifier', 'bcc_status_change_to_admin')) {
            $email->setBCC(Email::config()->admin_email);
        }

        $this->sendEmail($email, 'status_change');

        // Restore theme stack
        SSViewer::set_themes($adminThemeset);

        if ($latestLog) {
            // Mark as sent to customer
            $latestLog->SentToCustomer = true;
            $latestLog->write();
        }
    }

    protected function buildEmail(Order $order, string $template, string $subject): Email
    {
        $from = ShopConfigExtension::config()->email_from ? ShopConfigExtension::config()->email_from : Email::config()->admin_email;
        $to = $order->getLatestEmail();
        $checkoutpage = CheckoutPage::get()->first();
        $completemessage = $checkoutpage ? $checkoutpage->PurchaseComplete : '';

        $email = Email::create()
            ->setHTMLTemplate($template)
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject);

        // Add PDF attachment for invoiced orders
        if ($order->InvoiceNumber()) {
            $filename = 'Rechnung ' . $order->InvoiceNumber() . '.pdf';
            if (Config::inst()->get('ShopConfig', 'sendReceipt') != false) {
                $email->addAttachmentFromData($order->PDFReceipt('binary'), $filename, 'application/pdf');
            }
        }

        $email->setData([
            'PurchaseCompleteMessage' => $completemessage,
            'Order' => $order,
            'BaseURL' => Director::absoluteBaseURL(),
        ]);

        return $email;
    }

    protected function sendEmail(Email $email, string $type): void
    {
        try {
            $email->send();
            $this->addMessage("Successfully sent {$type} email");
        } catch (TransportExceptionInterface $e) {
            $message = "Error sending {$type} email: " . $e->getMessage();
            $this->addMessage($message);
            $this->logger->error($message);
            throw $e;
        }
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }
}
