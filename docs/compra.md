# 💳 Compra (Purchase)

Transações de **Compra** (TransactionCode = '1') utilizam 3D Secure e exigem dados de **billing**.

---

## 1️⃣ Criando a Requisição

```php
use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient(posId: 'VINTI4_POS_ID', posAutCode: 'VINTI4_POS_AUTCODE');

$request = $client->createPurchasePayment(amount: 250.00, responseUrl: 'https://seuapp.cv/processar-vinti4-callback');
$request->setMerchantRef('ORDER-'.time()); // opcional

// Dados de Billing obrigatórios
$request->setBilling(
    email: 'cliente@exemplo.com',
    country: '132',
    city: 'Mindelo',
    address: 'Rua Amílcar Cabral, 10',
    postalCode: '2110'
);
```

## 2️⃣ Renderizando o Formulário

```php
echo $client->renderPaymentForm($request);
exit;
```

O formulário irá se auto-submeter para o Vinti4Net Gateway.
