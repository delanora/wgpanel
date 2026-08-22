<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class MikrotikController {
    
    public array $_routeParams = [];
    private MikrotikClient $client;
    
    public function __construct(\App\Service\MikrotikClient $client = null) {
        $this->client = $client ?? MikrotikClient::fromEnv();
    }
    
    public function index(): void {
        $connected = false;
        $identity = null;
        $resource = null;
        $error = null;
        
        try {
            $identity = $this->client->systemIdentity();
            $resource = $this->client->systemResource();
            $connected = true;
        } catch (MikrotikApiException $e) {
            $error = $e->getUserMessage();
        } catch (\Exception $e) {
            $error = 'Erro inesperado: ' . $e->getMessage();
        }
        
        require __DIR__ . '/../../views/mikrotik/index.php';
    }
    
    public function interfaces(): void {
        $interfaces = [];
        $error = null;
        
        try {
            $interfaces = $this->client->interfaces();
        } catch (MikrotikApiException $e) {
            $error = $e->getUserMessage();
        } catch (\Exception $e) {
            $error = 'Erro inesperado: ' . $e->getMessage();
        }
        
        require __DIR__ . '/../../views/mikrotik/interfaces.php';
    }
    
    public function clients(): void {
        $clients = [];
        $error = null;
        
        try {
            $clients = $this->client->hotspotActive();
        } catch (MikrotikApiException $e) {
            $error = $e->getUserMessage();
        } catch (\Exception $e) {
            $error = 'Erro inesperado: ' . $e->getMessage();
        }
        
        require __DIR__ . '/../../views/mikrotik/clients.php';
    }
    
    public function logs(): void {
        $logs = [];
        $error = null;
        
        try {
            $logs = $this->client->logs();
        } catch (MikrotikApiException $e) {
            $error = $e->getUserMessage();
        } catch (\Exception $e) {
            $error = 'Erro inesperado: ' . $e->getMessage();
        }
        
        require __DIR__ . '/../../views/mikrotik/logs.php';
    }
    
    public function runCommand(): void {
        header('Content-Type: application/json');
        
        $command = trim($_POST['command'] ?? '');
        
        if (!$command) {
            echo json_encode(['success' => false, 'error' => 'Comando vazio']);
            return;
        }
        
        // Comandos permitidos (whitelist por segurança)
        $allowedCommands = [
            'system resource print',
            'system identity print',
            'interface print',
            'interface wireless print',
            'ip address print',
            'ip route print',
            'ip pool print',
            'ip dhcp-server lease print',
            'ip hotspot active print',
            'log print',
        ];
        
        $commandLower = strtolower($command);
        $allowed = false;
        
        foreach ($allowedCommands as $ac) {
            if (str_starts_with($commandLower, strtolower($ac))) {
                $allowed = true;
                break;
            }
        }
        
        if (!$allowed) {
            echo json_encode([
                'success' => false,
                'error' => 'Comando não permitido',
                'allowed' => $allowedCommands,
            ]);
            return;
        }
        
        try {
            // Converte o comando RouterOS em endpoint REST
            // Ex: "system resource print" -> GET /rest/system/resource
            $endpoint = $this->commandToEndpoint($command);
            $result = $this->client->get($endpoint);
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'command' => $command,
                'endpoint' => $endpoint,
            ]);
        } catch (MikrotikApiException $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getUserMessage(),
                'detail' => $e->getDetail(),
                'http_status' => $e->getHttpStatus(),
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Erro inesperado: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Rota de teste para verificar a conexão com a API.
     * Acessa GET /rest/system/resource e retorna a resposta.
     */
    public function testApi(): void {
        header('Content-Type: application/json');
        
        $result = [
            'success' => false,
            'config' => [
                'url' => MIKROTIK_API_URL,
                'port' => MIKROTIK_API_PORT,
                'user' => getenv('MIKROTIK_USER') ?: '(não definido)',
                'timeout' => (int) (getenv('MIKROTIK_TIMEOUT') ?: 10),
                'verify_ssl' => filter_var(getenv('MIKROTIK_VERIFY_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            ],
            'connection' => null,
            'identity' => null,
            'resource' => null,
            'request_log' => [],
            'error' => null,
        ];
        
        try {
            // Teste 1: Identidade do roteador
            $result['identity'] = $this->client->systemIdentity();
            $result['connection'] = 'OK';
            
            // Teste 2: Recursos do sistema
            $result['resource'] = $this->client->systemResource();
            
            $result['success'] = true;
            
        } catch (MikrotikApiException $e) {
            $result['error'] = $e->getUserMessage();
            $result['connection'] = 'ERRO';
        } catch (\Exception $e) {
            $result['error'] = 'Erro inesperado: ' . $e->getMessage();
            $result['connection'] = 'ERRO';
        }
        
        // Incluir log de chamadas feitas durante o teste
        $result['request_log'] = $this->client->getRequestLog();
        
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Converte um comando RouterOS em endpoint REST.
     * 
     * Exemplos:
     *   "system resource print" -> "/system/resource"
     *   "ip address print"      -> "/ip/address"
     *   "interface print"       -> "/interface"
     *   "log print"             -> "/log"
     */
    private function commandToEndpoint(string $command): string {
        // Remove "print" do final
        $command = trim(preg_replace('/\s+print\s*$/i', '', $command));
        
        // Converte espaços em barras
        $endpoint = '/' . str_replace(' ', '/', $command);
        
        return $endpoint;
    }
}
