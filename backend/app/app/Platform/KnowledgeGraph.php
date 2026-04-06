<?php

/**
 * KnowledgeGraph — Relationship Engine
 *
 * Maps entity relationships across the platform:
 *   Student ↔ Attendance, Teacher ↔ Class, Class ↔ Schedule,
 *   Behavior ↔ Performance, Admin actions ↔ Outcomes
 *
 * Enables reasoning over structured edges instead of raw querying.
 *
 * Tables: knowledge_nodes, knowledge_edges
 */
class KnowledgeGraph
{
  /**
   * Ensure knowledge graph tables exist.
   */
  public static function ensureTables(): void
  {
    try {
      $pdo = db()->getConnection();

      $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_type VARCHAR(50) NOT NULL,
        entity_id INT DEFAULT NULL,
        label VARCHAR(255) NOT NULL,
        properties JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_node_type (node_type),
        INDEX idx_entity (node_type, entity_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_edges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_node_id INT NOT NULL,
        target_node_id INT NOT NULL,
        relationship VARCHAR(100) NOT NULL,
        weight DECIMAL(5,2) DEFAULT 1.00,
        properties JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_source (source_node_id),
        INDEX idx_target (target_node_id),
        INDEX idx_rel (relationship),
        INDEX idx_edge (source_node_id, relationship)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'KnowledgeGraph table creation failed: ' . $e->getMessage(), 'HIGH');
    }
  }

  /**
   * Create or update a node.
   */
  public static function upsertNode(string $type, int $entityId, string $label, array $props = []): ?int
  {
    try {
      $existing = db()->fetchOne(
        "SELECT id FROM knowledge_nodes WHERE node_type = ? AND entity_id = ?",
        [$type, $entityId]
      );
      if ($existing) {
        db()->query(
          "UPDATE knowledge_nodes SET label = ?, properties = ?, updated_at = NOW() WHERE id = ?",
          [$label, json_encode($props), $existing['id']]
        );
        return (int) $existing['id'];
      }
      db()->query(
        "INSERT INTO knowledge_nodes (node_type, entity_id, label, properties) VALUES (?, ?, ?, ?)",
        [$type, $entityId, $label, json_encode($props)]
      );
      return (int) db()->getConnection()->lastInsertId();
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Node upsert failed: ' . $e->getMessage(), 'MEDIUM');
      return null;
    }
  }

  /**
   * Create an edge between two nodes.
   */
  public static function addEdge(int $sourceId, int $targetId, string $relationship, float $weight = 1.0, array $props = []): bool
  {
    try {
      // Check for existing edge
      $existing = db()->fetchOne(
        "SELECT id FROM knowledge_edges WHERE source_node_id = ? AND target_node_id = ? AND relationship = ?",
        [$sourceId, $targetId, $relationship]
      );
      if ($existing) {
        db()->query(
          "UPDATE knowledge_edges SET weight = ?, properties = ? WHERE id = ?",
          [$weight, json_encode($props), $existing['id']]
        );
        return true;
      }
      db()->query(
        "INSERT INTO knowledge_edges (source_node_id, target_node_id, relationship, weight, properties) VALUES (?, ?, ?, ?, ?)",
        [$sourceId, $targetId, $relationship, $weight, json_encode($props)]
      );
      return true;
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Edge creation failed: ' . $e->getMessage(), 'MEDIUM');
      return false;
    }
  }

