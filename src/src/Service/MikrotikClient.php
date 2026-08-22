<?php
namespace App\Service;

use App\Exception\MikrotikApiException;

/**
 * Cliente para a API REST do Mikrotik RouterOS.
 * 
 * Fornece métodos genéricos para comunicação HTTP com autenticação Basic Auth,
 * tratamento de erros, logging e suporte a filtros da API.
 * 
 * Aceita HttpTransport via DI para facilitar testes (mock).
 * 
 * Documentação de referência:
 * https://help.mikrotik.com/docs/display/ROS/REST+API
 */
class MikrotikClient {
    
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;
    private bool $verifySsl;
    private bool $logEnabled;
    private string $logFile;
    private HttpTransport $transport;
    
    /** @var array Histórico de chamadas para debug */
    private array $requestLog = [];
    
    /**
     * @param string        $baseUrl    URL base da API (ex: http://45.4.112.13)
     * @param string        $username   Usuário para HTTP Basic Auth
     * @param string        $password   Senha para HTTP Basic Auth
     * @param int           $timeout    Timeout em segundos (padrão: 10)
     * @param bool          $verifySsl  Verificar certificado SSL (false = permitir self-signed)
     * @param bool          $logEnabled Hababilitar log de chamadas
     * @param string        $logFile    Caminho do arquivo de log
     * @param HttpTransport $transport  Transport HTTP (default: CurlTransport)
     */
    public function __construct(
        string $baseUrl = '',
        string $username = '',
        string $password = '',
        int $timeout = 10,
        bool $verifySsl = false,
        bool $logEnabled = true,
        string $logFile = '/tmp/mikrotik_api.log',
        ?HttpTransport $transport = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
        $this->logEnabled = $logEnabled;
        $this->logFile = $logFile;
        $this->transport = $transport ?? new CurlTransport();
    }
    
