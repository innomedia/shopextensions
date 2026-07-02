<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverShop\Model\Variation\Variation;
use SilverShop\ORM\FieldType\ShopCurrency;

/**
 * Extension on {@see \SilverShop\Page\ProductController} that exposes a controller action
 * for AJAX variant selection.
 *
 * When a customer picks a product variation in the storefront, the frontend requests the
 * matching price through this action and updates the displayed price without a full page
 * reload.
 *
 * Registered against SilverShop\Page\ProductController in _config/shopextensions.yml.
 *
 * @property \SilverShop\Page\ProductController $owner
 */
class ProductControllerExtension extends Extension{
    private static $allowed_actions = [
        'selectvariation' => true
    ];


    /**
     * Returns the selling price (and image, if the variation has its own) of a product
     * variation as JSON, for live AJAX variant selection on the product page.
     *
     * The variation ID comes from the URL ({@see _config/routes.yml}); only variations
     * belonging to the current product are served. The price is formatted through
     * ShopCurrency so it matches the theme's currency notation ("50,00 €").
     *
     * @return \SilverStripe\Control\HTTPResponse JSON: {success, price?, image?}
     */
    public function selectvariation(){
        $request = $this->owner->getRequest();
        $response = $this->owner->getResponse();
        $response->addHeader('Content-Type', 'application/json');

        $id = (int) $request->param('ID');
        $variation = $id ? Variation::get()->byID($id) : null;

        // Only serve variations that actually belong to the product being viewed.
        if (!$variation || (int) $variation->ProductID !== (int) $this->owner->data()->ID) {
            $response->setBody(json_encode(['success' => false]));
            return $response;
        }

        $price = ShopCurrency::create();
        $price->setValue($variation->sellingPrice());

        $data = [
            'success' => true,
            'price' => $price->Nice(),
        ];

        if ($variation->hasMethod('Image') && ($image = $variation->Image()) && $image && $image->exists()) {
            $data['image'] = $image->Fit(900, 900)->URL;
        }

        $response->setBody(json_encode($data));
        return $response;
    }
}