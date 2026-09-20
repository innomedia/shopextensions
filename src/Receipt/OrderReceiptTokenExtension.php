<?php

namespace ShopExtensions\Receipt;

use ShopExtensions\Controllers\GuestReceiptController;
use SilverShop\Model\Order;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DB;

/**
 * The key a guest uses to get the invoice of an order without logging in: `/rechnung/<token>`
 * (opt-in, see _config/guestreceipt.yml.example).
 *
 * The regular receipt routes (account `receipt/<ID>`, `OrderReceipt/StreamReceipt/<ID>`) are bound to
 * the member who owns the order or a CMS user. A guest order has no member, and loosening that check
 * would be an IDOR (Order::canView() is true by default and IDs count up). Every order therefore gets
 * its own unguessable address:
 *
 * - 32 random bytes = 64 hex characters, indexed, in the PATH (query strings end up in logs).
 * - Session independent and without expiry (an invoice is still needed months later); revoke a link
 *   by clearing the field.
 * - Only for the statuses in {@see GuestReceiptController::$receipt_statuses} (default Paid, Sent,
 *   Complete) – checked again in the controller.
 *
 * Column name `ReceiptToken` is the one the project copies used (ghee, tietgeverlag, vogelwerk), so
 * existing links keep working after the switch. Remove the project copy in the same step.
 *
 * Links: GuestReceiptLink() (PDF), GuestReceiptHtmlLink() (accessible web page, only when
 * GuestReceiptController.enable_html_view is on) and their Absolute* variants for mails.
 * GuestReceiptLink() is relative unless GuestReceiptController.absolute_links is true (vogelwerk's
 * copy returned an absolute URL).
 *
 * @property Order  $owner
 * @property string $ReceiptToken
 */
class OrderReceiptTokenExtension extends Extension
{
    private static array $db = [
        'ReceiptToken' => 'Varchar(64)',
    ];

    private static array $indexes = [
        'ReceiptToken' => true,
    ];

    /**
     * Address of this order's invoice PDF, or null as long as there is none (not paid yet).
     */
    public function GuestReceiptLink(): ?string
    {
        $link = $this->relativeReceiptLink();
        if ($link === null) {
            return null;
        }

        return Config::inst()->get(GuestReceiptController::class, 'absolute_links') ? Director::absoluteURL($link) : $link;
    }

    /**
     * Absolute variant for mails (queued jobs run without a request, so SS_BASE_URL must be set).
     */
    public function AbsoluteGuestReceiptLink(): ?string
    {
        $link = $this->relativeReceiptLink();

        return $link === null ? null : Director::absoluteURL($link);
    }

    /**
     * Accessible HTML view of the same invoice (`/rechnung/<token>?format=html`). dompdf produces an
     * untagged PDF, so link this next to every PDF link. Null when the HTML view is not enabled.
     */
    public function GuestReceiptHtmlLink(): ?string
    {
        if (!Config::inst()->get(GuestReceiptController::class, 'enable_html_view')) {
            return null;
        }
        $link = $this->relativeReceiptLink();
        if ($link === null) {
            return null;
        }
        $link .= '?format=html';

        return Config::inst()->get(GuestReceiptController::class, 'absolute_links') ? Director::absoluteURL($link) : $link;
    }

    public function AbsoluteGuestReceiptHtmlLink(): ?string
    {
        if (!Config::inst()->get(GuestReceiptController::class, 'enable_html_view')) {
            return null;
        }
        $link = $this->relativeReceiptLink();

        return $link === null ? null : Director::absoluteURL($link . '?format=html');
    }

    /**
     * Whether the order is in a status that has an invoice for guests.
     */
    public function hasGuestReceipt(): bool
    {
        return in_array($this->getOwner()->Status, GuestReceiptController::receiptStatuses(), true);
    }

    /**
     * Fired by OrderProcessor::placeOrder() right before the order is written – only set the field.
     */
    public function onPlaceOrder(): void
    {
        if (!$this->getOwner()->ReceiptToken) {
            $this->getOwner()->ReceiptToken = self::generateToken();
        }
    }

    /**
     * Backfill existing orders that have a receipt – one by one through the ORM: every order needs its
     * OWN random value, a single UPDATE statement would give all of them the same one.
     */
    public function onRequireDefaultRecords(): void
    {
        $count = 0;
        foreach (Order::get()->filter('Status', GuestReceiptController::receiptStatuses()) as $order) {
            if ($order->ReceiptToken) {
                continue;
            }
            $order->ReceiptToken = self::generateToken();
            $order->write();
            $count++;
        }

        if ($count > 0) {
            DB::alteration_message(sprintf('%d order(s) got a receipt token.', $count), 'created');
        }
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function relativeReceiptLink(): ?string
    {
        if (!$this->hasGuestReceipt()) {
            return null;
        }
        $token = $this->ensureReceiptToken();
        if ($token === '') {
            return null;
        }

        return Controller::join_links(
            Director::baseURL(),
            (string) Config::inst()->get(GuestReceiptController::class, 'url_segment'),
            $token
        );
    }

    /**
     * Stragglers: an order paid after the last dev/build (async provider notification) may have no
     * token yet.
     */
    private function ensureReceiptToken(): string
    {
        $order = $this->getOwner();
        if ($order->ReceiptToken) {
            return (string) $order->ReceiptToken;
        }
        if (!$order->isInDB()) {
            return '';
        }
        $order->ReceiptToken = self::generateToken();
        $order->write();

        return (string) $order->ReceiptToken;
    }
}
