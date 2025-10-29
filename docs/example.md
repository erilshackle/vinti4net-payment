# 💳 Exemplo de Integração Vinti4Net (Ambiente de Teste)

Este exemplo demonstra como realizar uma **requisição de pagamento** e processar a **resposta da SISP (Vinti4)** usando a biblioteca `Erilshk\Vinti4Net`.

---

## ⚙️ Preparação do ambiente

1. Instale as dependências via **Composer**:

```bash
   composer require erilshk/vinti4net
```

2. Crie um ambiente local de testes:

```bash
php -S localhost:8000 -t tests/web
```

3. No navegador, acesse:

   👉 [http://localhost:8000/](http://localhost:8000/)

---

## 🧾 Arquivo `post-vinti4.php`

```php
<?php

//* Edite com as suas credenciais abaixo e siga as instruções para teste:
# no terminal: php -S localhost:8000 -t tests/web
# no navegador: http://localhost:8000/post-vinti4.php
# depois verifique o response-vinti4.php

use Erilshk\Vinti4Net\PaymentClient;

include "../../vendor/autoload.php";

// por segurança use variáveis no .env
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

$request = $client->createPurchasePayment(amount: $valor, responseUrl: VINTI4_RESPONSE_URL);

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
```

---

## 🧾 Arquivo `response-vinti4.php`

```php
<?php

//* Edite as credenciais abaixo e siga as instruções:
# Esta página deveria ser chamada pela resposta da SISP ao responseUrl
# No entanto, isso pode não funcionar pois é requerido HTTPS para receber a resposta.
//? Para testes, use um túnel como ngrok por exemplo:

use Erilshk\Vinti4Net\PaymentClient;
use Erilshk\Vinti4Net\PaymentResult;

include "../../vendor/autoload.php";

// por segurança use variáveis no .env
define('VINTI4_POS_ID', 'meu-posid');
define('VINTI4_POS_AUTCODE', 'meu-pos-autcode');

// Instanciação para teste
$client = new PaymentClient(
    posID: VINTI4_POS_ID,
    posAutCode: VINTI4_POS_AUTCODE,
);

/** @var PaymentResult */
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
```

---

## 🧪 Testar Submissão

Use o formulário abaixo para simular uma compra:

<form action="http://localhost:8000/post-vinti4.php" method="POST" target="_blank">
  <label for="price"><strong>Valor (ECV):</strong></label>
  <input type="number" id="price" name="price" value="100" min="0" step="100" required>
  <button type="submit">💳 Testar Pagamento</button>
</form>
<small style="opacity: 50%; font-size: smaller">vai submeter para: http://localhost:**8000**/post-vinti4.php</small>
<br>
> 💡 *Este formulário abrirá a página `post-vinti4.php`, que cria a requisição de pagamento e redireciona para o ambiente de testes da SISP.*

---

## 🧰 Dica de Teste com HTTPS

A SISP exige HTTPS para enviar respostas automáticas.
Para testes locais, utilize um túnel como **ngrok**:

```bash
ngrok http 8000
```

Depois, substitua a variável `VINTI4_RESPONSE_URL` por algo como:

```
https://1234abcd.ngrok.io/response-vinti4.php
```

---

✅ **Próximos passos:**

* Ajuste suas credenciais de teste fornecidas pela SISP.
* Teste diferentes valores de compra.
* Valide o fluxo completo até o `response-vinti4.php`.

---
