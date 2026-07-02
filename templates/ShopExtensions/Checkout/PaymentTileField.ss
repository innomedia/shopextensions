<style>
    .payment-tiles { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 12px; }
    .payment-tiles .payment-tile { margin: 0; }
    .payment-tiles .payment-tile label {
        display: flex; align-items: center; gap: 10px; cursor: pointer;
        border: 2px solid rgba(0,0,0,.12); border-radius: 8px; padding: 12px 16px; min-width: 160px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .payment-tiles .payment-tile label:hover { border-color: rgba(0,0,0,.3); }
    .payment-tiles .payment-tile label:focus-within { outline: 2px solid #2563eb; outline-offset: 2px; }
    /* Highlight strictly follows the actually checked radio, so ONLY the selected tile is marked.
       (Do not key off a static "selected" class — it would go stale on client-side selection.) */
    .payment-tiles .payment-tile:has(input:checked) label {
        border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; font-weight: 600;
    }
    .payment-tiles .payment-tile__icon { height: 28px; width: auto; }
</style>
<ul class="optionset payment-tiles" id="$ID" role="radiogroup">
    <% loop $OptionList %>
        <li class="payment-tile val{$Option.PaymentGateway}">
            <label for="$HolderID">
                <input id="$HolderID" class="payment-tile__radio" type="radio" name="$Up.Name" value="$Value"<% if $Selected %> checked="checked"<% end_if %> />
                <% if $Image.Exists %>
                    <img src="$Image.URL" alt="$Title.ATT" class="payment-tile__icon" />
                <% end_if %>
                <span class="payment-tile__title">$Title.XML</span>
            </label>
        </li>
    <% end_loop %>
</ul>
