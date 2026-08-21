<?php
namespace App\Service;

use App\Exception\MikrotikApiException;

/**
 * Cliente para a API REST do Mikrotik RouterOS.
 * 
 * Fornece métodos genéricos para comunicação HTTP com autenticação Basic Auth,
 * tratamento de erros, logging e suporte a filtros da API.
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
    
    /** @var array Histórico de chamadas para debug */
    private array $requestLog = [];
    
    /**
     * @param string $baseUrl    URL base da API (ex: http://45.4.112.13)
     * @param string $username   Usuário para HTTP Basic Auth
     * @param string $password   Senha para HTTP Basic Auth
     * @param int    $timeout    Timeout em segundos (padrão: 10)
     * @param bool   $verifySsl  Verificar certificado SSL (false = permitir self-signed)
     * @param bool   $logEnabled Habilitar log de chamadas
     * @param string $logFile    Caminho do arquivo de log
     */
    public function __construct(
        string $baseUrl = '',
        string $username = '',
        string $password = '',
        int $timeout = 10,
        bool $verifySsl = false,
        bool $logEnabled = true,
        string $logFile = '/tmp/mikrotik_api.log'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
        $this->logEnabled = $logEnabled;
        $this->logFile = $logFile;
    }
    
    /**
     * Cria uma instância do client a partir das variáveis de ambiente (.env).
     */
    public static function fromEnv(): self {
        return new self(
            baseUrl:  getenv('MIKROTIK_API_URL') ?: 'http://45.4.112.13',
            username: getenv('MIKROTIK_USER') ?: '',
            password: getenv('MIKROTIK_PASS') ?: '',
            timeout: (int) (getenv('MIKROTIK_TIMEOUT') ?: 10),
            verifySsl: filter_var(getenv('MIKROTIK_VERIFY_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            logEnabled: filter_var(getenv('MIKROTIK_LOG_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            logFile: getenv('MIKROTIK_LOG_FILE') ?: '/tmp/mikrotik_api.log'
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
     * Exemplo: $client->post('/ip/address', [
     *     '.proplist' => 'address,interface',
     *     '.query' => ['interface' => 'ether1']
     * ])
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
    // Métodos de conveniência (atalhos para endpoints comuns)
    // =====================================================================
    
    /**
     * Verifica se a API está acessível e retorna dados básicos do sistema.
     * 
     * @return array Dados de /system/resource
     * @throws MikrotikApiException
     */
    public function systemResource(): array {
        return $this->get('/system/resource');
    }
    
    /**
     * Retorna a identidade (nome) do roteador.
     * 
     * @return array ['name' => '...']
     * @throws MikrotikApiException
     */
    public function systemIdentity(): array {
        return $this->get('/system/identity');
    }
    
    /**
     * Lista todas as interfaces.
     * 
     * @param array $query Filtros opcionais
     * @return array
     * @throws MikrotikApiException
     */
    public function interfaces(array $query = []): array {
        return $this->get('/interface', $query);
    }
    
    /**
     * Lista clientes Hotspot ativos.
     * 
     * @param array $query Filtros opcionais
     * @return array
     * @throws MikrotikApiException
     */
    public function hotspotActive(array $query = []): array {
        return $this->get('/ip/hotspot/active', $query);
    }
    
    /**
     * Retorna logs do sistema.
     * 
     * @param array $query Filtros opcionais (ex: ['topics' => 'system,error'])
     * @return array
     * @throws MikrotikApiException
     */
    public function logs(array $query = []): array {
        return $this->get('/log', $query);
    }
    
    // =====================================================================
    // Histórico de chamadas (para debug)
    // =====================================================================
    
    /**
     * Retorna o histórico de todas as chamadas feitas nesta instância.
     * 
     * @return array Lista de chamadas com endpoint, método, status, tempo
     */
    public function getRequestLog(): array {
        return $this->requestLog;
    }
    
    /**
     * Retorna a última chamada feita.
     */
    public function getLastRequest(): ?array {
        return end($this->requestLog) ?: null;
    }
    
    // =====================================================================
    // Métodos privados
    // =====================================================================
    
    /**
     * Constrói a URL completa com query string.
     */
    private function buildUrl(string $path, array $query = []): string {
        $url = $this->baseUrl . '/rest' . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }
    
    /**
     * Executa a requisição HTTP via cURL.
     * 
     * @param string $method Método HTTP
     * @param string $url    URL completa
     * @param array  $body   Dados do corpo (opcional)
     * @return array Resposta decodificada
     * @throws MikrotikApiException
     */
    private function request(string $method, string $url, array $body = []): array {
        $startTime = microtime(true);
        
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            // Não seguir redirects automaticamente
            CURLOPT_FOLLOWLOCATION => false,
        ];
        
        // SSL
        if (!$this->verifySsl) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        
        // Autenticação Basic Auth
        if ($this->username !== '' && $this->password !== '') {
            $options[CURLOPT_USERPWD] = "{$this->username}:{$this->password}";
        }
        
        // Corpo da requisição (POST, PUT, PATCH)
        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        
        curl_close($ch);
        
        // Erro de conexão cURL (timeout, DNS, etc.)
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
            
            // Resposta não-JSON (pode ser HTML de erro do servidor)
            throw new MikrotikApiException(
                message: "Resposta inválida da API (não é JSON)",
                detail: substr($response, 0, 500),
                endpoint: $url,
                method: $method,
                httpStatus: $httpCode
            );
        }
        
        // Log da chamada bem-sucedida
        $this->log($method, $url, $httpCode, $elapsed);
        
        // Erro HTTP (>= 400)
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
    
    /**
     * Registra chamada no log (arquivo e memória).
     */
    private function log(string $method, string $url, int $status, float $elapsed, string $extra = ''): void {
        $entry = [
            'method'    => $method,
            'url'       => $url,
            'status'    => $status,
            'elapsed_ms' => $elapsed,
            'timestamp' => date('Y-m-d H:i:s'),
            'extra'     => $extra,
        ];
        
        $this->requestLog[] = $entry;
        
        if ($this->logEnabled) {
            $statusLabel = $status >= 400 ? 'ERROR' : 'OK';
            $line = sprintf(
                "[%s] %s %s -> %d (%sms) [%s]%s%s\n",
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
