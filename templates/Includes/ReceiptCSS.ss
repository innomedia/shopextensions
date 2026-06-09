<style>
    div
    {
        margin:0;
        padding:0;
    }
    img
    {
        margin:0;
        padding:0;
    }
    body{
        background: #cccccc;
    }

    body *{
        font-size: 10pt;
        color:#000000;
    }

    @page {
        size: 21cm 29.7cm;
        padding:0;
        margin:0;
    }
    .page
    {
        position: relative;
        background: #fff;
        height:29.7cm;
        max-height:29.7cm;
        width:21cm;
        font-family: sans-serif;
        font-size:0.4cm;
        margin:0 !important;
        padding:0 !important;
    }
    a{
        text-decoration: none;
    }

    /* FOOTER */
    .footer{
        position: absolute;
        bottom:0;
        left:0;
        right:0;
        width: 100%;
    }

    .footer table{
        padding:2cm;
    }


    .footer table td{
        white-space: nowrap;
        line-height: 1.7;
    }

    .footer *{
        font-size: 7pt
    }

    /* TYPO */
    strong{
        font-weight: bold;
    }

    .right{
        text-align: right;
    }

    .center{
        text-align: center;
    }

    .addresscontainer{
        position: absolute;
        top:5cm;
        left:2cm;
    }

    .logo{
        width: 5cm;
        position: absolute;
        right:2cm;
        top:3cm;
    }

    .shippingcontainer{
        position: absolute;
        top:8.5cm;
        left:2cm;
    }


    /* INVOICE */
    .invoicecontainer{
        position: absolute;
        top:8cm;
        right:2cm;

    }

    .itemscontainer table{
        border-collapse: collapse;
    }
    .itemscontainer td,
    .itemscontainer th{
        padding:0.15cm 0;
    }

    .itemscontainer{
        position: absolute;
        top:11cm;
        left:2cm;
        width: 17cm;
    }

    #PaymentTable td,
    #PaymentTable th{
        text-align: left;
    }
</style>