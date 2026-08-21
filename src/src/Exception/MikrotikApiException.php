<?php
namespace App\Exception;

/**
 * Exceção lançada quando a API REST do Mikrotik retorna erro (HTTP >= 400).
 * 
 * Contém o código de erro, mensagem e detalhes retornados pelo RouterOS.
 */
class MikrotikApiException extends \RuntimeException {
    
    private int $apiErrorCode;
    private string $detail;
    private string $endpoint;
    private string $method;
    private int $httpStatus;
    
    public function __construct(
        string $message,
        int $apiErrorCode = 0,
        string $detail = '',
        string $endpoint = '',
        string $method = '',
        int $httpStatus = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $apiErrorCode, $previous);
        
        $this->apiErrorCode = $apiErrorCode;
        $this->detail = $detail;
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->httpStatus = $httpStatus;
    }
    
    /**
     * Código de erro interno do RouterOS (ex: 0, no such item, etc.)
     */
    public function getApiErrorCode(): int {
        return $this->apiErrorCode;
    }
    
    /**
     * Detalhe adicional do erro retornado pela API
     */
    public function getDetail(): string {
        return $this->detail;
    }
    
    /**
     * Endpoint que foi chamado quando ocorreu o erro
     */
    public function getEndpoint(): string {
        return $this->endpoint;
    }
    
    /**
     * Método HTTP utilizado (GET, POST, PUT, PATCH, DELETE)
     */
    public function getMethod(): string {
        return $this->method;
    }
    
    /**
     * Código HTTP de status retornado
     */
    public function getHttpStatus(): int {
        return $this->httpStatus;
    }
    
    /**
     * Mensagem formatada para exibição amigável
     */
    public function getUserMessage(): string {
        $msg = "Erro na API Mikrotik: {$this->getMessage()}";
        
        if ($this->detail !== '') {
            $msg .= " ({$this->detail})";
        }
        
        if ($this->endpoint !== '') {
            $msg .= " [{$this->method} {$this->endpoint}]";
        }
        
        return $msg;
    }
}
