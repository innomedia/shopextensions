<?php

namespace ShopExtensions\Payment;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;

/**
 * Lets only ONE payment completion per order run at a time. Registered on
 * {@see \SilverStripe\Omnipay\Service\PurchaseService} in _config/shopextensions.yml (bugfix, on by
 * default, MySQL/MariaDB only – other databases are skipped).
 *
 * Mollie calls the webhook and redirects the customer back within the same second. Both requests run
 * through PurchaseService::complete(), both still see "PendingPurchase", both mark the order paid and
 * each queues its own receipt and admin mail, so every mail arrives twice.
 *
 * A named lock (GET_LOCK) per order, taken right before the provider status request, makes the second
 * request wait until the first connection ends. By then the order is written, and the second request
 * finds the ORDER paid (silvershop reloads it in onCaptured()), so no second mail is sent. The lock is
 * released with the connection (end of request). On timeout the completion continues without a lock
 * and a warning is logged.
 *
 * Config:
 *   enabled       false switches the lock off
 *   lock_timeout  seconds the second request waits at most (Mollie expects a webhook answer within 15 s)
 *   lock_prefix   prefix of the lock name
 * Hook: updatePaymentLockName(&$name, $payment) on the PurchaseService, e.g. to lock per subscription.
 *
 * Limitation: PurchaseService::complete() checks `Status === 'Captured'` BEFORE this hook on its
 * in-memory Payment, so the waiting request still asks the provider again and adds a second
 * PaymentMessage / runs onCaptured() once more (harmless for mails, see above). A full fix needs that
 * check after the lock, i.e. upstream in silverstripe-omnipay.
 *
 * Replaces the project copies Tietge\Books\Checkout\PaymentCompletionLockExtension and
 * Ghee\Shop\Checkout\PaymentCompletionLockExtension (remove them when updating).
 *
 * @property \SilverStripe\Omnipay\Service\PurchaseService $owner
 */
class PaymentCompletionLockExtension extends Extension
{
    private static bool $enabled = true;

    private static int $lock_timeout = 10;

    private static string $lock_prefix = 'shopext_payment_complete_';

    public function onBeforeCompletePurchase(array &$gatewayData): void
    {
        if (!Config::inst()->get(self::class, 'enabled')) {
            return;
        }
        if (!in_array(DB::get_conn()->getDatabaseServer(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $payment = $this->getOwner()->getPayment();
        if (!$payment) {
            return;
        }

        $name = (string) Config::inst()->get(self::class, 'lock_prefix')
            . ($payment->OrderID ? $payment->OrderID : 'payment_' . $payment->ID);
        $this->getOwner()->extend('updatePaymentLockName', $name, $payment);
        // MySQL lock names are limited to 64 characters.
        if (strlen($name) > 64) {
            $name = substr($name, 0, 31) . md5($name);
        }

        $timeout = (int) Config::inst()->get(self::class, 'lock_timeout');
        $acquired = DB::prepared_query('SELECT GET_LOCK(?, ?)', [$name, $timeout])->value();

        if ((int) $acquired !== 1) {
            Injector::inst()->get(LoggerInterface::class)->warning(sprintf(
                'Payment completion without lock: %s not acquired after %d s (payment %d).',
                $name,
                $timeout,
                $payment->ID
            ));
        }
    }
}
