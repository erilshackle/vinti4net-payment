# 📝 Vinti4Net PHP SDK Documentation

## Sumário

- [📝 Vinti4Net PHP SDK Documentation](#-vinti4net-php-sdk-documentation)
  - [Sumário](#sumário)
  - [Introdução](#introdução)
  - [Instalação](#instalação)
  - [Conceitos Principais](#conceitos-principais)
  - [Configuração e Inicialização](#configuração-e-inicialização)
  - [Criando Pagamentos](#criando-pagamentos)
    - [Compra (TransactionCode='1')](#compra-transactioncode1)
    - [Pagamento de Serviço (TransactionCode='2')](#pagamento-de-serviço-transactioncode2)
    - [Recarga (TransactionCode='3')](#recarga-transactioncode3)
  - [Processando Callback/Resposta](#processando-callbackresposta)
  - [Tratamento de Erros e Exceções](#tratamento-de-erros-e-exceções)
  - [Classes e Métodos](#classes-e-métodos)
    - [PaymentClient](#paymentclient)
    - [PaymentRequest](#paymentrequest)
    - [PaymentResult](#paymentresult)
  - [Contribuição](#contribuição)
  - [Licença](#licença)

---

## Introdução

O **Vinti4Net PHP SDK** fornece uma integração segura com o **Vinti4Net Payment Gateway**, encapsulando toda a lógica de:

* Fingerprint Request & Response
* PurchaseRequest 3DS
* Validação de Callback

O SDK é compatível com **PHP 8.1+** e foi desenvolvido para simplificar fluxos de pagamento em aplicativos PHP.

---

## Instalação

```bash
composer require erilshk/vinti4net-payment
```

---

## Conceitos Principais

| Conceito            | Descrição                                                               |
| ------------------- | ----------------------------------------------------------------------- |
| **PaymentClient**   | Classe principal que gerencia todas as transações.                      |
| **PaymentRequest**  | DTO que representa uma requisição de pagamento (amount, billing, etc).  |
| **PaymentResult**   | Resultado da transação, incluindo status, mensagem e dados do callback. |
| **FingerPrint**     | Token de segurança calculado para Request e Response.                   |
| **PurchaseRequest** | Payload de 3DS para validação de compras.                               |

---

## Configuração e Inicialização

```php
use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient('VINTI4_POS_ID', 'VINTI4_POS_AUTCODE');
// Ambiente de teste (opcional)
$client_test = new PaymentClient('VINTI4_POS_ID', 'VINTI4_POS_AUTCODE', 'https://3dsteste.vinti4net.cv/endpoint.php');
```

---

## Criando Pagamentos


### Compra (TransactionCode='1')

```php
$request = $client->createPurchasePayment(1500.00, 'https://seuapp.cv/callback-vinti4', [
    'user' => [
        'email' => 'cliente@exemplo.com',
        'country' => '132',
        'city' => 'Praia',
        'address' => 'Av. Cidade de Lisboa, 12',
        'postalCode' => '7600'
    ]
]);

echo $client->renderPaymentForm($request);
exit;
```

### Pagamento de Serviço (TransactionCode='2')

```php
$serviceRequest = $client->createServicePayment(
    500.00,
    'https://seuapp.cv/callback-vinti4',
    'FAT123',     // entityCode
    '123456789'   // reference
);

echo $client->renderPaymentForm($serviceRequest);
exit;
```

### Recarga (TransactionCode='3')

```php
$rechargeRequest = $client->createRechargePayment(
    200.00,
    'https://seuapp.cv/callback-vinti4',
    'OP123',  // entityCode
    '9876545'  // referenceNumber
);

echo $client->renderPaymentForm($rechargeRequest);
exit;
```

---

## Processando Callback/Resposta

```php
$result = $client->processResponse($_POST);

if ($result->succeeded()) {
    echo "Pagamento aprovado: " . $result->data['merchantRespMerchantRef'];
    echo $result->generateReceipt();
} elseif ($result->status === $result::STATUS_FINGERPRINT_INVALID) {
    error_log("Falha de segurança: " . $result->message);
} else {
    echo "Pagamento falhou ou cancelado. Status: {$result->status}";
}
```

---

## Tratamento de Erros e Exceções

| Exceção               | Descrição                                   |
| --------------------- | ------------------------------------------- |
| `ValidationException` | Campos obrigatórios ausentes ou incorretos. |
| `PaymentException`    | Erros internos do SDK.                      |

```php
try {
    echo $client->renderPaymentForm($request);
} catch (ValidationException $e) {
    echo "Erro de validação: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro inesperado: " . $e->getMessage();
}
```

---

## Classes e Métodos

### PaymentClient

| Método                    | Descrição                                    |
| ------------------------- | -------------------------------------------- |
| `createPurchasePayment()` | Cria requisição de compra (3DS obrigatório). |
| `createServicePayment()`  | Cria requisição de pagamento de serviço.     |
| `createRechargePayment()` | Cria requisição de recarga de telemóvel.     |
| `renderPaymentForm()`     | Retorna formulário HTML auto-submissível.    |
| `processResponse()`       | Valida callback e retorna PaymentResult.     |

### PaymentRequest

* `merchantRef`
* `merchantSession`
* `merchantSession`
* `setBilling(array $billingData)`

### PaymentResult

* `succeeded(): bool`
* `generateReceipt(): string`
* `status`
* `message`
* `data`

---

## Contribuição

1. Abra uma **Issue**.
2. Faça um **Fork** do projeto.
3. Envie um **Pull Request** testado.

---

## Licença

MIT License – livre para uso em qualquer projeto.

