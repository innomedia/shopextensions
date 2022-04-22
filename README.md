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


