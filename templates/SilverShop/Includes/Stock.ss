<div>
    <% if $hasAvailableStock %>
        <% if $Available %>
            <span class="d-inline" style="font-size: 16px"><i class="fas fa-circle" style="color: #856404; font-size: 12px"></i> Verfügbar</span>
        <% else %>
            <span class="d-inline" style="font-size: 16px"><i class="fas fa-circle" style="color: green; font-size: 12px"></i> auf Anfrage</span>
        <% end_if %>
    <% else %>
        <span class="d-inline" style="font-size: 16px"><i class="fas fa-circle" style="color: #8e060a; font-size: 12px"></i> Verfügbar</span>
    <% end_if %>
</div>