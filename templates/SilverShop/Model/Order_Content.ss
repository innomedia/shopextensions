<table id="InformationTable" class="infotable ordercontent mb-4">
    <colgroup class="product title"/>
    <colgroup class="unitprice" />
    <colgroup class="quantity" />
    <colgroup class="total"/>
    <thead>
    <tr>
        <th scope="col"><%t SilverShop\Page\Product.SINGULARNAME "Product" %></th>
        <th class="text-center" scope="col"><%t SilverShop\Model\Order.UnitPrice "Unit Price" %></th>
        <th class="text-center" scope="col"><%t SilverShop\Model\Order.Quantity "Quantity" %></th>
        <th class="text-right" scope="col"><%t SilverShop\Model\Order.Total "Total Price" %></th>
    </tr>
    </thead>
    <tbody>
        <% loop $Items %>
            <% include SilverShop\Model\Order_Content_ItemLine %>
        <% end_loop %>
    </tbody>
    <% include SilverShop\Model\Order_Content_SubTotals %>
</table>