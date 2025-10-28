# 💳 Vinti4Net PHP SDK (`erilshk/vinti4net-payment`)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/erilshk/vinti4net-payment.svg)](https://packagist.org/packages/erilshk/vinti4net-payment)
[![License](https://img.shields.io/github/license/erilshk/vinti4net-payment)](https://github.com/erilshk/vinti4net-payment/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/erilshk/vinti4net-payment.svg)](https://packagist.org/packages/erilshk/vinti4net-payment)

Uma biblioteca **PHP simples e robusta** para integrar com o **Vinti4Net Payment Gateway** (SISP – Sistema Interbancário de Pagamentos de Cabo Verde).

Este SDK encapsula toda a complexidade do **3D Secure**, **Fingerprint**, e **PurchaseRequest**, permitindo que você se concentre no fluxo de negócio, garantindo segurança e conformidade com a documentação oficial.

---

## 🌟 Funcionalidades Principais

* Geração automática e segura de `FingerPrint` respeitando a documentação (Request e Response/Callback).
* Criação e validação de payload `PurchaseRequest` (3DS).
* Suporte a tipos de transação:

  * **Compra** (`TransactionCode='1'`)
  * **Pagamento de Serviço** (`TransactionCode='2'`)
  * **Recarga** (`TransactionCode='3'`)
* Validação de Callback com verificação de segurança (`hash_equals`).
* Classes DTO (`PaymentRequest`, `PaymentResult`) para código limpo e tipado.

---

## 🚀 Instalação

Requer **PHP >= 8.0**. Instale via [Composer](https://getcomposer.org/):

```bash
composer require erilshk/vinti4net-payment
```

---

## 🛠️ Uso

### 1. Inicialização

```php
<?php
require 'vendor/autoload.php';

use Erilshk\Vinti4Net\PaymentClient;

// Credenciais do POS
$posID = 'SEU_POS_ID';
$posAutCode = 'SEU_POS_AUT_CODE_SECRETO';

$client = new PaymentClient($posID, $posAutCode);

// Para ambiente de teste, passe a URL do endpoint como terceiro parâmetro
// $client = new PaymentClient($posID, $posAutCode, 'https://staging.vinti4net.cv/BizMPIOnUs/CardPayment');
```

---

### 2. Fluxo de Compra (`TransactionCode='1'`)

Para compras, os dados de **Billing** são obrigatórios para 3D Secure.

```php
use Erilshk\Vinti4Net\PaymentRequest;

// 1. Cria a requisição
$request = new PaymentRequest();
$request->setAmount(1500.00); // valor em CVE
$request->setResponseUrl('https://seuapp.cv/callback-vinti4');
$request->setMerchantRef('ORDER-' . time());

// 2. Dados de cobrança (Billing)
$request->setBilling(
    email: 'cliente@exemplo.com',
    country: '132', // CV
    city: 'Praia',
    address: 'Av. Cidade de Lisboa, 12',
    postalCode: '7600'
);

// 3. Renderiza o formulário de pagamento (auto-submissão)
echo $client->renderPaymentForm($request);
exit;
```

---

### 3. Pagamento de Serviço (`TransactionCode='2'`)

```php
$request = $client->createServicePayment(
    amount: 500.00,
    responseUrl: 'https://seuapp.cv/callback-vinti4',
    entityCode: '4321',         // Código da entidade
    referenceNumber: '987654321'  // Número de referência
);

echo $client->renderPaymentForm($request);
exit;
```

---

### 4. Processamento de Resposta (Callback)

A URL `$responseUrl` recebe POST do Vinti4Net. Valide o `FingerPrint` para segurança.

```php
use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient('SEU_POS_ID', 'SEU_POS_AUT_CODE_SECRETO');

// Processa o POST do gateway
$result = $client->processResponse($_POST);

if ($result->succeeded()) {
    $referencia = $result->data['merchantRespMerchantRef'];
    echo "<h1>Pagamento #{$referencia} aprovado!</h1>";
    echo $result->generateReceipt(); // opcional
} elseif ($result->status === $result::STATUS_FINGERPRINT_INVALID) {
    error_log("Falha crítica no Fingerprint: " . $result->message);
} else {
    echo "<h1>Pagamento falhou ou cancelado</h1>";
    echo "<p>Status: {$result->status}</p>";
    echo "<p>Mensagem: {$result->message}</p>";
}
```

---

## 🚨 Tratamento de Erros e Exceções

| Exceção               | Descrição                                                               |
| --------------------- | ----------------------------------------------------------------------- |
| `ValidationException` | Dados obrigatórios ausentes ou incorretos (ex: billingData em compras). |
| `PaymentException`    | Erros internos do SDK (ex: falha ao codificar JSON).                    |

**Exemplo de Try/Catch:**

```php
use Erilshk\Vinti4Net\Exception\ValidationException;

try {
    echo $client->renderPaymentForm($request);
} catch (ValidationException $e) {
    echo "Erro de validação: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro inesperado: " . $e->getMessage();
}
```

---

## 🤝 Contribuição

Contribuições são bem-vindas!

1. Abra uma **Issue** no GitHub.
2. Faça um **Fork** do projeto.
3. Envie um **Pull Request** com alterações (preferencialmente com testes).

---

## 📄 Licença

Este projeto está sob a **Licença MIT** – uso livre, modificação e distribuição permitidos, inclusive para projetos comerciais.

---

**[Voltar ao Topo](#)**

---