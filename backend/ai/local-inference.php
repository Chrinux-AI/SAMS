<?php
/**
 * Sentinel Local AI Inference Connector
 * 
 * Links directly to the GGUF model running via llama-server or LM Studio
 * without requiring any external cloud APIs.
 */

class SentinelLocalAI {
    // Port 8080 is standard for llama-server. If using LM Studio, change this to 1234.
    private $api_endpoint = 'http://localhost:8080/v1/chat/completions';
    
    public function generateResponse($messages, $temperature = 0.7) {
        $ch = curl_init();
        
        $payload = [
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => 1024,
            'stream' => false
        ];
        
        curl_setopt($ch, CURLOPT_URL, $this->api_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer local-ai'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Local inferece can take time
        
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($status === 200 && $result) {
            $data = json_decode($result, true);
            return [
                'success' => true,
                'content' => $data['choices'][0]['message']['content'] ?? ''
            ];
        }
        
        return [
            'success' => false,
            'error' => "Local AI Error: HTTP $status. Ensure 'start-local-ai.bat' is running or LM Studio local server is online. cURL Error: $error"
        ];
    }
}
?>
