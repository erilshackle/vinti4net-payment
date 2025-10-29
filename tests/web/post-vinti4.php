<?php

use Erilshk\Vinti4Net\PaymentClient;

include "../../vendor/autoload.php";
include "./credentials.php";

$valor = $_POST['price'] ?? 150;

// Instanciação para teste (opcional)
$client = new PaymentClient(
    posID: VINTI4_POS_ID,
    posAutCode: VINTI4_POS_AUTCODE,
    endpoint: VINTI4_ENDPOINT
);

$request = $client->createPurchasePayment(amount: $valor, responseUrl: VINTI4_RESPONSE_URL, extra: [
    'user' => [
        'id' => 123,
    ]
]);

// Dados de Billing obrigatórios
$request->setBilling(
    email: 'cliente@exemplo.com',
    country: '132',
    city: 'Mindelo',
    address: 'Rua Amílcar Cabral, 10',
    postalCode: '2110'
);

echo $client->renderPaymentForm($request);
exit;
