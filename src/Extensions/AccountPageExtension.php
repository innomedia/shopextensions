<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverStripe\i18n\i18n;
use SilverStripe\Core\Extension;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Extension for the SilverShop AccountPageController.
 *
 * Adds member-facing order actions to the account area: streaming an order's
 * invoice PDF for download and rendering an order info page (guarded by a
 * canView permission check).
 *
 * @property \SilverShop\Page\AccountPageController $owner
 */
class AccountPageControllerExtension extends Extension{
    private static $allowed_actions = array(
        'receipt' => true,
        'infos' => true,
    );

    /**
     * Stream the invoice PDF for the requested order as a file download.
     *
     * Access is restricted to the Member the order belongs to or a CMS/staff user — the same
     * rule as {@see \ShopExtensions\Controllers\OrderReceiptController}. Without this check any
     * logged-in customer could download a foreign order's invoice by guessing its ID (IDOR),
     * because Order::canView() returns true by default and is not a real gate.
     *
     * @param HTTPRequest $request The current request, providing the order ID.
     * @return HTTPResponse PDF download, or a 403/404 error response.
     */
    public function receipt(HTTPRequest $request){
        $id = (int) $request->param('ID');
        /** @var Order|null $order */
        $order = $id > 0 ? Order::get()->byID($id) : null;
        if (!$order || !$order->exists()) {
            return $this->owner->httpError(404, 'Bestellung nicht gefunden.');
        }

        $member = Security::getCurrentUser();
        $owns  = $member instanceof Member && $order->MemberID && (int) $order->MemberID === (int) $member->ID;
        $staff = Permission::check('CMS_ACCESS_LeftAndMain') || Permission::check('ADMIN');
        if (!$owns && !$staff) {
            return $this->owner->httpError(403, 'Kein Zugriff auf diesen Beleg.');
        }

        i18n::set_locale($order->Locale);
        $binary = $order->PDFReceipt('string');
        if (!$binary) {
            return $this->owner->httpError(500, 'PDF konnte nicht erzeugt werden.');
        }

        $name = 'Rechnung_' . $order->Reference . '.pdf';
        $response = HTTPResponse::create($binary);
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
        $response->addHeader('Content-Transfer-Encoding', 'binary');
        return $response;
    }

    /**
     * Render an order info page for the requested order.
     *
     * Loads the order by the "ID" URL parameter and renders it with the
     * AccountPage_infos template, but only if the current member is allowed
     * to view the order. Returns a 403 for a forbidden order and a 404 when
     * no ID is supplied.
     *
     * @return \SilverStripe\ORM\FieldType\DBHTMLText The rendered order info template.
     */
    public function infos(){
        $request = $this->owner->getRequest();
        if($request->param('ID')){
            $order = Order::get()->byID(Convert::raw2sql($request->param('ID')));
            if(count($order) == 1 && $order->canView(Security::getCurrentUser())){
                return $this->owner->customise([
                    'Title' => $this->owner->Title,
                    'Order' => $order
                ])->renderWith(['AccountPage_infos', 'Page']);
            } else {
                $this->owner->httpError(403);
            }
        }
        $this->owner->httpError(404);
    }
}