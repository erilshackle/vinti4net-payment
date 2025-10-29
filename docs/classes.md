Perfeito 👌 — agora entramos na parte **técnica da documentação das classes PHP**, que vai dentro da pasta `docs/classes/` e pode ser linkada no menu do `mkdocs.yml`.

Vamos fazer isso de forma profissional, semelhante à doc oficial do Laravel, Symfony ou Stripe SDK, com explicação e exemplos rápidos por classe.

---

## 📁 Estrutura sugerida

```
docs/
 ├── index.md
 ├── 01-compra.md
 ├── 02-pagamento-servico.md
 ├── 03-recarga.md
 ├── 04-callback.md
 └── classes/
     ├── PaymentClient.md
     ├── PaymentRequest.md
     ├── PaymentResult.md
     ├── Fingerprint.md
     └── Exceptions.md
```

---

### `docs/classes/PaymentClient.md`

````markdown
# 💼 Classe: `PaymentClient`

Classe principal para comunicação com o **Vinti4Net Gateway**.  
Responsável por gerar fingerprints, renderizar formulários e validar callbacks.

---

## 🧩 Namespace

```php
use Erilshk\Vinti4Net\PaymentClient;
````

---

## 🔧 Construtor

```php
__construct(string $posID, string $posAutCode, ?string $endpoint = null)
```

**Parâmetros**

| Nome          | Tipo   | Descrição                                              |                                                      |
| ------------- | ------ | ------------------------------------------------------ | ---------------------------------------------------- |
| `$posID`      | string | Identificador do terminal (POS ID) fornecido pela SISP |                                                      |
| `$posAutCode` | string | Código secreto de autenticação                         |                                                      |
| `$endpoint`   | string | null                                                   | (Opcional) URL do endpoint — usa produção por padrão |

---

## 🧾 Métodos Principais

### `renderPaymentForm(PaymentRequest $request): string`

Gera o HTML completo de submissão para o gateway.

```php
echo $client->renderPaymentForm($request);
```

---

### `processResponse(array $postData): PaymentResult`

Processa e valida os dados recebidos no callback do Vinti4Net.

```php
$result = $client->processResponse($_POST);
```

---

### `createServicePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber): PaymentRequest`

Cria uma requisição de **Pagamento de Serviço**.

---

### `createRechargePayment(float $amount, string $responseUrl, string $entityCode, string $referenceNumber): PaymentRequest`

Cria uma requisição de **Recarga**.

---

### `verifyFingerprint(array $data, string $fingerprint): bool`

Valida a integridade de um Fingerprint recebido do gateway.

---

## ⚙️ Exemplo Completo

```php
$client = new PaymentClient('90000443', 'ABCDE12345');
$request = $client->createServicePayment(500, 'https://meusite.cv/callback', '123', '987654');
echo $client->renderPaymentForm($request);
```

````

---

### `docs/classes/PaymentRequest.md`

```markdown
# 🧾 Classe: `PaymentRequest`

Objeto DTO (Data Transfer Object) que encapsula os dados de uma transação.

---

## 🧩 Namespace

```php
use Erilshk\Vinti4Net\PaymentRequest;
````

---

## 🔧 Construtor

```php
__construct(int|float|string $amount, string $responseUrl, int|string $transactionCode)
```

| Parâmetro          | Tipo   | Descrição                          |                                                         |
| ------------------ | ------ | ---------------------------------- | ------------------------------------------------------- |
| `$amount`          | float  | string                             | Valor da transação                                      |
| `$responseUrl`     | string | URL que receberá o POST de retorno |                                                         |
| `$transactionCode` | string | int                                | Tipo da transação: 1 (Compra), 2 (Serviço), 3 (Recarga) |

---

## 🧱 Propriedades

| Propriedade        | Tipo    | Descrição                             |
| ------------------ | ------- | ------------------------------------- |
| `$amount`          | string  | Valor da transação                    |
| `$transactionCode` | string  | Tipo da transação                     |
| `$responseUrl`     | string  | URL de callback                       |
| `$merchantRef`     | string  | Referência única da loja              |
| `$merchantSession` | string  | Sessão única da loja                  |
| `$entityCode`      | ?string | Código da entidade (Serviços/Recarga) |
| `$referenceNumber` | ?string | Número de referência                  |
| `$billingData`     | array   | Dados de cobrança (3DS)               |

---

## 🧩 Métodos

### `setBilling(string $email, string $country, string $city, string $address, string $postalCode, array $acctInfo = [], array $aditionals = []): self`

Define os dados de cobrança para 3D Secure.

```php
$request->setBilling(
    'cliente@exemplo.com', '132', 'Praia', 'Av. Cidade de Lisboa, 12', '7600'
);
```

````

---

### `docs/classes/PaymentResult.md`

```markdown
# 📄 Classe: `PaymentResult`

DTO para padronizar o resultado do processamento da resposta do Vinti4Net.

---

## 🧩 Namespace

```php
use Erilshk\Vinti4Net\PaymentResult;
````

---

## 🔧 Construtor

```php
__construct(string $status, string $message, bool $isSuccessful, array $data, array $debugInfo = [])
```

---

## 🧱 Propriedades

| Nome            | Tipo   | Descrição                              |
| --------------- | ------ | -------------------------------------- |
| `$status`       | string | Status da transação                    |
| `$message`      | string | Mensagem do sistema                    |
| `$isSuccessful` | bool   | Indica se o pagamento foi bem-sucedido |
| `$data`         | array  | Dados brutos da resposta               |
| `$debugInfo`    | array  | Informações internas úteis para log    |

---

## 🚦 Métodos

### `isValid(): bool`

Verifica se o status é reconhecido.

### `succeeded(): bool`

Retorna `true` se a transação foi bem-sucedida.

### `failed(): bool`

Retorna `true` se a transação falhou.

### `generateReceipt(): string`

Gera um HTML simples com o recibo do pagamento.

```php
echo $result->generateReceipt();
```

````

---

### `docs/classes/Fingerprint.md`

```markdown
# 🔐 Classe: `Fingerprint`

Classe utilitária para geração e validação de fingerprints.

---

## 🧩 Namespace

```php
use Erilshk\Vinti4Net\Fingerprint;
````

---

## 🧩 Métodos

### `generate(array $fields, string $posAutCode): string`

Gera o hash FingerPrint para envio de requisição.

### `validate(array $fields, string $posAutCode, string $received): bool`

Valida um fingerprint recebido no callback.

```php
$isValid = Fingerprint::validate($_POST, 'SEU_POS_AUT_CODE', $_POST['FingerPrint']);
```

````

---

### `docs/classes/Exceptions.md`

```markdown
# ⚠️ Exceções

O SDK lança exceções específicas para diferenciar erros de dados e falhas internas.

---

## `ValidationException`

> Namespace: `Erilshk\Vinti4Net\Exception\ValidationException`

Lançada quando campos obrigatórios estão ausentes ou incorretos.

```php
throw new ValidationException("BillingData ausente para transação de compra.");
````

---

## `PaymentException`

> Namespace: `Erilshk\Vinti4Net\Exception\PaymentException`

Lançada em falhas internas do SDK, como erro ao gerar Fingerprint ou codificar JSON.

```

---

Se quiser, posso agora **gerar o `mkdocs.yml` completo** com menu hierárquico:  
- “Guia Rápido” (Compra, Recarga, etc.)  
- “Referência de Classes” (PaymentClient, Request, Result, etc.)  

Quer que eu monte o `mkdocs.yml` com tudo pronto e bonito (com Material for MkDocs)?
```
