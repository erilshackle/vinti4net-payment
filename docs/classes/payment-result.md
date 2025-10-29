# Classe `PaymentResult`

Namespace: `Erilshk\Vinti4Net`
Tipo: **DTO (Data Transfer Object)**

Classe responsável por encapsular o resultado de uma transação processada pelo gateway **Vinti4Net**, incluindo status, mensagem, dados brutos da resposta e informações de debug.

## Propriedades

| Propriedade                 | Tipo   | Descrição                                                             |
| --------------------------- | ------ | --------------------------------------------------------------------- |
| `public string $status`     | string | Status da transação retornado pelo gateway.                           |
| `public string $message`    | string | Mensagem de resultado da transação.                                   |
| `public bool $isSuccessful` | bool   | Indica se a transação foi bem-sucedida.                               |
| `public array $data`        | array  | Dados brutos recebidos do gateway (`$_POST` ou equivalente).          |
| `public array $debugInfo`   | array  | Informações adicionais de debug, como fingerprint calculado/recebido. |

---

## Constantes de Status

| Constante                    | Valor                    | Descrição                            |
| ---------------------------- | ------------------------ | ------------------------------------ |
| `STATUS_SUCCESS`             | `'SUCCESS'`              | Transação concluída com sucesso.     |
| `STATUS_CANCELLED`           | `'CANCELLED'`            | Transação cancelada pelo usuário.    |
| `STATUS_FAILURE`             | `'FAILURE'`              | Falha no processamento da transação. |
| `STATUS_ERROR`               | `'ERROR'`                | Erro interno ou exceção.             |
| `STATUS_FINGERPRINT_INVALID` | `'FINGERPRINT_INVALIDO'` | Falha na validação do fingerprint.   |

---

## Construtor

```php
public function __construct(
    string $status,
    string $message,
    bool $isSuccessful,
    array $data,
    array $debugInfo = []
)
```

**Parâmetros:**

* `$status` – Status da transação.
* `$message` – Mensagem de resultado da transação.
* `$isSuccessful` – Verdadeiro se a transação foi bem-sucedida.
* `$data` – Dados brutos recebidos do gateway.
* `$debugInfo` – Informações adicionais de debug (opcional).

---

## Métodos

### `isValid(): bool`

Verifica se o status da transação é válido (SUCCESS, CANCELLED ou FINGERPRINT_INVALIDO).

**Retorno:** `true` se o status for válido; caso contrário, `false`.

---

### `succeeded(): bool`

Indica se a transação foi bem-sucedida.

**Retorno:** `true` se o status for `SUCCESS`; caso contrário, `false`.

---

### `failed(): bool`

Indica se a transação falhou.

**Retorno:** `true` se o status for diferente de `SUCCESS`; caso contrário, `false`.

---

### `generateReceipt(): string`

Gera um **recibo HTML seguro** baseado nos dados da transação. Todos os valores são escapados com `htmlspecialchars` para prevenir ataques XSS.

**Retorno:** HTML do recibo, incluindo:

* Status da transação
* Montante e moeda
* Referência e sessão do comerciante
* Mensagem de erro (se houver)
* Bloco **DCC** (Dynamic Currency Conversion) se disponível

---

## Exemplo de Uso

```php
use Erilshk\Vinti4Net\PaymentResult;
use Erilshk\Vinti4Net\PaymentClient;

// Suponha que $response seja o array retornado pelo gateway
$client = new PaymentClient(posId: 'VINTI4_POS_ID', posAutCode: 'VINTI4_POS_AUTCODE');

$result = $client->processResponse($_POST);

if ($result->succeeded()) {
    echo "Pagamento realizado com sucesso!";
} else {
    echo "Falha na transação: " . $result->message;
}

// Gerar recibo HTML
echo $result->generateReceipt();
```
