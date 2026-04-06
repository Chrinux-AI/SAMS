<?php

/**
 * AI Intent Parser
 * Converts natural language / structured payloads into registered intents.
 */
class AICopilotIntentParser
{
  /**
   * @param string $message
   * @param array<string,mixed> $explicitPayload
   * @return array<string,mixed>
   */
  public function parse(string $message, array $explicitPayload = []): array
  {
    // If caller already passes structured intent, trust only registry-known intents.
    if (!empty($explicitPayload['intent']) && is_string($explicitPayload['intent'])) {
      $intent = strtoupper(trim($explicitPayload['intent']));
      if (!AICopilotActionRegistry::exists($intent)) {
        return $this->reject('Unknown or unregistered intent.');
      }

      return [
        'ok' => true,
        'intent' => $intent,
        'parameters' => is_array($explicitPayload['parameters'] ?? null) ? $explicitPayload['parameters'] : []
      ];
    }

    $m = strtolower(trim($message));
    if ($m === '') {
      return $this->reject('No actionable instruction provided.');
    }

    // Heuristic parse with strict allow-list.
    if (preg_match('/\b(create|add)\b.*\bclass\b/', $m)) {
      return [
        'ok' => true,
        'intent' => 'CREATE_CLASS',
        'parameters' => $this->extractClassParams($message)
      ];
    }

    if (preg_match('/\b(update|edit|change|rename)\b.*\bclass\b/', $m)) {
      return [
        'ok' => true,
        'intent' => 'UPDATE_CLASS',
        'parameters' => $this->extractClassParams($message)
      ];
    }

    if (preg_match('/\b(delete|remove)\b.*\bclass\b/', $m)) {
      return [
        'ok' => true,
        'intent' => 'DELETE_CLASS',
        'parameters' => $this->extractClassParams($message)
      ];
    }

    if (preg_match('/\b(mark|take|record)\b.*\battendance\b/', $m)) {
      return [
        'ok' => true,
        'intent' => 'MARK_ATTENDANCE',
        'parameters' => $this->extractAttendanceParams($message)
      ];
    }

    if (preg_match('/\b(send|post|publish)\b.*\b(notice|announcement|alert)\b/', $m)) {
      return [
        'ok' => true,
        'intent' => 'SEND_NOTICE',
        'parameters' => $this->extractNoticeParams($message)
      ];
    }

    return $this->reject('Intent is not recognized or not allowed by policy.');
  }

  private function reject(string $message): array
  {
    return [
      'ok' => false,
      'intent' => 'REJECTED',
      'parameters' => [],
      'error' => $message
    ];
  }

  /** @return array<string,mixed> */
  private function extractClassParams(string $message): array
  {
    $params = [];

    if (preg_match('/class\s+([A-Za-z0-9 _-]{2,80})/i', $message, $m)) {
      $params['name'] = trim($m[1]);
    }
    if (preg_match('/grade\s*([0-9]{1,2}|[A-Za-z]{1,6})/i', $message, $m)) {
      $params['grade_level'] = trim((string)$m[1]);
    }
    if (preg_match('/teacher\s*#?\s*([0-9]+)/i', $message, $m)) {
      $params['teacher_id'] = (int)$m[1];
    }
    if (preg_match('/class\s*id\s*#?\s*([0-9]+)/i', $message, $m)) {
      $params['class_id'] = (int)$m[1];
    }

    return $params;
  }

  /** @return array<string,mixed> */
  private function extractAttendanceParams(string $message): array
  {
    $params = [];
    if (preg_match('/student\s*#?\s*([0-9]+)/i', $message, $m)) {
      $params['student_id'] = (int)$m[1];
    }
    if (preg_match('/class\s*#?\s*([0-9]+)/i', $message, $m)) {
      $params['class_id'] = (int)$m[1];
    }
    if (preg_match('/\b(present|absent|late|excused)\b/i', $message, $m)) {
      $params['status'] = strtolower($m[1]);
    }
    return $params;
  }

  /** @return array<string,mixed> */
  private function extractNoticeParams(string $message): array
  {
    return [
      'title' => 'AI Copilot Notice',
      'message' => trim($message),
      'audience' => 'all'
    ];
  }
}
