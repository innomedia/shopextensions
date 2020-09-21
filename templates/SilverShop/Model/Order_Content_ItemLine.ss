<tr class="itemRow $EvenOdd $FirstLast">
    <td class="product title" scope="row">
        <% if $Link %>
            <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$TableTitle %>">$TableTitle</a>
        <% else %>
            $TableTitle
        <% end_if %>
        <% if $SubTitle %>
            <span class="subtitle">$SubTitle</span>
        <% end_if %>
    </td>
    <td class="text-center unitprice text-nowrap">$UnitPrice</td>
    <td class="text-center quantity">$Quantity</td>
    <td class="total text-right">$Total</td>
</tr>
