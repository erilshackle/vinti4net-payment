<?php

use Erilshk\Vinti4Net\PaymentClient;

include "../../vendor/autoload.php";
include "./credentials.php";

// Instanciação para teste (opcional)
$client = new PaymentClient(
    posID: VINTI4_POS_ID,
    posAutCode: VINTI4_POS_AUTCODE,
);

/** @var \Erilshk\Vinti4Net\PaymentResult */
$result = $client->processResponse($_POST);

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