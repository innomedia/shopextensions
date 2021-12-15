<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\SiteConfig\SiteConfig;
use TractorCow\Fluent\Model\Locale;


/**
 * Class \MySite\Extensions\WirecardPurchaseService
 *
 * @property PaymentService $owner
 */
class ExtendedPurchaseService extends Extension
{
    /**
     * Adds required order description to order data
     *
     * @param array $data
     */
    public function onBeforePurchase(array &$data)
    {
        $payment = $this->owner->getPayment();

        /** @var Order $order */
        $order = $payment->Order();

        $data['description'] = $order->getDescription();
        //die($data['description']);

        $termsPage = SiteConfig::current_site_config()->TermsPage();

        $serviceURL = $termsPage->exists()
            ? $termsPage->AbsoluteLink()
            : Director::absoluteURL('/');

        $data['serviceUrl'] = $serviceURL;

        if(isset($_SESSION['custompaymentmethod'])){
            $data['paymentType'] = $_SESSION['custompaymentmethod'];
        }

        if(class_exists('TractorCow\Fluent\Model\Locale')){
            if(Locale::getCurrentLocale()->Locale){
                $data['language'] = substr(Locale::getCurrentLocale()->Locale,0,2);

            }
        }

        // Fix reserved params in repayment
        unset($data['PaymentMethod']);
    }
}
