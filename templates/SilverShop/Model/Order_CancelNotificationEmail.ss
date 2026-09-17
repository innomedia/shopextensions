<%-- Operator notice: a customer cancelled an order from the account page
     (SendOrderEmailJob::sendCancelNotification() / CustomOrderEmailNotifier). Replaces the former
     setBody($order->renderWith(Order::class)), which came out empty with TemplatedEmail (it only
     keeps string bodies) and showed the browser view of the order. Override in the theme.
     Data: $Order, $BaseURL (absolute, with trailing slash). --%>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
        <title><%t SilverShop\ShopEmail.CancelNotificationTitle 'Bestellung vom Kunden storniert' %></title>
        <% include SilverShop\Includes\OrderReceiptStyle %>
    </head>
    <body>
        <table id="container" cellpadding="0" cellspacing="0" border="0" >
            <tr>
                <td>
                    <table id="Content" cellspacing="0" cellpadding="0" summary="Email Information">
                        <thead>
                            <tr>
                                <th scope="col" colspan="2">
                                    <h1 class="title"><%t SilverShop\ShopEmail.CancelNotificationTitle 'Bestellung vom Kunden storniert' %></h1>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <% if $Order %>
                                <% with $Order %>
                                    <tr>
                                        <td>
                                            <p><%t SilverShop\ShopEmail.CancelNotificationLead 'Der Kunde {kunde} hat die Bestellung {ref} über {total} storniert.' kunde=$Name ref=$Reference total=$Total.Nice %></p>
                                            <p><a href="{$Top.BaseURL}admin/orders/SilverShop-Model-Order/EditForm/field/SilverShop-Model-Order/item/{$ID}/edit"><%t SilverShop\ShopEmail.AdminNotificationOpen 'Bestellung im Backend öffnen' %></a></p>
                                            <% include SilverShop\Model\Order_Address %>
                                            <% include SilverShop\Model\Order_Content %>
                                        </td>
                                    </tr>
                                <% end_with %>
                            <% end_if %>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
