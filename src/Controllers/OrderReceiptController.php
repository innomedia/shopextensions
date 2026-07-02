<?php

namespace ShopExtensions\Controllers;

use SilverShop\Model\Order;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Streams an order's invoice / delivery-slip PDF on a dedicated, access-controlled route
 * (default segment "OrderReceipt", registered in _config/routes.yml).
 *
 * This replaces the previous, unsafe approach where StreamReceipt/StreamDeliverySlip lived as
 * allowed actions on every PageController and echoed the PDF for ANY order ID with no permission
 * check at all (an IDOR: any visitor could enumerate order IDs and download foreign invoices).
 *
 * Access rule ({@see self::canDownload()}): a receipt may only be downloaded by
 *   - the Member the order belongs to (Order.MemberID), or
 *   - a CMS/staff user (CMS access or ADMIN).
 * Everyone else gets a login redirect (if logged out) or a 403 (if logged in but not entitled).
 *
 * Note: we do NOT rely on Order::canView(), which returns true by default in SilverShop and is
 * therefore not a real gate.
 */
class OrderReceiptController extends Controller
{
    private static $url_segment = 'OrderReceipt';

    private static $allowed_actions = [
        'StreamReceipt',
        'StreamDeliverySlip',
        'StreamStorno',
        'PreviewReceipt',
    ];

    /**
     * Stream the invoice PDF for the order identified by the "ID" URL parameter.
     */
    public function StreamReceipt(HTTPRequest $request): HTTPResponse
    {
        $order = null;
        if ($deny = $this->guard($request, $order)) {
            return $deny;
        }
        return $this->pdfDownload($order, 'receipt');
    }

    /**
     * Stream the delivery-slip PDF for the order identified by the "ID" URL parameter.
     */
    public function StreamDeliverySlip(HTTPRequest $request): HTTPResponse
    {
        // Opt-in feature: 404 when the project hasn't enabled delivery slips.
        if (!Order::config()->get('enable_delivery_slip')) {
            return $this->httpError(404, 'Lieferschein ist nicht aktiviert.');
        }

        $order = null;
        if ($deny = $this->guard($request, $order)) {
            return $deny;
        }
        return $this->pdfDownload($order, 'deliveryslip');
    }

    /**
     * Stream the cancellation invoice (Stornorechnung) PDF for the order identified by "ID".
     */
    public function StreamStorno(HTTPRequest $request): HTTPResponse
    {
        // Opt-in feature: 404 when the project hasn't enabled the Storno feature.
        if (!Order::config()->get('enable_storno')) {
            return $this->httpError(404, 'Storno ist nicht aktiviert.');
        }

        $order = null;
        if ($deny = $this->guard($request, $order)) {
            return $deny;
        }

        // Only admin-cancelled orders that actually carry a Storno document number have one.
        if (!$order->hasMethod('IsStorno') || !$order->IsStorno()) {
            return $this->httpError(404, 'Keine Stornorechnung vorhanden.');
        }

        return $this->pdfDownload($order, 'storno');
    }

    /**
     * Return a small PNG preview (first page of the invoice PDF, 400px wide) for embedding in the
     * CMS order view. Rasterised with ImageMagick/Ghostscript. Same access rule as the download.
     */
    public function PreviewReceipt(HTTPRequest $request): HTTPResponse
    {
        $order = null;
        if ($deny = $this->guard($request, $order)) {
            return $deny;
        }

        i18n::set_locale($order->Locale);
        $pdf = $order->PDFReceipt('string');
        if (!$pdf) {
            return $this->httpError(500, 'PDF konnte nicht erzeugt werden.');
        }

        $png = $this->rasteriseFirstPage($pdf, 400);
        if ($png === null) {
            return $this->httpError(500, 'Vorschau konnte nicht erzeugt werden.');
        }

        $response = HTTPResponse::create($png);
        $response->addHeader('Content-Type', 'image/png');
        // Preview only, keep it out of shared caches (it is access-controlled per user).
        $response->addHeader('Cache-Control', 'private, max-age=0, no-store');
        return $response;
    }

    /**
     * Resolve + authorise the order for the current request.
     *
     * @param Order|null $order Set to the resolved order on success (by reference).
     * @return HTTPResponse|null An error/redirect response to return, or null when access is granted.
     */
    protected function guard(HTTPRequest $request, ?Order &$order): ?HTTPResponse
    {
        $id = (int) $request->param('ID');
        if ($id <= 0) {
            return $this->httpError(404, 'Keine gültige Bestell-ID.');
        }

        $order = Order::get()->byID($id);
        if (!$order || !$order->exists()) {
            return $this->httpError(404, 'Bestellung nicht gefunden.');
        }

        if (!$this->canDownload($order)) {
            // Logged out → give them a chance to log in and come back; logged in but not
            // entitled → hard 403 (no information leak about the order's existence beyond this).
            if (!Security::getCurrentUser()) {
                return $this->redirect(
                    Security::login_url() . '?BackURL=' . urlencode($request->getURL(true))
                );
            }
            return $this->httpError(403, 'Kein Zugriff auf diesen Beleg.');
        }

        return null;
    }

    /**
     * Build a PDF download response for an already-authorised order.
     *
     * @param string $kind 'receipt' or 'deliveryslip'
     */
    protected function pdfDownload(Order $order, string $kind): HTTPResponse
    {
        i18n::set_locale($order->Locale);

        switch ($kind) {
            case 'deliveryslip':
                $binary = $order->PDFDeliverySlip('string');
                break;
            case 'storno':
                $binary = $order->PDFStorno('string');
                break;
            default:
                $binary = $order->PDFReceipt('string');
        }

        if (!$binary) {
            return $this->httpError(500, 'PDF konnte nicht erzeugt werden.');
        }

        $labels = ['receipt' => 'Rechnung', 'deliveryslip' => 'Lieferschein', 'storno' => 'Stornorechnung'];
        $label = $labels[$kind] ?? 'Rechnung';
        $filename = $label . '_' . $order->Reference . '_' . date('Y-m-d') . '.pdf';

        $response = HTTPResponse::create($binary);
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->addHeader('Content-Transfer-Encoding', 'binary');
        return $response;
    }

    /**
     * Rasterise the first page of a PDF to a PNG of the given width using ImageMagick.
     *
     * @param string $pdf   Raw PDF bytes.
     * @param int    $width Target width in px (height scales proportionally).
     * @return string|null PNG bytes, or null on failure (e.g. Imagick/Ghostscript missing).
     */
    protected function rasteriseFirstPage(string $pdf, int $width): ?string
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $imagick = new \Imagick();
            // Resolution must be set BEFORE reading so Ghostscript renders crisply, not upscaled.
            $imagick->setResolution(150, 150);
            // White background so transparent PDF areas don't turn black in the PNG.
            $imagick->setBackgroundColor(new \ImagickPixel('white'));
            $imagick->readImageBlob($pdf);
            $imagick->setIteratorIndex(0);
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->thumbnailImage($width, 0);
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            return $blob;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A receipt may be downloaded only by the owning Member or by a CMS/staff user.
     */
    protected function canDownload(Order $order): bool
    {
        $member = Security::getCurrentUser();

        // Owner of the order.
        if ($member instanceof Member && $order->MemberID && (int) $order->MemberID === (int) $member->ID) {
            return true;
        }

        // Any CMS/back-office user (covers shop staff and full admins).
        if (Permission::check('CMS_ACCESS_LeftAndMain') || Permission::check('ADMIN')) {
            return true;
        }

        return false;
    }
}
