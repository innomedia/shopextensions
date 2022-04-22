<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Core\Convert;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataExtension;



class PageControllerExtension extends DataExtension
{
    private static $allowed_actions = [
        'StreamReceipt' => true,
        'StreamDeliverySlip' => true,
    ];

    public function StreamReceipt()
    {
        $ID = Convert::raw2sql($this->getRequest()->params()["ID"]);
        if ($ID != "" && $ID > 0) {
            $Order = Order::get()->byID($ID);
            i18n::set_locale($Order->Locale);
            $name = '_' . $Order->Reference . '__' . date("Y-m-d_H-i-s");

            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
            header('Content-Transfer-Encoding: binary');
            echo Order::get()->byID($ID)->PDFReceipt("string");
        }
    }

    public function StreamDeliverySlip()
    {
        $ID = Convert::raw2sql($this->getRequest()->params()["ID"]);
        if ($ID != "" && $ID > 0) {
            $Order = Order::get()->byID($ID);
            i18n::set_locale($Order->Locale);
            $name = '_' . $Order->Reference . '__' . date("Y-m-d_H-i-s");

            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
            header('Content-Transfer-Encoding: binary');
            echo Order::get()->byID($ID)->PDFDeliverySlip("string");
        }
    }
}
