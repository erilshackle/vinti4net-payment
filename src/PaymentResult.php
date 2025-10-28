<?php

namespace Erilshk\Vinti4Net;

/**
 * Classe DTO para encapsular o resultado de uma transação processada pelo gateway Vinti4Net.
 *
 * @package Erilshk\Vinti4Net
 */
class PaymentResult
{
    /** @var string Status da transação */
    public string $status;

    /** @var string Mensagem de resultado */
    public string $message;

    /** @var bool Indica se a transação foi bem-sucedida */
    public bool $isSuccessful;

    /** @var array Dados brutos recebidos do gateway ($_POST ou array equivalente) */
    public array $data;

    /** @var array Informações adicionais de debug (ex: fingerprint calculado/recebido) */
    public array $debugInfo;

    /** Status possíveis */
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_FAILURE = 'FAILURE';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_FINGERPRINT_INVALID = 'FINGERPRINT_INVALIDO';

    /**
     * Construtor.
     *
     * @param string $status Status da transação
     * @param string $message Mensagem de resultado
     * @param bool $isSuccessful True se a transação foi bem-sucedida
     * @param array $data Dados brutos da resposta
     * @param array $debugInfo Informações adicionais de debug
     */
    public function __construct(string $status, string $message, bool $isSuccessful, array $data, array $debugInfo = [])
    {
        $this->status = $status;
        $this->message = $message;
        $this->isSuccessful = $isSuccessful;
        $this->data = $data;
        $this->debugInfo = $debugInfo;
    }

    /**
     * Verifica se o status é válido (SUCCESS, CANCELLED ou FINGERPRINT_INVALIDO).
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_CANCELLED,
            self::STATUS_FINGERPRINT_INVALID
        ], true);
    }

    /**
     * Indica se a transação foi bem-sucedida.
     *
     * @return bool
     */
    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Indica se a transação falhou.
     *
     * @return bool
     */
    public function failed(): bool
    {
        return $this->status !== self::STATUS_SUCCESS;
    }

    /**
     * Gera um recibo HTML seguro baseado nos dados da transação.
     *
     * @return string HTML seguro
     */
    public function generateReceipt(): string
    {
        $data = $this->data;
        $dcc = $data['dcc'] ?? [];

        $escape = fn($value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE);

        $merchantRef = $escape($data['merchantRespMerchantRef'] ?? '');
        $merchantSession = $escape($data['merchantRespMerchantSession'] ?? '');
        $rawAmount = (float)($data['merchantRespPurchaseAmount'] ?? '0');
        $formattedAmount = number_format($rawAmount, 2, ',', '.');
        $transactionCurrency = $escape($data['merchantRespPurchaseCurrency'] ?? 'CVE');
        $messageType = $escape($data['messageType'] ?? '');
        $merchantResp = $escape($data['merchantResp'] ?? '');
        $errorDescription = $escape($data['merchantRespErrorDescription'] ?? '');
        $errorDetail = $escape($data['merchantRespErrorDetail'] ?? '');
        $additionalError = $escape($data['merchantRespAdditionalErrorMessage'] ?? '');

        $status = $this->status;
        $displayMessage = $this->message;

        $html = "<div class='vinti4-receipt' style='font-family:Arial,sans-serif;line-height:1.5;max-width:600px;margin:auto;padding:15px;border:1px solid #ccc;border-radius:8px;'>";
        $html .= "<h2 style='text-align:center; color:" . ($this->isSuccessful ? '#28a745' : '#dc3545') . ";'>Recibo de Pagamento</h2>";
        $html .= "<p><strong>Status da Transação:</strong> {$escape($status)}</p>";

        if (!$this->isSuccessful && $status !== self::STATUS_CANCELLED) {
            $html .= "<p style='color:#dc3545;'><strong>Mensagem de Falha:</strong> {$errorDescription} {$errorDetail} {$additionalError}</p>";
        } else {
            $html .= "<p><strong>Mensagem do Sistema:</strong> {$escape($displayMessage)}</p>";
        }

        $html .= "<hr style='border:0;height:1px;background:#eee;'>";
        $html .= "<p><strong>Referência (MerchantRef):</strong> {$merchantRef}</p>";
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
            $html .= "<p><strong>Markup (Taxa do Serviço):</strong> {$dccMarkup} {$dccCurrency}</p>";
        }

        $html .= "<hr style='border:0;height:1px;background:#eee;'>";
        $html .= "<p style='font-size:0.8em;color:#777;'>Recibo gerado automaticamente.</p>";
        $html .= "</div>";

        return $html;
    }
}
