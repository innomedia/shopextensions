<?php

namespace ShopExtensions\Checkout;

use SilverStripe\Forms\OptionsetField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Model\ArrayData;

/**
 * An OptionsetField that renders payment options as real radio "tiles" (label + radio + icon),
 * without any JavaScript. Validation still uses the plain source map (option-ID => title) so the
 * submitted value is guaranteed to be one of the offered options.
 *
 * The visual list is provided separately via {@see self::setPaymentOptions()} and exposed to the
 * template as {@see self::OptionList()}, each entry flagged with whether it is the selected one.
 */
class PaymentTileField extends OptionsetField
{
    /**
     * @var SS_List|null The PaymentOption records to render as tiles.
     */
    protected $paymentOptions;

    /**
     * Set the PaymentOption records that back the tiles.
     *
     * @param SS_List $options
     * @return $this
     */
    public function setPaymentOptions(SS_List $options)
    {
        $this->paymentOptions = $options;
        return $this;
    }

    /**
     * The tiles for the template: each PaymentOption plus a Selected flag reflecting the value.
     *
     * @return ArrayList
     */
    public function OptionList()
    {
        $list = ArrayList::create();
        if (!$this->paymentOptions) {
            return $list;
        }
        $selected = $this->getValue();
        foreach ($this->paymentOptions as $option) {
            $list->push(ArrayData::create([
                'Option' => $option,
                'Value' => $option->ID,
                'Title' => $option->Title,
                'Image' => $option->Image(),
                'Selected' => ((string) $option->ID === (string) $selected),
                'HolderID' => $this->ID() . '_' . $option->ID,
            ]));
        }
        return $list;
    }
}
