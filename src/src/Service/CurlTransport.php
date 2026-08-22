<?php
namespace App\Service;

/**
 * Implementação padrão de HttpTransport usando cURL.
 * 
 * Usado em produção. Pode ser substituído por mocks nos testes.
 */
class CurlTransport implements HttpTransport {
    
    public function execute(string $method, string $url, array $options = []): array {
        $ch = curl_init();
        
        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 10,
            CURLOPT_CONNECTTIMEOUT => $options['connectTimeout'] ?? 5,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $options['headers'] ?? [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => false,
        ];
        
        // SSL
        if (!empty($options['verifySsl']) && $options['verifySsl'] === false) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        
        // Autenticação Basic Auth
        if (!empty($options['username']) && !empty($options['password'])) {
            $curlOptions[CURLOPT_USERPWD] = "{$options['username']}:{$options['password']}";
        }
        
        // Corpo da requisição (POST, PUT, PATCH)
        if (!empty($options['body']) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        return [
            'body'     => $response ?: '',
            'httpCode' => $httpCode,
            'error'    => $curlError,
            'errno'    => $curlErrno,
        ];
    }
}
