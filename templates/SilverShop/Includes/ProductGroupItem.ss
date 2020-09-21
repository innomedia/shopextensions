<div class="card">
    <% if $Image %>
        <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$Title %>" class="p-4 pb-2">
            <img src="$Image.getThumbnail.URL" class="card-img-top" alt="<%t SilverShop\Page\Product.ImageAltText "{Title} image" Title=$Title %>" >
        </a>
    <% else %>
        <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$Title %>" class="noimage"><!-- no image --></a>
    <% end_if %>
    <div class="card-body text-center typography">
        <% if $BaseWeight %>
            <h6 class="mb-0">$BaseWeight</h6>
        <% else_if $SubTitle %>
            <h6 class="mb-0">$SubTitle</h6>
        <% end_if %>
        <h3 class="card-title text-uppercase"><a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$Title %>" class="text-uppercase">$Title</a></h3>
        <p class="mb-0 bold d-inline-block h4">$Price.Nice</p> <small class="bold font_small"></small>
        <p class="font-grey font_small mb-0">zzgl. Versandkosten</p>
    </div>
    <ul class="list-group list-group-flush pr-0 border-top">
        <li class="list-group-item p-0">
            <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$Title %>" class="btn btn-white w-100">
                <%t SilverShop\Page\Product.View "View Product" %>
            </a>
        </li>
        <li class="list-group-item p-0">
            <a href="$addLink" title="<%t SilverShop\Page\Product.AddToCartTitle "Add &quot;{Title}&quot; to your cart" Title=$Title %>" class="btn btn-white w-100">
                <%t SilverShop\Page\Product.AddToCart "Add to Cart" %>
                <% if $IsInCart %>
                    ($Item.Quantity)
                <% end_if %>
            </a>
        </li>
    </ul>
</div>