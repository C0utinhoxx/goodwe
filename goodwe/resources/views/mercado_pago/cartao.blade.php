<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <title>Pagamento</title>
    <style>
        #form-checkout {
            display: flex;
            flex-direction: column;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            gap: 15px;
        }

        .container {
            height: 40px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            padding: 0 8px;      /* sem padding vertical */
            box-sizing: border-box;
            position: relative;
        }

        .container iframe {
            width: 100% !important;
            height: 100% !important;
            border: none;
            display: block;
        }   

        input, select {
            width: 100%;
            height: 40px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        button {
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .date-container {
            display: flex;
            gap: 10px;
        }
        
        .date-container .container {
            flex: 1;
        }
    </style>
</head>
<body>
    <form id="form-checkout">
        <div class="container" id="form-checkout__cardNumber"></div>
        <div class="date-container">
            <div class="container" id="form-checkout__expirationMonth"></div>
            <div class="container" id="form-checkout__expirationYear"></div>
        </div>
        <div class="container" id="form-checkout__securityCode"></div>
        
        <input type="text" id="form-checkout__cardholderName" placeholder="Titular do cartão"/>
        <select id="form-checkout__issuer"></select>
        <select id="form-checkout__installments"></select>
        <select id="form-checkout__identificationType"></select>
        <input type="text" id="form-checkout__identificationNumber" placeholder="Número do documento"/>
        <input type="email" id="form-checkout__cardholderEmail" placeholder="E-mail"/>

        <button type="submit" id="form-checkout__submit">Pagar</button>
        <progress value="0" class="progress-bar">Carregando...</progress>
    </form>

  <script>
    window.paymentAmount = "{{ number_format($total, 2, '.', '') }}";
</script>
   <script   src="{{ asset('js/pagamento.js') }}"></script>
</body>
</html>