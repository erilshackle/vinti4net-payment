# 🏦 Pagamento de Serviço

Transações de **Pagamento de Serviço** (TransactionCode = '2') requerem `entityCode` e `referenceNumber`.

---

## 1️⃣ Criando a Requisição

```php

use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient(posId: 'VINTI4_POS_ID', posAutCode: 'VINTI4_POS_AUTCODE');

$prequest = $client->createServicePayment(
    amount: 500.00,
    responseUrl: 'https://seuapp.cv/processar-vinti4-callback',
    entityCode: '123',       // Código da entidade
    referenceNumber: '456789' // Número de referência
);
```

---

## 2️⃣ Renderizando o Formulário

```php
echo $client->renderPaymentForm($prequest);
exit;
```

> Não é necessário enviar dados de billing.
>
> _Porém estranhamente a middleware do gateway reclama_
