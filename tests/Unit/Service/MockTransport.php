<?php
namespace Tests\Unit\Service;

use App\Service\HttpTransport;

/**
 * Mock HTTP Transport para testes.
 * 
 * Permite simular respostas da API Mikrotik sem fazer chamadas reais.
 * Permite configurar respostas por URL e método.
 */
class MockTransport implements HttpTransport {
    
    /** @var array Lista de respostas configuradas */
    private array $responses = [];
    
    /** @var array Histórico de chamadas recebidas */
    private array $calls = [];
    
    /**
     * Configura uma resposta para um padrão de URL/método.
     * 
     * @param string $method  Método HTTP (ou * para qualquer)
     * @param string $url     Padrão de URL (ou * para qualquer)
     * @param array  $response ['body' => string, 'httpCode' => int, 'error' => '', 'errno' => 0]
     */
    public function when(string $method, string $url, array $response): self {
        $this->responses[] = [
            'method'   => $method,
            'url'      => $url,
            'response' => $response,
        ];
        return $this;
    }
    
    /**
     * Configura resposta bem-sucedida com JSON.
     */
    public function whenJson(string $method, string $url, array $jsonData, int $httpCode = 200): self {
        return $this->when($method, $url, [
            'body'     => json_encode($jsonData),
            'httpCode' => $httpCode,
            'error'    => '',
            'errno'    => 0,
        ]);
    }
    
    /**
     * Configura erro de API Mikrotik (HTTP >= 400 com body JSON).
     */
    public function whenApiError(string $method, string $url, string $message, string $detail = '', int $httpCode = 400): self {
        return $this->when($method, $url, [
            'body'     => json_encode(['error' => $httpCode, 'message' => $message, 'detail' => $detail]),
            'httpCode' => $httpCode,
            'error'    => '',
            'errno'    => 0,
        ]);
    }
    
    /**
     * Configura erro de conexão (timeout, DNS, etc.).
     */
    public function whenConnectionError(string $method, string $url, string $error = 'Connection refused', int $errno = 7): self {
        return $this->when($method, $url, [
            'body'     => '',
            'httpCode' => 0,
            'error'    => $error,
            'errno'    => $errno,
        ]);
    }
    
    /**
     * Configura resposta padrão para qualquer request não mapeado.
     */
    public function whenDefault(array $response): self {
        return $this->when('*', '*', $response);
    }
    
    public function execute(string $method, string $url, array $options = []): array {
        $this->calls[] = [
            'method'  => $method,
            'url'     => $url,
            'options' => $options,
        ];
        
        // Procurar resposta configurada
        foreach ($this->responses as $config) {
            $methodMatch = $config['method'] === '*' || strtoupper($config['method']) === strtoupper($method);
            $urlMatch = $config['url'] === '*' || str_contains($url, $config['url']);
            
            if ($methodMatch && $urlMatch) {
                return $config['response'];
            }
        }
        
        // Resposta padrão: 200 vazio
        return [
            'body'     => json_encode([]),
            'httpCode' => 200,
            'error'    => '',
            'errno'    => 0,
        ];
    }
    
    /**
     * Retorna histórico de chamadas recebidas.
     */
    public function getCalls(): array {
        return $this->calls;
    }
    
    /**
     * Retorna a última chamada recebida.
     */
    public function getLastCall(): ?array {
        return end($this->calls) ?: null;
    }
    
    /**
     * Retorna quantas vezes foi chamado.
     */
    public function getCallCount(): int {
        return count($this->calls);
    }
    
    /**
     * Verifica se uma URL foi chamada.
     */
    public function wasCalled(string $method, string $url): bool {
        foreach ($this->calls as $call) {
            if (strtoupper($call['method']) === strtoupper($method) && str_contains($call['url'], $url)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Limpa histórico de chamadas.
     */
    public function reset(): void {
        $this->calls = [];
        $this->responses = [];
    }
}
