<?php

namespace Erilshk\Vinti4Pay;

/**
 * DTO para encapsular o resultado de uma transação processada pelo gateway Vinti4Net.
 *
 * Usada para dar acesso prático ao resultado retornado pelos métodos
 * processResponse() e processRefundResponse().
 *
 * @package Erilshk\Vinti4Pay
 */
class Vinti4Result
{
    /** @var string */
    public string $status = 'ERROR';

    /** @var string */
    public string $message = '';

    /** @var bool */
    public bool $isSuccessful = false;

    /** @var array */
    public array $data = [];

    /** @var array|null */
    public ?array $debugInfo = null;

    /** @var array|null */
    public ?array $dcc = null;

    /** @var string|null */
    public ?string $detail = null;

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';

    /**
     * Cria a instância a partir de um array retornado por processResponse() ou processRefundResponse().
     *
     * @param array $result
     * @return static
     */
    public function __construct(array|object $result)
    {
        if ($result instanceof Vinti4Result) {
            $result = $result->toArray();
        }

        $this->status = $result['status'] ?? self::STATUS_ERROR;
        $this->message = $result['message'] ?? '';
        $this->isSuccessful = (bool)($result['success'] ?? false);
        $this->data = $result['data'] ?? [];
        $this->debugInfo = $result['debug'] ?? null;
        $this->dcc = $result['dcc'] ?? null;
        $this->detail = $result['detail'] ?? null;
    }

    /**
     * Executa callback se a transação for bem-sucedida.
     * @param callable(Vinti4Result $r): void
     */
    public function onSuccessfulTransaction(callable $callback): self
    {
        if ($this->isSuccessful && $this->status === self::STATUS_SUCCESS) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Executa callback se a transação falhar.
     * @param callable(Vinti4Result $r): void
     */
    public function onFailedTransaction(callable $callback): self
    {
        if (!$this->isSuccessful && $this->status === self::STATUS_ERROR) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Executa callback se a transação for cancelada.
     * @param callable(Vinti4Result $r): void
     */
    public function onCancelledTransaction(callable $callback): self
    {
        if ($this->status === self::STATUS_CANCELLED) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Retorna true se o fingerprint for inválido.
     */
    public function hasInvalidFingerprint(): bool
    {
        return $this->status === self::STATUS_INVALID_FINGERPRINT;
    }

    /**
     * Retorna uma string com o status amigável.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'Transação bem-sucedida',
            self::STATUS_CANCELLED => 'Transação cancelada pelo utilizador',
            self::STATUS_INVALID_FINGERPRINT => 'Fingerprint inválido',
            default => 'Erro na transação',
        };
    }

    /**
     * Retorna uma cópia segura como array.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'success' => $this->isSuccessful,
            'data' => $this->data,
            'debug' => $this->debugInfo,
            'dcc' => $this->dcc,
            'detail' => $this->detail,
        ];
    }

    /**
     * Retorna o dados em formatos json.
     * ideal para quardarna BD
     */
    public function jsonData(): string
    {
        return json_encode($this->data);
    }

    /**
     * Gera um recibo HTML baseado nos dados da transação.
     */
    public function generateReceipt(): string
    {
        $data = $this->data;
        $dcc = $this->dcc ?? [];

        $escape = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE);

        $merchantRef = $escape($data['merchantRespMerchantRef'] ?? '');
        $merchantSession = $escape($data['merchantRespMerchantSession'] ?? '');
        $rawAmount = (float)($data['merchantRespPurchaseAmount'] ?? '0');
        $formattedAmount = number_format($rawAmount, 2, ',', '.');
        $transactionCurrency = $escape($data['merchantRespPurchaseCurrency'] ?? 'CVE');
        $errorDescription = $escape($data['merchantRespErrorDescription'] ?? '');
        $errorDetail = $escape($data['merchantRespErrorDetail'] ?? '');
        $additionalError = $escape($data['merchantRespAdditionalErrorMessage'] ?? '');

        $html = "<div class='vinti4-receipt' style='font-family:Arial,sans-serif;line-height:1.5;max-width:600px;margin:auto;padding:15px;border:1px solid #ccc;border-radius:8px;'>";
        $html .= "<h2 style='text-align:center; color:" . ($this->isSuccessful ? '#28a745' : '#dc3545') . ";'>Recibo de Pagamento</h2>";
        $html .= "<p><strong>Status:</strong> {$escape($this->getStatusLabel())}</p>";

        if (!$this->isSuccessful && $this->status !== self::STATUS_CANCELLED) {
            $html .= "<p style='color:#dc3545;'><strong>Mensagem de Falha:</strong> {$errorDescription} {$errorDetail} {$additionalError}</p>";
        } else {
            $html .= "<p><strong>Mensagem:</strong> {$escape($this->message)}</p>";
        }

        $html .= "<hr style='border:0;height:1px;background:#eee;'>";
        $html .= "<p><strong>Referência:</strong> {$merchantRef}</p>";
        $html .= "<p><strong>Montante:</strong> {$formattedAmount} {$transactionCurrency}</p>";
        $html .= "<p><strong>Sessão:</strong> {$merchantSession}</p>";

        if (!empty($dcc)) {
            $dccAmount = $escape($dcc['amount'] ?? 0);
            $dccCurrency = $escape($dcc['currency'] ?? '');
            $dccMarkup = $escape($dcc['markup'] ?? 0);
            $dccRate = $escape($dcc['rate'] ?? 0);

            $html .= "<hr style='border:0;height:1px;background:#eee;'>";
            $html .= "<h3 style='color:#007bff;'>Dynamic Currency Conversion (DCC)</h3>";
            $html .= "<p><strong>Moeda de Conversão:</strong> {$dccCurrency}</p>";
            $html .= "<p><strong>Montante DCC:</strong> {$dccAmount} {$dccCurrency}</p>";
            $html .= "<p><strong>Taxa:</strong> 1 {$dccCurrency} = {$dccRate} {$transactionCurrency}</p>";
            $html .= "<p><strong>Markup:</strong> {$dccMarkup} {$dccCurrency}</p>";
        }

        $html .= "<hr style='border:0;height:1px;background:#eee;'>";
        $html .= "<p style='font-size:0.8em;color:#777;'>Recibo gerado automaticamente.</p>";
        $html .= "</div>";

        return $html;
    }
}
