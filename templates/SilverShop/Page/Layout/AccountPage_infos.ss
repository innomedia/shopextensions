<% require css("silvershop/core: client/dist/css/account.css") %>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 content py-3 col-print-12 typography">
            <h1 class="mb-20">$Title.RAW</h1>
            $Content

            <% if $Order %>
                <% with $Order %>
                    <h2 class="h4"><%t SilverShop\Model\Order.OrderHeadline "Order #{OrderNo} {OrderDate}" OrderNo=$Reference OrderDate=$Created.Nice %></h2>
                <% end_with %>
            <% end_if %>
            <% if $Message %>
                <p class="message $MessageType">$Message</p>
            <% end_if %>
            <% if $Order %>
                <% with $Order %>
                    <% include SilverShop\Model\Order %>
                <% end_with %>
                $ActionsForm
            <% end_if %>
        </div>
        <div class="col-md-4 py-3 typography">
            <h2><%t SilverShop\Page\AccountPage.Title 'My Account' %></h2>
            <ul class="">
                <li>
                    <a href="{$Link}" class="black">
                        <%t SilverShop\Page\AccountPage.PastOrders 'Past Orders' %>
                    </a>
                </li>
                <li>
                    <a href="Security/logout" class="black">
                        <%t SilverShop\Page\AccountPage.LogOut 'Log Out' %>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>