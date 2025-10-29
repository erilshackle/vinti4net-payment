# 🔄 Processamento de Callback

A URL definida em `responseUrl` receberá o POST do Vinti4Net.

---

## 1️⃣ Processando a Resposta

```php
use Erilshk\Vinti4Net\PaymentClient;

$client = new PaymentClient(posId: 'VINTI4_POS_ID', posAutCode: 'VINTI4_POS_AUTCODE');

// Processa o POST enviado pelo gateway
$result = $client->processResponse($_POST);
```

## 2️⃣ Verificando o Resultado

```php
if ($result->succeeded()) {
    echo "Pagamento aprovado!";
    $referencia = $result->data['merchantRespMerchantRef'];
    echo $result->generateReceipt(); // opcional
} elseif ($result->status === $result::STATUS_FINGERPRINT_INVALID) {
    error_log("Falha de segurança: Fingerprint inválido!");
} else {
    echo "Pagamento falhou ou foi cancelado.";
    echo $result->message;
}
```

---

## 3️⃣ Observações

* Sempre verifique o `FingerPrint` com `hash_equals`.
* Atualize o status do pedido ou recarga apenas se `succeeded()` for `true`.
* Para segurança, trate `STATUS_FINGERPRINT_INVALID` como fraude em potencial.

