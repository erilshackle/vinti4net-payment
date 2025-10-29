# 📱 Recarga

Transações de **Recarga** (TransactionCode = '3') requerem `entityCode` (operadora) e `referenceNumber` (número do telemóvel).

---

## 1️⃣ Criando a Requisição

```php

use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient(posId: 'VINTI4_POS_ID', posAutCode: 'VINTI4_POS_AUTCODE');


$prequest = $client->createRechargePayment(
    amount: 100.00,
    responseUrl: 'https://seuapp.cv/processar-vinti4-callback',
    entityCode: '001',       // Operadora
    referenceNumber: '991234567' // Número do telemóvel
);
```


## 2️⃣ Renderizando o Formulário

```php
echo $client->renderPaymentForm($prequest);
exit;
```

> Não é necessário enviar dados de billing.
