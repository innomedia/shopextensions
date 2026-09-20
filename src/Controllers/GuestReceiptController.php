<?php

namespace ShopExtensions\Controllers;

use SilverShop\Model\Order;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\i18n\i18n;

/**
 * Invoice of an order without login, addressed by the order's receipt token (opt-in, see
 * _config/guestreceipt.yml.example and {@see \ShopExtensions\Receipt\OrderReceiptTokenExtension}):
 *
 *   GET rechnung/<token>              invoice PDF
 *   GET rechnung/<token>?format=html  the same invoice as an accessible web page (enable_html_view)
 *
 * Whoever has the token may see the invoice. There is no order ID in the link, so nothing to count
 * up and no second check to forget. Every failure (malformed/unknown token, order not in a receipt
 * status) gets the same 404, so the answer never reveals whether a token exists. Responses are never
 * cached, carry `X-Robots-Tag: noindex` and `Referrer-Policy: no-referrer`, and are rendered in the
 * locale of the ORDER.
 *
 * Both Director rule styles work: `'rechnung': ShopExtensions\Controllers\GuestReceiptController`
 * and `'rechnung//$Token!': …`.
 *
 * HTML view: when the class in `html_page_class` exists (default `Page`), a transient page record of
 * that class provides the site chrome (header, menus, footer) and the order is rendered into the
 * Layout template `ShopExtensions/Layout/GuestReceipt` (override it in the theme). `html_page_fields`
 * sets fields on that transient page (e.g. to hide page elements). Without such a class the standalone
 * template `ShopExtensions/GuestReceiptStandalone` is used. Hook: updateGuestReceiptHtmlData(&$data, $order).
 */
class GuestReceiptController extends Controller
{
    private static array $url_handlers = [
        '$Token!' => 'receipt',
    ];

    private static array $allowed_actions = [
        'receipt',
    ];

    /**
     * URL segment the links point to (must match the Director rule).
     *
     * @config
     */
    private static string $url_segment = 'rechnung';

    /**
     * Order statuses that have an invoice for guests.
     *
     * @config
     */
    private static array $receipt_statuses = ['Paid', 'Sent', 'Complete'];

    /**
     * 'attachment' (download) or 'inline' (opens in the browser tab).
     *
     * @config
     */
    private static string $content_disposition = 'attachment';

    /**
     * PDF file name; {number} = invoice number (or order reference when there is none).
     *
     * @config
     */
    private static string $filename_pattern = 'Rechnung_{number}.pdf';

    /**
     * Offer the accessible HTML view (?format=html) and the GuestReceiptHtmlLink() getters.
     *
     * @config
     */
    private static bool $enable_html_view = false;

    /**
     * GuestReceiptLink()/GuestReceiptHtmlLink() return absolute URLs (the Absolute* getters always do).
     *
     * @config
     */
    private static bool $absolute_links = false;

    /**
     * Page class that provides the site chrome for the HTML view ('' = standalone template).
     *
     * @config
     */
    private static string $html_page_class = 'Page';

    /**
     * Field values set on the transient page of the HTML view, e.g. {ShowBreadcrumbs: false}.
     *
     * @config
     */
    private static array $html_page_fields = [];

    /**
     * @return string[]
     */
    public static function receiptStatuses(): array
    {
        return array_values((array) Config::inst()->get(self::class, 'receipt_statuses'));
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        // The rule 'rechnung//$Token!' routes straight to index().
        if ($request->param('Token')) {
            return $this->receipt($request);
        }

        return $this->httpError(404);
    }

    public function receipt(HTTPRequest $request): HTTPResponse
    {
        // A receipt belongs to exactly one person: never cache.
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $order = $this->orderForToken((string) $request->param('Token'));
        if (!$order) {
            return $this->httpError(404);
        }

        if ($order->Locale) {
            i18n::set_locale($order->Locale);
        }

        if ($request->getVar('format') === 'html' && static::config()->get('enable_html_view')) {
            return $this->htmlResponse($order);
        }

        $binary = $order->PDFReceipt('string');
        if (!$binary) {
            return $this->httpError(500, _t(self::class . '.PdfFailed', 'Die Rechnung konnte nicht erzeugt werden.'));
        }

        $number = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($order->InvoiceNumber() ?: $order->Reference));
        $name = str_replace('{number}', $number, (string) static::config()->get('filename_pattern'));
        $disposition = static::config()->get('content_disposition') === 'inline' ? 'inline' : 'attachment';

        $response = HTTPResponse::create($binary);
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', $disposition . '; filename="' . $name . '"');

        return $this->privateHeaders($response);
    }

    /**
     * The order for a token, or null. Looked up in SQL and compared again with hash_equals(): the SQL
     * comparison is neither constant-time nor case-sensitive (MySQL collation). The status is checked
     * here, not only in templates: the link stays, the status may change (cancellation).
     */
    public function orderForToken(string $token): ?Order
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        /** @var Order|null $order */
        $order = Order::get()->filter('ReceiptToken', $token)->first();
        if (!$order || !$order->exists() || !hash_equals((string) $order->ReceiptToken, $token)) {
            return null;
        }

        return in_array($order->Status, self::receiptStatuses(), true) ? $order : null;
    }

    protected function htmlResponse(Order $order): HTTPResponse
    {
        $title = $order->InvoiceNumber()
            ? _t(self::class . '.InvoiceTitle', 'Rechnung {number}', ['number' => $order->InvoiceNumber()])
            : _t(self::class . '.OrderTitle', 'Bestellung {number}', ['number' => $order->Reference]);

        $data = [
            'Order' => $order,
            'Title' => $title,
            'MetaTitle' => $title,
            'InvoicePdfLink' => $order->GuestReceiptLink(),
        ];
        $this->extend('updateGuestReceiptHtmlData', $data, $order);

        $pageClass = (string) static::config()->get('html_page_class');
        if ($pageClass !== '' && ClassInfo::exists($pageClass)) {
            // Same pattern as the Security controller: a transient page record provides the page
            // controller (requirements, menus, title) for the site chrome.
            $page = $pageClass::create();
            $page->Title = $title;
            $page->URLSegment = (string) static::config()->get('url_segment');
            $page->ID = -1;
            foreach ((array) static::config()->get('html_page_fields') as $field => $value) {
                $page->$field = $value;
            }

            $controller = ModelAsController::controller_for($page);
            $controller->setRequest($this->getRequest());
            $controller->doInit();

            $body = $controller->customise($data)->renderWith(['ShopExtensions/GuestReceipt', $pageClass]);
        } else {
            $body = $this->customise($data)->renderWith('ShopExtensions/GuestReceiptStandalone');
        }

        // doInit() may have configured caching for a normal page – a receipt belongs to one person.
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $response = HTTPResponse::create($body);
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');

        return $this->privateHeaders($response);
    }

    protected function privateHeaders(HTTPResponse $response): HTTPResponse
    {
        $response->addHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->addHeader('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function Link($action = null)
    {
        return Controller::join_links((string) static::config()->get('url_segment'), $action);
    }
}
