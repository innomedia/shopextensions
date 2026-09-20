<!DOCTYPE html>
<html lang="$i18nLocale.RFC1766">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>$Title.XML</title>
</head>
<body>
<main>
    <% with $Order %><% include ShopExtensions/Includes/GuestReceiptBody InvoicePdfLink=$Top.InvoicePdfLink %><% end_with %>
</main>
</body>
</html>