    /**
     * Cria uma instância do client a partir das variáveis de ambiente (.env).
     */
    public static function fromEnv(?HttpTransport $transport = null): self {
        return new self(
            baseUrl:  getenv('MIKROTIK_API_URL') ?: '',
            username: getenv('MIKROTIK_USER') ?: '',
            password: getenv('MIKROTIK_PASS') ?: '',
            timeout: (int) (getenv('MIKROTIK_TIMEOUT') ?: 10),
            verifySsl: filter_var(getenv('MIKROTIK_VERIFY_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            logEnabled: filter_var(getenv('MIKROTIK_LOG_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            logFile: getenv('MIKROTIK_LOG_FILE') ?: '/tmp/mikrotik_api.log',
            transport: $transport
        );
    }
    
    // =====================================================================
    // Métodos HTTP públicos
    // =====================================================================
    
    /**
     * GET — Consulta recursos.
     * 
     * Suporta filtros via query string (ex: ?name=ether1&disabled=false)
     * 
     * @param string $path  Caminho do recurso (ex: /interface, /ip/address)
     * @param array  $query Parâmetros de filtro
     * @return array Resposta decodificada do JSON
     * @throws MikrotikApiException
     */
    public function get(string $path, array $query = []): array {
        $url = $this->buildUrl($path, $query);
        return $this->request('GET', $url);
    }
    
    /**
     * POST — Cria recursos ou executa comandos.
     * 
     * Suporta .proplist e .query no body (formato JSON).
     * 
     * @param string $path Caminho do recurso
     * @param array  $body Dados do corpo (JSON)
     * @return array Resposta decodificada
     * @throws MikrotikApiException
     */
    public function post(string $path, array $body = []): array {
        $url = $this->buildUrl($path);
        return $this->request('POST', $url, $body);
    }
    
    /**
     * PUT — Atualiza um recurso existente (substitui campos).
     * 
     * @param string $path Caminho do recurso (ex: /interface/0)
     * @param array  $body Dados para atualização
     * @return array Resposta decodificada
     * @throws MikrotikApiException
     */
    public function put(string $path, array $body = []): array {
        $url = $this->buildUrl($path);
        return $this->request('PUT', $url, $body);
    }
    
    /**
     * PATCH — Atualiza parcialmente um recurso.
     * 
     * @param string $path Caminho do recurso
     * @param array  $body Campos para atualizar
     * @return array Resposta decodificada
     * @throws MikrotikApiException
     */
    public function patch(string $path, array $body = []): array {
        $url = $this->buildUrl($path);
        return $this->request('PATCH', $url, $body);
    }
    
    /**
     * DELETE — Remove um recurso.
     * 
     * @param string $path Caminho do recurso (ex: /ip/address/0)
     * @return array Resposta decodificada (geralmente vazia)
     * @throws MikrotikApiException
     */
    public function delete(string $path): array {
        $url = $this->buildUrl($path);
        return $this->request('DELETE', $url);
    }
    
    // =====================================================================
    // Métodos de conveniência
    // =====================================================================
    
    public function systemResource(): array {
        return $this->get('/system/resource');
    }
    
    public function systemIdentity(): array {
        return $this->get('/system/identity');
    }
    
    public function interfaces(array $query = []): array {
        return $this->get('/interface', $query);
    }
    
    public function hotspotActive(array $query = []): array {
        return $this->get('/ip/hotspot/active', $query);
    }
    
    public function logs(array $query = []): array {
        return $this->get('/log', $query);
    }
    
    // =====================================================================
    // Histórico de chamadas (para debug)
    // =====================================================================
    
    public function getRequestLog(): array {
        return $this->requestLog;
    }
    
    public function getLastRequest(): ?array {
        return end($this->requestLog) ?: null;
    }
    
    // =====================================================================
    // Métodos privados
    // =====================================================================
    
    private function buildUrl(string $path, array $query = []): string {
        $url = $this->baseUrl . '/rest' . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }
    
    /**
     * Executa a requisição HTTP via HttpTransport.
     */
    private function request(string $method, string $url, array $body = []): array {
        $startTime = microtime(true);
        
        $options = [
            'timeout'         => $this->timeout,
            'connectTimeout'  => 5,
            'verifySsl'       => $this->verifySsl,
            'username'        => $this->username,
            'password'        => $this->password,
            'headers'         => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];
        
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['body'] = json_encode($body);
        }
        
        $result = $this->transport->execute($method, $url, $options);
        
        $response = $result['body'];
        $httpCode = $result['httpCode'];
        $curlError = $result['error'];
        $curlErrno = $result['errno'];
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        
        // Erro de conexão (timeout, DNS, etc.)
        if ($curlError) {
            $this->log($method, $url, 0, $elapsed, "cURL Error: {$curlError}");
            
            throw new MikrotikApiException(
                message: "Erro de conexão com a API Mikrotik",
                apiErrorCode: $curlErrno,
                detail: $curlError,
                endpoint: $url,
                method: $method,
                httpStatus: 0
            );
        }
        
        // Decodificar resposta JSON
        $decoded = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE && $response !== '') {
            $this->log($method, $url, $httpCode, $elapsed, "JSON decode error: " . json_last_error_msg());
            
            throw new MikrotikApiException(
                message: "Resposta inválida da API (não é JSON)",
                detail: substr($response, 0, 500),
                endpoint: $url,
                method: $method,
                httpStatus: $httpCode
            );
        }
        
        $this->log($method, $url, $httpCode, $elapsed);
        
        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? 'Erro desconhecido';
            $errorDetail = $decoded['detail'] ?? '';
            $errorCode = $decoded['error'] ?? $httpCode;
            
            throw new MikrotikApiException(
                message: $errorMsg,
                apiErrorCode: is_int($errorCode) ? $errorCode : 0,
                detail: $errorDetail,
                endpoint: $url,
                method: $method,
                httpStatus: $httpCode
            );
        }
        
        return $decoded ?? [];
    }
    
    private function log(string $method, string $url, int $status, float $elapsed, string $extra = ''): void {
        $entry = [
            'method'     => $method,
            'url'        => $url,
            'status'     => $status,
            'elapsed_ms' => $elapsed,
            'timestamp'  => date('Y-m-d H:i:s'),
            'extra'      => $extra,
        ];
        
        $this->requestLog[] = $entry;
        
        if ($this->logEnabled) {
            $statusLabel = $status >= 400 ? 'ERROR' : 'OK';
            $line = sprintf(
                "[%s] %s %s -> %d (%sms) [%s]%s%s",
                $entry['timestamp'],
                $method,
                $url,
                $status,
                $elapsed,
                $statusLabel,
                $extra !== '' ? " - {$extra}" : '',
                PHP_EOL
            );
            
            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }
}
