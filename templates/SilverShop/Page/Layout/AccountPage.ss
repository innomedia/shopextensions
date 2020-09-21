<% require css("silvershop/core: client/dist/css/account.css") %>

<div class="container-fluid py-5">
    <div class="row">
        <div class="col-md-9 content ph-3 col-print-12 typography">
            <% if not $TitleDisabled %>
                <h1 class="mb-4">$Title.RAW</h1>
            <% end_if %>
            $Content
            <h2 class="pagetitle h4 font_brown"><%t SilverShop\Page\AccountPage.PastOrders 'Past Orders' %></h2>
            <% with $Member %>
                <% if $PastOrders %>
                    <table class="table infotable orderhistory">
                        <thead>
                        <tr>
                            <th><%t SilverShop\Model\Order.db_Reference 'Reference' %></th>
                            <th><%t SilverShop\Model\Order.Date 'Date' %></th>
                            <th><%t SilverShop\Model\Order.has_many_Items 'Items' %></th>
                            <th><%t SilverShop\Model\Order.Total 'Total' %></th>
                            <th><%t SilverShop\Model\Order.db_Status 'Status' %></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                            <% loop $PastOrders %>
                            <tr class="{$Status}">
                                <td>$Reference</td>
                                <td>$Created.Nice</td>
                                <td>$Items.Quantity</td>
                                <td>$Total.Nice</td>
                                <td>$StatusI18N</td>
                                <td>
                                    <% if $Status = 'Paid' || $Status = 'Complete' %>
                                        <a class="button btn btn-primary btn-sm" href="$ReceiptLink">
                                            <i class="far fa-file-alt"></i> <%--t PDFReceipt.RECEIPT 'Rechnung' --%>
                                        </a>
                                    <% end_if %>
                                    <a class="btn btn-sm btn-primary" href="$Link">
                                        <i class="far fa-search"></i> <%--t SilverShop\Generic.View 'view' --%>
                                    </a>
                                </td>
                            </tr>
                            <% end_loop %>
                        </tbody>
                    </table>

                <% else %>
                    <p class="message warning"><%t SilverShop\Page\AccountPage.NoPastOrders 'No past orders found.' %></p>
                <% end_if %>
            <% end_with %>
            $Form
        </div>
        <div class="col-md-3 ph-3 typography account__sidebar">
            <h2 class="mb-4"><%t SilverShop\Page\AccountPage.Title 'My Account' %></h2>
            <a href="{$Link}" class="black d-block">
                <i class="far fa-file-invoice-dollar"></i> <%t SilverShop\Page\AccountPage.PastOrders 'Past Orders' %>
            </a>
            <a href="Security/logout" class="black d-block">
                <i class="far fa-sign-out-alt"></i> <%t SilverShop\Page\AccountPage.LogOut 'Log Out' %>
            </a>
        </div>
    </div>
</div>