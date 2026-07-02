<%-- Tailwind product detail layout (shopextensions override of silvershop's default
     SilverShop/Page/Layout/Product.ss). Two columns: image + purchase panel.
     The data-* hooks drive shop-cart.js: AJAX add-to-cart, live variant price
     (via the selectvariation action) and variant image switching. --%>
<% include PageHeaderSlim %>
<div class="page space-top space-bottom relative bg-white z-10 rounded-t-md lg:rounded-t-lg xl:rounded-t-xl overflow-hidden">
    <div class="container" data-product data-selectvariation-base="$Link">
        <% if $Breadcrumbs %>
            <nav class="mb-6 lg:mb-8 text-sm text-primary/60 [&_a]:hover:text-accent [&_a]:transition-colors">$Breadcrumbs</nav>
        <% end_if %>

        <div class="grid grid-cols-12 gap-6 lg:gap-10 xl:gap-14">
            <%-- Bildbereich --%>
            <div class="col-span-12 lg:col-span-6">
                <div class="bg-grey-light rounded-lg lg:rounded-xl overflow-hidden aspect-square flex items-center justify-center p-4 lg:p-8">
                    <%-- data-product-image bleibt immer im DOM, damit shop-cart.js beim
                         Variantenwechsel das Bild tauschen kann. Default: Produktbild,
                         sonst das Bild der ersten Variante (Produkte ohne eigenes Bild),
                         sonst Placeholder. --%>
                    <% if $Image %>
                        <img data-product-image src="$Image.Fit(900,900).URL"
                             alt="<%t SilverShop\Page\Product.ImageAltText "{Title}" Title=$Title %>"
                             class="max-w-full max-h-full w-auto h-auto object-contain">
                    <% else_if $Variations.First.Image %>
                        <img data-product-image src="$Variations.First.Image.Fit(900,900).URL"
                             alt="<%t SilverShop\Page\Product.ImageAltText "{Title}" Title=$Title %>"
                             class="max-w-full max-h-full w-auto h-auto object-contain">
                    <% else %>
                        <img data-product-image src=""
                             alt="<%t SilverShop\Page\Product.ImageAltText "{Title}" Title=$Title %>"
                             class="max-w-full max-h-full w-auto h-auto object-contain hidden">
                        <span data-product-image-placeholder class="flex flex-col items-center text-primary/30">
                            <i class="bi bi-box-seam text-6xl"></i>
                            <span class="mt-2 text-sm"><%t SilverShop\Page\Product.NoImage "Kein Bild" %></span>
                        </span>
                    <% end_if %>
                </div>
            </div>

            <%-- Kaufbereich --%>
            <div class="col-span-12 lg:col-span-6">
                <% if $Parent.Title %>
                    <span class="block text-sm text-primary/60 mb-1">$Parent.Title</span>
                <% end_if %>
                <h1 class="text-2xl lg:text-3xl xl:text-4xl font-bold text-primary mb-4">$Title</h1>

                <%-- Preis (data-product-price wird beim Variantenwechsel per AJAX aktualisiert) --%>
                <div class="mb-6">
                    <span data-product-price class="text-3xl lg:text-4xl font-bold text-primary">
                        <% if $PriceRange %>
                            <% if $PriceRange.HasRange %><%t ShopExtensions.From "ab" %> <% end_if %>$PriceRange.Min.Nice
                        <% else %>
                            $Price.Nice
                        <% end_if %>
                    </span>
                    <% if $SiteConfig.TaxHint %>
                        <span class="block text-xs text-primary/60 mt-1">$SiteConfig.TaxHint</span>
                    <% end_if %>
                </div>

                <%-- Produkt-Meta --%>
                <% if $InternalItemID || $Model || $Size %>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm text-primary/80 mb-6">
                        <% if $InternalItemID %>
                            <dt class="font-semibold"><%t SilverShop\Page\Product.Code "Artikel-Nr." %></dt>
                            <dd class="mb-0">$InternalItemID</dd>
                        <% end_if %>
                        <% if $Model %>
                            <dt class="font-semibold"><%t SilverShop\Page\Product.Model "Modell" %></dt>
                            <dd class="mb-0">$Model.XML</dd>
                        <% end_if %>
                        <% if $Size %>
                            <dt class="font-semibold"><%t SilverShop\Page\Product.Size "Größe" %></dt>
                            <dd class="mb-0">$Size.XML</dd>
                        <% end_if %>
                    </dl>
                <% end_if %>

                <% if $IsInCart %>
                    <p class="inline-flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4">
                        <i class="bi bi-check-circle"></i>
                        <% if $Item.Quantity == 1 %>
                            <%t SilverShop\Page\Product.NumItemsInCartSingular "Dieser Artikel liegt in Ihrem Warenkorb." %>
                        <% else %>
                            <%t SilverShop\Page\Product.NumItemsInCartPlural "Sie haben {Quantity} Stück im Warenkorb." Quantity=$Item.Quantity %>
                        <% end_if %>
                    </p>
                <% end_if %>

                <%-- Add-to-cart / Varianten-Formular --%>
                <div class="product-add-form">
                    $Form
                </div>
            </div>
        </div>

        <% if $Content %>
            <div class="grid grid-cols-12 mt-10 lg:mt-14">
                <div class="col-span-12 lg:col-span-10 xl:col-span-8 prose prose-primary max-w-none">
                    $Content
                </div>
            </div>
        <% end_if %>
    </div>
</div>