  /**
   * Find nodes connected to a given node by relationship.
   */
  public static function traverse(int $nodeId, string $relationship = '', string $direction = 'outgoing'): array
  {
    try {
      if ($direction === 'outgoing') {
        $sql = "SELECT kn.*, ke.relationship, ke.weight
                FROM knowledge_edges ke
                JOIN knowledge_nodes kn ON kn.id = ke.target_node_id
                WHERE ke.source_node_id = ?";
        $params = [$nodeId];
      } else {
        $sql = "SELECT kn.*, ke.relationship, ke.weight
                FROM knowledge_edges ke
                JOIN knowledge_nodes kn ON kn.id = ke.source_node_id
                WHERE ke.target_node_id = ?";
        $params = [$nodeId];
      }
      if ($relationship) {
        $sql .= " AND ke.relationship = ?";
        $params[] = $relationship;
      }
      $sql .= " ORDER BY ke.weight DESC";
      return db()->fetchAll($sql, $params);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Build the knowledge graph from live data.
   * Populates student→attendance, teacher→class, class→schedule edges.
   */
  public static function buildFromData(): array
  {
    $built = ['nodes' => 0, 'edges' => 0];
    self::ensureTables();

    try {
      // Students
      $students = db()->fetchAll("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'student' LIMIT 500");
      foreach ($students as $s) {
        $nid = self::upsertNode('student', (int) $s['id'], $s['name']);
        if ($nid) $built['nodes']++;
      }

      // Teachers
      $teachers = db()->fetchAll("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'teacher' LIMIT 200");
      foreach ($teachers as $t) {
        $nid = self::upsertNode('teacher', (int) $t['id'], $t['name']);
        if ($nid) $built['nodes']++;
      }

      // Classes
      if (function_exists('table_exists') && table_exists('classes')) {
        $classes = db()->fetchAll("SELECT id, name FROM classes LIMIT 200");
        foreach ($classes as $c) {
          $nid = self::upsertNode('class', (int) $c['id'], $c['name']);
          if ($nid) $built['nodes']++;
        }
      }

      // Edges: student ↔ class (via enrollments)
      if (function_exists('table_exists') && table_exists('class_enrollments')) {
        $enrollments = db()->fetchAll("SELECT student_id, class_id FROM class_enrollments LIMIT 2000");
        foreach ($enrollments as $e) {
          $sNode = self::findNode('student', (int) $e['student_id']);
          $cNode = self::findNode('class', (int) $e['class_id']);
          if ($sNode && $cNode) {
            self::addEdge($sNode, $cNode, 'enrolled_in');
            $built['edges']++;
          }
        }
      }

      // Edges: teacher → class (via class_schedules or classes.teacher_id)
      if (function_exists('table_exists') && table_exists('class_schedules')) {
        $schedules = db()->fetchAll("SELECT DISTINCT teacher_id, class_id FROM class_schedules WHERE teacher_id IS NOT NULL LIMIT 500");
        foreach ($schedules as $sc) {
          $tNode = self::findNode('teacher', (int) $sc['teacher_id']);
          $cNode = self::findNode('class', (int) $sc['class_id']);
          if ($tNode && $cNode) {
            self::addEdge($tNode, $cNode, 'teaches');
            $built['edges']++;
          }
        }
      }

      // Edges: student → attendance patterns
      $lowAttendance = db()->fetchAll(
        "SELECT student_id, class_id, COUNT(*) AS absences
         FROM attendance
         WHERE status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY student_id, class_id
         HAVING absences >= 3
         LIMIT 500"
      );
      foreach ($lowAttendance as $la) {
        $sNode = self::findNode('student', (int) $la['student_id']);
        $cNode = self::findNode('class', (int) $la['class_id']);
        if ($sNode && $cNode) {
          self::addEdge($sNode, $cNode, 'low_attendance', (float) $la['absences'], [
            'absences_30d' => (int) $la['absences']
          ]);
          $built['edges']++;
        }
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Knowledge graph build error: ' . $e->getMessage(), 'HIGH');
    }

    ErrorCollector::log('platform', "Knowledge graph built: {$built['nodes']} nodes, {$built['edges']} edges", 'INFO');
    return $built;
  }

  /**
   * Find a node ID by type and entity ID.
   */
  public static function findNode(string $type, int $entityId): ?int
  {
    try {
      $row = db()->fetchOne(
        "SELECT id FROM knowledge_nodes WHERE node_type = ? AND entity_id = ?",
        [$type, $entityId]
      );
      return $row ? (int) $row['id'] : null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Query: find students with low attendance in a class.
   */
  public static function queryLowAttendance(int $classEntityId): array
  {
    try {
      $classNode = self::findNode('class', $classEntityId);
      if (!$classNode) return [];
      return db()->fetchAll(
        "SELECT kn.entity_id AS student_id, kn.label AS student_name, ke.weight AS absences, ke.properties
         FROM knowledge_edges ke
         JOIN knowledge_nodes kn ON kn.id = ke.source_node_id
         WHERE ke.target_node_id = ? AND ke.relationship = 'low_attendance'
         ORDER BY ke.weight DESC",
        [$classNode]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get graph stats for dashboard.
   */
  public static function getStats(): array
  {
    try {
      $nodes = db()->fetchOne("SELECT COUNT(*) AS cnt FROM knowledge_nodes");
      $edges = db()->fetchOne("SELECT COUNT(*) AS cnt FROM knowledge_edges");
      $types = db()->fetchAll("SELECT node_type, COUNT(*) AS cnt FROM knowledge_nodes GROUP BY node_type ORDER BY cnt DESC");
      $rels = db()->fetchAll("SELECT relationship, COUNT(*) AS cnt FROM knowledge_edges GROUP BY relationship ORDER BY cnt DESC");
      return [
        'total_nodes' => (int) ($nodes['cnt'] ?? 0),
        'total_edges' => (int) ($edges['cnt'] ?? 0),
        'node_types'  => $types,
        'relationships' => $rels,
      ];
    } catch (\Throwable $e) {
      return ['total_nodes' => 0, 'total_edges' => 0, 'node_types' => [], 'relationships' => []];
    }
  }

  /**
   * Prune old edges older than N days.
   */
  public static function pruneOldEdges(int $days = 60): int
  {
    try {
      $stmt = db()->query(
        "DELETE FROM knowledge_edges WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$days]
      );
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }
}
