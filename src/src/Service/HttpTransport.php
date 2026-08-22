<?php
namespace App\Service;

/**
 * Interface para camada de transporte HTTP.
 * 
 * Permite injetar transports customizados (ex: mock nos testes)
 * no MikrotikClient sem depender de cURL diretamente.
 */
interface HttpTransport {
    /**
     * Executa uma requisição HTTP.
     *
     * @param string $method  Método HTTP (GET, POST, PUT, PATCH, DELETE)
     * @param string $url     URL completa
     * @param array  $options Opções da requisição (headers, body, auth, ssl, timeout)
     * @return array ['body' => string, 'httpCode' => int, 'error' => string, 'errno' => int]
     */
    public function execute(string $method, string $url, array $options = []): array;
}
