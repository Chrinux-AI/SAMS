<?php

/**
 * ClassController — HTTP handler for class CRUD operations.
 *
 * Processes POST actions from admin/classes.php through the service layer.
 * Returns structured result array with success/message/issues.
 *
 * Usage (from admin/classes.php):
 *   $result = ClassController::handle($_POST);
 *   $message      = $result['message'];
 *   $message_type = $result['success'] ? 'success' : 'error';
 */
class ClassController
{
  /**
   * Route POST request to the appropriate action.
   *
   * @return array{success: bool, message: string, issues: array}
   */
  public static function handle(array $post): array
  {
    if (isset($post['add_class'])) {
      return ClassService::create($post);
    }

    if (isset($post['edit_class'])) {
      $id = (int)($post['class_id'] ?? 0);
      if ($id <= 0) {
        return ['success' => false, 'message' => 'Invalid class ID.', 'issues' => []];
      }
      return ClassService::update($id, $post);
    }

    if (isset($post['delete_class'])) {
      $id = (int)($post['class_id'] ?? 0);
      if ($id <= 0) {
        return ['success' => false, 'message' => 'Invalid class ID.', 'issues' => []];
      }
      return ClassService::delete($id);
    }

    return ['success' => false, 'message' => 'Unknown action.', 'issues' => []];
  }

  /**
   * Fetch a single class as JSON (for AJAX edit modal).
   */
  public static function getJson(int $id): void
  {
    header('Content-Type: application/json');
    $class = ClassRepository::find($id);
    if (!$class) {
      http_response_code(404);
      echo json_encode(['error' => 'Class not found']);
      return;
    }
    echo json_encode($class);
  }
}
