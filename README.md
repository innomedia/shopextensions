# shopextensions

Overwrite Receipt.ss and DeliverySlip.ss in templates.
on 404 after i.e. Mollie returns "paid" and order not found
check if Emails can be sent without error

Debug Hint:
set Order to paid in Database
and call
/OrderReceipt/StreamDeliverySlip/$OrderID
and
/OrderReceipt/StreamReceipt/$OrderID
to test for errors


You may also need to apply the silvershop.patch for Address Fields to correctly display in default templates
just add/require "cweagans/composer-patches" -> composer update -> add  
"patches": {
    "silvershop/core": {
        "Made Address Fields not in composite field": "silvershop.patch"
    }
},
to your "extra" in composer.json
and update again (first one needed for composer-patches to install second needed for the patch to be applied)
