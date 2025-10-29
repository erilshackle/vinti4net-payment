# `Erilshk\Vinti4Net\PaymentClient`

> Serviço de integração com a plataforma de pagamento **Vinti4Net**.

Esta classe encapsula toda a lógica de comunicação com o gateway de pagamentos da **Vinti4Net**, incluindo:

* Criação de requisições de pagamento (`Purchase`, `Service`, `Recharge`);
* Geração de **FingerPrints** (envio e resposta);
* Geração de **PurchaseRequest** para 3DS;
* Renderização de formulários HTML para submissão;
* Validação e processamento de **callbacks** e respostas do gateway.

---

## 🧩 Namespace

```
Erilshk\Vinti4Net
```

## 📦 Pacote

```
erilshk/vinti4net-payment
```

## ⚖️ Licença

MIT
© 2025 [Eril TS](https://github.com/erilshk)

---

## 🏗️ Classe: `PaymentClient`

### Descrição

Gerencia todo o ciclo de vida de uma transação Vinti4Net, desde a criação da requisição até o processamento do retorno.

### Dependências

* [`PaymentRequest`](./PaymentRequest.md)
* [`PaymentResult`](./PaymentResult.md)
* [`PaymentException`](./Exception/PaymentException.md)
* [`ValidationException`](./Exception/ValidationException.md)

---

## 🚀 Construtor

### `__construct(string $posID, string $posAutCode, ?string $endpoint = null)`

Inicializa o cliente de pagamentos.

| Parâmetro     | Tipo     | Descrição                       |                                                             |
| ------------- | -------- | ------------------------------- | ----------------------------------------------------------- |
| `$posID`      | `string` | Código do Ponto de Venda (POS). |                                                             |
| `$posAutCode` | `string` | Código secreto de autorização.  |                                                             |
| `$endpoint`   | `string  | null`                           | URL do gateway (opcional, usa o padrão `DEFAULT_BASE_URL`). |

---

## ⚙️ Constantes

| Nome                               | Valor                                            | Descrição                                |
| ---------------------------------- | ------------------------------------------------ | ---------------------------------------- |
| `DEFAULT_BASE_URL`                 | `https://mc.vinti4net.cv/BizMPIOnUs/CardPayment` | URL padrão do gateway.                   |
| `TRANSACTION_TYPE_PURCHASE`        | `'1'`                                            | Transação de compra.                     |
| `TRANSACTION_TYPE_SERVICE_PAYMENT` | `'2'`                                            | Pagamento de serviço.                    |
| `TRANSACTION_TYPE_RECHARGE`        | `'3'`                                            | Recarga.                                 |
| `CURRENCY_CVE`                     | `'132'`                                          | Código ISO do Escudo Cabo-verdiano.      |
| `SUCCESS_MESSAGE_TYPES`            | `['8', '10', 'P', 'M']`                          | Tipos de mensagens consideradas sucesso. |

---

## 💳 Métodos Públicos

### `createPurchasePayment(float $amount, string $responseUrl, array $extra = []): PaymentRequest`

Cria um objeto de requisição para uma **Compra (Purchase)**.
O `billingData` deve ser definido separadamente via `setBilling()`.

**Exemplo:**

```php
$client = new PaymentClient('90000443', 'ABC12345');
$request = $client->createPurchasePayment(1000, 'https://site/callback');
$request->setBilling([
    'billAddrCountry' => 'CV',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Avenida Principal',
    'billAddrPostCode' => '7600',
    'email' => 'cliente@example.com'
]);
echo $client->renderPaymentForm($request);
```

---

### `createServicePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extra = []): PaymentRequest`

Cria uma requisição para **Pagamento de Serviço**.

**Exemplo:**

```php
$req = $client->createServicePayment(1500, 'https://site/callback', '3100', '12345678');
```

---

### `createRechargePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extra = []): PaymentRequest`

Cria uma requisição de **Recarga** (ex: telemóvel).

**Exemplo:**

```php
$req = $client->createRechargePayment(500, 'https://site/callback', '3010', '9812345');
```

---

### `renderPaymentForm(PaymentRequest $prequest): string`

Renderiza um formulário HTML de **auto-submissão** ao gateway.

| Retorno | Tipo     | Descrição                                     |
| ------- | -------- | --------------------------------------------- |
| HTML    | `string` | Código HTML pronto para submissão automática. |

---

### `processResponse(array $postData): PaymentResult`

Valida e interpreta a resposta (callback) enviada pelo gateway.

| Parâmetro   | Tipo    | Descrição                          |
| ----------- | ------- | ---------------------------------- |
| `$postData` | `array` | Dados POST do callback (`$_POST`). |

| Retorno         | Tipo     | Descrição                                              |
| --------------- | -------- | ------------------------------------------------------ |
| `PaymentResult` | `object` | Objeto contendo status, mensagem e dados da transação. |

**Possíveis Status:**

* `SUCCESS` — Transação concluída e FingerPrint válido;
* `FINGERPRINT_INVALIDO` — Erro de segurança na verificação;
* `CANCELLED` — Cancelamento pelo utilizador;
* `FAILURE` — Rejeição pela entidade emissora;
* `ERROR` — Falha de comunicação ou erro desconhecido.

---

## 🔒 Métodos Protegidos

### `generateRequestFingerPrint(array $data): string`

Gera o **FingerPrint** da requisição (envio).

### `generateSuccessfulResponseFingerPrint(array $data): string`

Gera o **FingerPrint** de uma resposta (callback) bem-sucedida.

### `generatePurchaseRequest(array $billingData): string`

Gera o **PurchaseRequest** para 3DS em Base64 (validação forte).

### `processPaymentRequest(PaymentRequest $prequest): array`

Monta o payload completo com `postUrl` e `fields`.

---

## 🧾 Exemplo Completo

```php
use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient('90000443', 'XYZSECRET');

// Criar pagamento de compra
$request = $client->createPurchasePayment(2500, 'https://meusite.com/callback');

// Dados de cobrança obrigatórios
$request->setBilling([
    'billAddrCountry' => 'CV',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Avenida Central',
    'billAddrPostCode' => '7600',
    'email' => 'cliente@exemplo.cv'
]);

// Renderizar o formulário HTML para submissão
echo $client->renderPaymentForm($request);
```

---

## 🧠 Observações Importantes

* O valor (`amount`) deve ser informado em unidades inteiras (Ex: `1000` = 1000 CVE).
  Internamente, o sistema converte para milésimos conforme exigido pela Vinti4Net.
* Sempre valide o FingerPrint no retorno (isso já é feito automaticamente por `processResponse()`).
* Em ambiente de **testes**, use endpoints próprios disponibilizados pela Vinti4Net.
