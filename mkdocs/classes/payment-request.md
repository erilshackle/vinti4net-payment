# `Erilshk\Vinti4Net\PaymentRequest`

> DTO (Data Transfer Object) responsável por encapsular todos os dados de uma transação no **Vinti4Net Payment Gateway**.

Esta classe é utilizada para transportar e validar informações entre a criação da requisição e o envio ao gateway, garantindo consistência dos dados e suporte a **3D Secure (3DS)** quando aplicável.

---

## 🧩 Namespace

```
Erilshk\Vinti4Net
```

## 📦 Pacote

```
erilshk/vinti4net-payment
```

---

## 🏗️ Classe: `PaymentRequest`

### Descrição

Representa uma requisição de pagamento, contendo informações sobre o valor, tipo de transação, URLs de resposta, e dados de cobrança (billing).

Geralmente, é criada através de métodos da classe [`PaymentClient`](./PaymentClient.md), mas também pode ser instanciada manualmente em casos específicos.

---

## 🔧 Propriedades

| Propriedade         | Tipo                   | Padrão  | Descrição                                                                           |                                                            |
| ------------------- | ---------------------- | ------- | ----------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `$amount`           | `string`               | —       | Valor da transação (ex: `"1000.50"`).                                               |                                                            |
| `$transactionCode`  | `string`               | —       | Código da transação: `'1'` = Compra, `'2'` = Pagamento de Serviço, `'3'` = Recarga. |                                                            |
| `$responseUrl`      | `string`               | —       | URL de callback para retorno da transação.                                          |                                                            |
| `$merchantRef`      | `string`               | `''`    | Referência única da transação no sistema do comerciante.                            |                                                            |
| `$merchantSession`  | `string`               | `''`    | Identificador único de sessão do comerciante.                                       |                                                            |
| `$entityCode`       | `string                | null`   | `null`                                                                              | Código da Entidade (para Pagamentos de Serviço/Recarga).   |
| `$referenceNumber`  | `string                | null`   | `null`                                                                              | Número de referência (para Pagamentos de Serviço/Recarga). |
| `$languageMessages` | `string`               | `'pt'`  | Código de idioma da interface/mensagens.                                            |                                                            |
| `$currency`         | `string`               | `'132'` | Código ISO da moeda (132 = CVE).                                                    |                                                            |
| `$billingData`      | `array<string, mixed>` | `[]`    | Dados de cobrança (3DS) e campos adicionais.                                        |                                                            |

---

## 🧠 Constantes Relevantes

Esta classe não define constantes próprias, mas utiliza os valores definidos em [`PaymentClient`](./PaymentClient.md):

* `TRANSACTION_TYPE_PURCHASE = '1'`
* `TRANSACTION_TYPE_SERVICE_PAYMENT = '2'`
* `TRANSACTION_TYPE_RECHARGE = '3'`

---

## 🚀 Construtor

### `__construct(int|float|string $amount, string $responseUrl, int|string $transactionCode)`

Cria uma nova instância de `PaymentRequest`.

| Parâmetro          | Tipo                     | Descrição                                                |
| ------------------ | ------------------------ | -------------------------------------------------------- |
| `$amount`          | `int \| float \| string` | Valor da transação (ex: `1000.50`).                      |
| `$responseUrl`     | `string`                 | URL de callback para onde o gateway enviará o resultado. |
| `$transactionCode` | `int \| string`          | Tipo da transação (1, 2 ou 3).                           |

**Exemplo:**

```php
use Erilshk\Vinti4Net\PaymentRequest;

$request = new PaymentRequest(1500, 'https://site.com/callback', 1);
$request->merchantRef = 'ORDER-001';
$request->merchantSession = 'SESSION-ABC123';
```

---

## 💳 Métodos Públicos

### `setBilling(string $email, string $country, string $city, string $address, string $postalCode, array $acctInfo = [], array $aditionals = []): self`

Define os dados de cobrança (billing), **obrigatórios para transações de Compra (3DS)**.

| Parâmetro     | Tipo                   | Descrição                                                                              |
| ------------- | ---------------------- | -------------------------------------------------------------------------------------- |
| `$email`      | `string`               | Email do titular do cartão.                                                            |
| `$country`    | `string`               | Código do país (ex: `'132'` para Cabo Verde).                                          |
| `$city`       | `string`               | Cidade.                                                                                |
| `$address`    | `string`               | Endereço principal (linha 1).                                                          |
| `$postalCode` | `string`               | Código postal.                                                                         |
| `$acctInfo`   | `array<string, mixed>` | Informações opcionais da conta (ex: `chAccAgeInd`, `chAccChange`, `chAccPwChangeInd`). |
| `$aditionals` | `array<string, mixed>` | Campos adicionais (ex: `addrMatch`, `shipAddrLine2`, `shipAddrState`).                 |

**Retorna:**
`self` — permite encadeamento de métodos.

**Exemplo:**

```php
$request->setBilling(
    email: 'cliente@exemplo.com',
    country: '132',
    city: 'Praia',
    address: 'Av. Cidade de Lisboa, 12',
    postalCode: '7600'
);
```

---

### `addBillingExtras(array $extra): self`

Adiciona dados extras opcionais de billing **sem sobrescrever** os já definidos.

| Parâmetro | Tipo                   | Descrição                      |
| --------- | ---------------------- | ------------------------------ |
| `$extra`  | `array<string, mixed>` | Campos adicionais de cobrança. |

**Exemplo:**

```php
$request->addBillingExtras([
    'shipAddrLine2' => '2º Andar',
    'shipAddrState' => 'Santiago'
]);
```

---

## 🧾 Exemplo Completo

```php
use Erilshk\Vinti4Net\PaymentRequest;

// Cria o objeto de requisição
$request = new PaymentRequest(1500, 'https://site.com/callback', 1);
$request->merchantRef = 'ORDER-20251001';
$request->merchantSession = uniqid('sess_', true);

// Define os dados de billing obrigatórios (3DS)
$request->setBilling(
    email: 'cliente@exemplo.cv',
    country: '132',
    city: 'Praia',
    address: 'Avenida Cidade de Lisboa, 12',
    postalCode: '7600',
    aditionals: [
    'shipAddrLine2' => 'Bairro Palmarejo',
    'addrMatch' => 'Y'
]);
```

---

## 📘 Observações

* O campo `billingData` deve estar completo **apenas para transações de Compra (1)**.
  Pagamentos de serviço e recargas não exigem esse preenchimento.
* A propriedade `currency` tem valor padrão `132` (CVE), mas pode ser alterada conforme necessário.
* Essa classe é **imutável** nas propriedades principais (`readonly`) — o valor, tipo e URL são definidos no construtor e não podem ser alterados depois.
