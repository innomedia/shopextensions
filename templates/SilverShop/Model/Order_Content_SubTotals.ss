<tfoot>
<tr class="gap summary" id="SubTotal">
    <td colspan="2" class="border-0"></td>
    <td colspan="1" scope="row" class="threeColHeader subtotal text-right"><strong><%t SilverShop\Model\Order.SubTotal "Sub-total" %></strong></td>
    <td class="text-right">$SubTotal</td>
</tr>
    <% loop $Modifiers %>
        <% if $ShowInTable %>
        <tr class="modifierRow $EvenOdd $FirstLast $Classes">
            <td colspan="1" scope="row" class="border-0 text-right">
            <td colspan="2" scope="row" class="text-right">
                <strong>
                <% if $TableTitle = 'Discount' %>
                    <%t Order.DISCOUNT 'Discount' %>
                <% else %>
                    $TableTitle.RAW
                <% end_if %>
                </strong>
            </td>
            <td class="text-right text-nowrap pl-2">$TableValue</td>
        </tr>
        <% end_if %>
    <% end_loop %>
<tr class="gap summary total" id="Total">
    <td colspan="1" class="border-0"></td>
    <td colspan="2" scope="row" class="threeColHeader total text-right"><strong><%t SilverShop\Model\Order.Total "Total" %></strong></td>
    <td class="text-right Total">$Total</td>
</tr>
</tfoot>
