# 🚀 Inicialização do Vinti4Net PHP SDK

Esta página mostra como **inicializar rapidamente** o SDK e preparar o cliente para uso.

---

## 1️⃣ Requisitos e Instalação

- PHP >= 8.1
- Composer instalado

Instale via Composer:

```bash
composer require erilshk/vinti4net-payment
```


## 2️⃣ Criando o Client

```php
<?php

require 'vendor/autoload.php';

use Erilshk\Vinti4Net\PaymentClient;

// Substitua pelas suas credenciais reais
$posID = 'SEU_POS_ID';
$posAutCode = 'SEU_POS_AUT_CODE_SECRETO';

$client = new PaymentClient($posID, $posAutCode);

// Opcional: URL de teste (Staging)
// $client = new PaymentClient($posID, $posAutCode, 'https://staging.vinti4net.cv/');
```

## 3️⃣ Criação de uma transação 

```php
use Erilshk\Vinti4Net\PaymentRequest;

// Criar o objeto de requisição
// transitionCode = 1 - compra
// transitionCode = 2 - pagamento de serviço
// transitionCode = 3 - recarga
$request = new PaymentRequest(amount: 150, responseUrl: 'https://seuapp.cv/processar-vinti4-callback', transactionCode: 1);

/**
 *? É muito recomendado criar a instancia de PaymentRequest por meio da classe PaymentClient
 * $client = new PaymentClient('VINTI4_POS_ID', 'VINTI4_POS_AUTCODE'); 
 **/

// Adicionar dados de billing (obrigatório para 3DS)
$request->setBilling(
    email: 'cliente@exemplo.com',
    country: '132',       // Código do país CV
    city: 'Praia',
    address: 'Av. Cidade de Lisboa, 12',
    postalCode: '7600'
);

// Renderizar formulário e redirecionar
echo $client->renderPaymentForm($request);
exit;
```

---

## 4️⃣ Próximos passos

* Configurar **callback URL** para processar a resposta do Vinti4Net.
* Criar páginas para **Serviço** e **Recarga**.
* Consultar seções de **Tratamento de Erros** e **Exceções**.