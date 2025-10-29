<?php

//* Edite com as suas credenciais abaixo e siga as instrucoes para teste:       <- Critical
# no terminal: php -S localhost:8000 -t tests/web                               <- !important
# no navegador: http://localhost:8000/                                          <- Ctrl + click
# depois verifique o response-vinti4.php                                        <- just read

use Erilshk\Vinti4Net\PaymentClient;

include "../../vendor/autoload.php";

// por segurança use variaveis no .env
define('VINTI4_POS_ID', 'meu-posid');   
define('VINTI4_POS_AUTCODE', 'meu-pos-autcode');
define('VINTI4_RESPONSE_URL', 'http://localhost:8000/response-vinti4.php');
define('VINTI4_ENDPOINT', 'https://mc.vinti4net.cv/Client_VbV_v2/biz_vbv_clientdata.jsp');

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
