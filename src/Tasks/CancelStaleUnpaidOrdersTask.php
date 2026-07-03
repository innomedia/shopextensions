<?php

namespace ShopExtensions\Tasks;

use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * Auto-cancels orders that were placed (Cart→Unpaid) but never paid, so they don't linger forever
 * once the customer walked away from the payment provider.
 *
 * Context: with Order.place_before_payment on, an order becomes a real Unpaid record BEFORE the
 * payment happens (protecting delayed SEPA/async payments from CartCleanupTask, which only deletes
 * Status='Cart'). The flip side is that abandoned Mollie/card checkouts leave Unpaid orders behind.
 * This task retires those after a grace period.
 *
 * Selection (all must hold):
 *  - Status = 'Unpaid'
 *  - LastEdited older than cancel_after_days (default 14)
 *  - TotalOutstanding(true) > 0  → not actually settled
 *  - InvoiceNumber empty  → the decisive filter: the automation touches ONLY number-less orders
 *    (pure Mollie/card abandoners), so it can NEVER punch a gap into the invoice range. Orders that
 *    already carry a number (Manual/"on account", or tiles flagged InvoiceOnPlacement) are left for
 *    the manual dunning process (open receivable, possibly a real credit note with accounting).
 *
 * Action: set Status → 'MemberCancelled' (deliberately NOT AdminCancelled, so the Storno feature is
 * not triggered) and send the customer a notice mail. We cancel instead of delete: the record stays
 * and the move is REVERSIBLE — a late webhook (onCaptured → completePayment) still finds the order
 * and heals it back to Paid (self-healing).
 *
 * Opt-in via cron; safe to re-run (idempotent — cancelled orders no longer match). Run with
 *   vendor/bin/sake dev/tasks/cancel-stale-unpaid-orders
 */
class CancelStaleUnpaidOrdersTask extends BuildTask
{
    protected string $title = 'Cancel stale unpaid orders';

    protected static string $description =
        'Cancel (MemberCancelled) number-less Unpaid orders older than cancel_after_days and notify the customer.';

    // SS6 derives the task URL/CLI address from $commandName (PolyCommand); the legacy
    // BuildTask::$segment is ignored → without this the address would be the long class name.
    protected static string $commandName = 'cancel-stale-unpaid-orders';

    /**
     * Grace period in days: an Unpaid order is only cancelled once it hasn't changed for this long.
     *
     * @config
     */
    private static int $cancel_after_days = 14;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = (int) $this->config()->get('cancel_after_days');
        $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - $days * 86400);

        $candidates = Order::get()
            ->filter('Status', 'Unpaid')
            ->filter('LastEdited:LessThan', $cutoff);

        $cancelled = 0;
        $skipped = 0;

        foreach ($candidates as $order) {
            // Never touch a number-bearing order (Manual/"on account" or InvoiceOnPlacement tiles):
            // those go to the manual dunning process, and cancelling them could break the number range.
            if ($order->InvoiceNumber) {
                $skipped++;
                continue;
            }

            // Skip anything that is in fact settled (a payment may have landed without a status flip).
            if ((float) $order->TotalOutstanding(true) <= 0.0) {
                $skipped++;
                continue;
            }

            // MemberCancelled (not AdminCancelled) — reversible, and it does NOT trigger the Storno feature.
            $order->Status = 'MemberCancelled';
            $order->write();
            $cancelled++;

            $notified = $this->notifyCustomer($order);
            $output->writeln(sprintf(
                'Order #%s (%s): → MemberCancelled%s',
                $order->ID,
                $order->Reference,
                $notified ? ' + Hinweis-Mail' : ' (keine Mail: keine E-Mail-Adresse/Fehler)'
            ));
        }

        $output->writeln(sprintf(
            'Done. Cancelled: %d, skipped (numbered/settled): %d, grace: %d days.',
            $cancelled,
            $skipped,
            $days
        ));
        return 0;
    }

    /**
     * Send the customer a "we cancelled your open order, you can re-order any time" notice.
     *
     * @return bool True when the mail was sent, false if the order has no recipient or sending failed.
     */
    protected function notifyCustomer(Order $order): bool
    {
        $to = $order->getLatestEmail();
        if (!$to) {
            return false;
        }

        $siteConfig = SiteConfig::current_site_config();
        $from = $siteConfig->AdminEmail ?: Email::config()->admin_email;

        $email = Email::create()
            ->setHTMLTemplate('ShopExtensions/Email/CancelStaleUnpaidEmail')
            ->setTo($to)
            ->setSubject(sprintf('Ihre offene Bestellung %s wurde storniert', $order->Reference))
            ->setFrom($from, $siteConfig->AdminName ?: null)
            ->setData([
                'Order' => $order,
                'SiteConfig' => $siteConfig,
                'FromEmail' => $from,
                'BaseURL' => Director::absoluteBaseURL(),
            ]);

        try {
            $email->send();
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }
}