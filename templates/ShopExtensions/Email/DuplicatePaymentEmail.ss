<%-- Operator alert: a further payment was captured on an order that was already covered
     (ShopExtensions\PaymentExtension::alertDuplicatePayment()). With auto_refund_duplicate off this
     mail is the only signal that a customer paid twice, so it lists ALL payments of the order and
     marks the surplus one. Override in the theme to use the project's mail layout.
     Data: $Order, $Payment, $SiteConfig, $BaseURL (absolute, with trailing slash). --%>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta name="viewport" content="width=device-width" />
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><%t ShopExtensions\Email.DuplicatePaymentTitle 'Mögliche Doppelzahlung' %></title>
        <style type="text/css">
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.5em; color: #222; background-color: #f6f6f6; margin: 0; padding: 0; }
            table.main { max-width: 640px; margin: 0 auto; background-color: #fff; border: 1px solid #e9e9e9; }
            td.content { padding: 20px; }
            table.data { border-collapse: collapse; width: 100%; margin: 0 0 20px; }
            table.data th, table.data td { text-align: left; vertical-align: top; padding: 6px 8px 6px 0; border-bottom: 1px solid #e9e9e9; }
            table.data td.amount { text-align: right; white-space: nowrap; }
            a.button { display: inline-block; padding: 10px 20px; background-color: #333; color: #fff; text-decoration: none; }
        </style>
    </head>
    <body>
        <table class="main" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="content">
                    <h1><%t ShopExtensions\Email.DuplicatePaymentTitle 'Mögliche Doppelzahlung' %></h1>
                    <% if $Order %>
                        <p><%t ShopExtensions\Email.DuplicatePaymentLead 'Zu Bestellung {ref} wurde eine weitere Zahlung über {amount} verbucht, obwohl die Bestellung bereits durch eine andere Zahlung gedeckt war. Bitte eine Rückerstattung prüfen.' ref=$Order.Reference amount=$Payment.Money.Nice %></p>

                        <table class="data" cellpadding="0" cellspacing="0">
                            <tr>
                                <th width="40%"><%t ShopExtensions\Email.DuplicatePaymentOrder 'Bestellung' %></th>
                                <td>$Order.Reference<% if $Order.Placed %> &middot; $Order.Placed.Nice<% end_if %></td>
                            </tr>
                            <tr>
                                <th><%t ShopExtensions\Email.DuplicatePaymentCustomer 'Kunde' %></th>
                                <td>$Order.Name<% if $Order.LatestEmail %> &middot; $Order.LatestEmail<% end_if %></td>
                            </tr>
                            <tr>
                                <th><%t ShopExtensions\Email.DuplicatePaymentTotal 'Summe der Bestellung' %></th>
                                <td>$Order.Total.Nice</td>
                            </tr>
                            <tr>
                                <th><%t ShopExtensions\Email.DuplicatePaymentPaid 'Bereits verbucht' %></th>
                                <td>$Order.TotalPaid.Nice</td>
                            </tr>
                        </table>

                        <h2><%t ShopExtensions\Email.DuplicatePaymentPayments 'Zahlungen zu dieser Bestellung' %></h2>
                        <table class="data" cellpadding="0" cellspacing="0">
                            <% loop $Order.Payments %>
                                <tr>
                                    <td>
                                        $Created.Nice &middot; $GatewayTitle &middot; $PaymentStatus<br />
                                        Nr. $ID<% if $TransactionReference %> &middot; $TransactionReference<% end_if %>
                                        <% if $Top.Payment && $ID == $Top.Payment.ID %><br /><strong><%t ShopExtensions\Email.DuplicatePaymentThisOne 'Diese Zahlung ist zu viel.' %></strong><% end_if %>
                                    </td>
                                    <td class="amount">$Money.Nice</td>
                                </tr>
                            <% end_loop %>
                        </table>

                        <p><a class="button" href="{$BaseURL}admin/orders/SilverShop-Model-Order/EditForm/field/SilverShop-Model-Order/item/{$Order.ID}/edit"><%t ShopExtensions\Email.DuplicatePaymentOpen 'Bestellung im Backend öffnen' %></a></p>
                    <% else %>
                        <p><%t ShopExtensions\Email.DuplicatePaymentNoOrder 'Zu dieser Zahlung liegt keine Bestellung mehr vor. Bitte im Backend nachsehen.' %></p>
                    <% end_if %>
                </td>
            </tr>
        </table>
    </body>
</html>
