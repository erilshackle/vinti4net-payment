# 💳 Vinti4Net PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/erilshk/vinti4pay.svg?style=flat-square)](https://packagist.org/packages/erilshk/vinti4net-payment)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=flat-square&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)

> SDK  em **PHP** para integração com o sistema de pagamentos **Vinti4Net (SISP Cabo Verde)**.  
> Focado em clareza, segurança e total compatibilidade com as especificações **MOP021**.

---

## 🚀 Visão Geral

O **Vinti4Net PHP SDK** simplifica a integração entre o seu sistema e o **gateway Vinti4Net**, permitindo criar formulários de pagamento, processar respostas de callbacks e gerir reembolsos (reversões) de forma padronizada.

Com ele, você pode:

- ✅ Criar formulários HTML de **pagamento** (Purchase)
- 🔁 Executar **reembolsos** (Reversal / Refund)
- 🧾 Processar e validar **respostas de callback**
- 🔐 Garantir a **integridade de transações** com *fingerprint SHA-512*
- 💼 Integrar tanto com **Composer** quanto com **arquivos independentes** (modo manual)

---

## ⚙️ Requisitos

| Requisito | Versão mínima | Observação |
|------------|----------------|-------------|
| **PHP** | >= 8.0 | Tipagem forte e suporte moderno a `hash('sha512')` |
| **Extensão cURL** | Ativa | Necessária para comunicação HTTPS |
| **HTTPS** | Obrigatório | Todos os endpoints do Vinti4 exigem HTTPS |
| **Credenciais Vinti4** | Válidas | `posID` e `posAutCode` fornecidos pela SISP |

---

## 📦 Instalação

### 🧩 Via Composer

```bash
composer require erilshk/vinti4pay-php
```

Importe a classe conforme o uso:

```php
use Erilshk\Vinti4Pay\Vinti4Pay;
use Erilshk\Vinti4Pay\Vinti4Refund;
```

---

### 📁 Instalação Manual (sem Composer)

Se preferir não usar o Composer, baixe as classes independentes:

| Arquivo            | Descrição                                        | Download                                                                       |
| ------------------ | ------------------------------------------------ | ------------------------------------------------------------------------------ |
| `Vinti4Pay.php`    | Classe de **pagamentos (Purchase)** em português | [Baixar ›](https://github.com/erilshk/vinti4net-payment/dist/Vinti4Pay.php)    |
| `Vinti4Refund.php` | Classe de **reembolsos (Refund)** em inglês      | [Baixar ›](https://github.com/erilshk/vinti4net-payment/dist/Vinti4Refund.php) |

Uso direto:

```php
require 'Vinti4Pay.php';

$vinti4 = new Vinti4Pay('90000443', 'AUTHCODE123');
$formHtml = $vinti4->createPurchaseForm(1000, 'https://meusite.cv/callback.php', [
    'billAddrCountry' => 'CV',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Av. Amílcar Cabral',
    'billAddrPostCode' => '7600',
    'email' => 'cliente@exemplo.cv'
]);

echo $formHtml;
```

---

## 💰 Pagamentos (Purchase)

Crie um formulário HTML completo e pronto para submissão ao gateway:

```php
use Erilshk\Vinti4Pay\Vinti4Pay;

$vinti4 = new Vinti4Pay('90000443', 'AUTHCODE123');

echo $vinti4->createPurchaseForm(
    2500,
    'https://meusite.cv/callback.php',
    [
        'billAddrCountry' => 'CV',
        'billAddrCity' => 'Mindelo',
        'billAddrLine1' => 'Rua Lisboa',
        'billAddrPostCode' => '7110',
        'email' => 'cliente@mindelo.cv'
    ]
);
```

🔄 Após o envio, o cliente será redirecionado para o ambiente seguro da **Vinti4Net**, preencherá os dados do cartão e, ao confirmar, será feita uma chamada automática (POST) ao **callback URL** informado.

📖 [Veja a documentação completa de pagamento ›](docs/vinti4Pay.md)

---

## 🧾 Callback (Resposta do Gateway)

O endpoint de callback deve processar a resposta recebida via `$_POST`:

```php
use Erilshk\Vinti4Pay\Vinti4Pay;

$vinti4 = new Vinti4Pay('90000443', 'AUTHCODE123');
$result = $vinti4->processResponse($_POST);

if ($result['success']) {
    echo "✅ Pagamento confirmado!";
} else {
    echo "❌ Falha: " . $result['message'];
}
```

📖 [Documentação detalhada do callback ›](docs/callback.md)

---

## 🔁 Reembolsos (Refund / Reversal)

Execute uma reversão de pagamento previamente concluído:

```php
use Erilshk\Vinti4Pay\Vinti4Refund;

$refund = new Vinti4Refund('90000443', 'AUTHCODE123');
$data = $refund->prepareRefund(
    'INV-1001',
    'SESSION-ABC',
    1000,
    '202501',
    'TID12345',
    'https://meusite.cv/refund-callback.php'
);
```

📖 [Documentação completa de reembolsos ›](docs/vinti4Refund.md)

---

## 🧠 Estrutura de Projeto Recomendada

```
project/
├── vendor/
├── dist/
│   ├── Vinti4Pay.php
│   └── Vinti4Refund.php
├── public/
│   ├── vinti4.php
│   └── refund-callback.php
├── docs/
│   ├── vinti4Pay.md
│   ├── callback.md
│   └── vinti4Refund.md
└── composer.json
```

---

## 🧪 Testes Locais

Você pode testar o fluxo de pagamento localmente executando:

```bash
php -S localhost:8000 -t public
```

E criando um formulário simples:

```html
<form method="POST" action="http://localhost:8000/vinti4.php">
  <label>Valor (CVE):</label>
  <input type="number" name="amount" value="1000">
  <button type="submit">Testar Pagamento</button>
</form>
```

---

## 🧾 Licença

Este SDK é distribuído sob a **MIT License**.
Sinta-se livre para usar, modificar e distribuir conforme necessário.

📄 [Leia a licença completa ›](LICENSE)

---

## 👨‍💻 Autor

**Eril TS Carvalho**
Desenvolvedor PHP & Engenheiro de Software
[GitHub](https://github.com/erilshk) • [LinkedIn](https://linkedin.com/in/erilshk)

---

## 🌐 Documentação Completa

Toda a documentação técnica está disponível em formato **MkDocs**:

* [🏠 Introdução](https://erilshk.github.io/vinti4net-payment/)
* [💰 Pagamentos (Vinti4Pay)](https://erilshk.github.io/vinti4net-payment/vinti4Pay/)
* [📩 Callback](https://erilshk.github.io/vinti4net-payment/callback/)
* [🔁 Reembolsos (Vinti4Refund)](https://erilshk.github.io/vinti4net-payment/vinti4Refund/)

---

> 💬 “Pagamentos seguros e integrações simples — é disso que o Vinti4Pay PHP SDK cuida.”

```