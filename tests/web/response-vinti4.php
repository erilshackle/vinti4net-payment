<?php

//* Edite as credenciais abaixo e siga as instrucoes:
# esta pagina deveria ser chamada pelo responsta da SISP ao responseUrl
# No entando isso pode não funcionar pois é requerido HTTPS para receber a resposta. oq fazer para testes?
//? Para testes, use um tunel como ngrok por exemplo;

//! É extrememente cirucrgico seguinte os passos assim como dito para comprovar os testes
// todo: De forma análoga prepare seu proprio ambiente de teste e execute os passos.

use Erilshk\Vinti4Net\PaymentClient;
use Erilshk\Vinti4Net\PaymentResult;

include "../../vendor/autoload.php";

// por segurança use variaveis no .env
// por segurança use variaveis no .env
define('VINTI4_POS_ID', 'meu-posid');   
define('VINTI4_POS_AUTCODE', 'meu-pos-autcode');

// Instanciação para teste (opcional)
$client = new PaymentClient(
    posID: VINTI4_POS_ID,
    posAutCode: VINTI4_POS_AUTCODE,
);

/** @var PaymentResult */
$result = $client->processResponse(postData: $_POST);

if ($result->succeeded()) {
    echo "Pagamento aprovado!";
    $referencia = $result->data['merchantRespMerchantRef'];
    echo $result->generateReceipt(); // opcional
} elseif ($result->status === $result::STATUS_FINGERPRINT_INVALID) {
    error_log("Falha de segurança: Fingerprint inválido!");
} else {
    echo "Pagamento falhou ou foi cancelado:<br>";
    echo $result->message;
}
exit;