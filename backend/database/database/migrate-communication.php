<?php

/**
 * Communication System Migration
 * Creates tables for WhatsApp-style messaging
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$results = [];

$tables = [
  'comm_conversations' => "CREATE TABLE comm_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        type ENUM('direct','group') NOT NULL DEFAULT 'direct',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cc_type (type),
        INDEX idx_cc_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  'comm_participants' => "CREATE TABLE comm_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('member','admin') NOT NULL DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cp (conversation_id, user_id),
        INDEX idx_cp_user (user_id),
        FOREIGN KEY (conversation_id) REFERENCES comm_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  'comm_messages' => "CREATE TABLE comm_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        reply_to_id INT DEFAULT NULL,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cm_conv (conversation_id, created_at),
        INDEX idx_cm_sender (sender_id),
        FOREIGN KEY (conversation_id) REFERENCES comm_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  'comm_reads' => "CREATE TABLE comm_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cr (message_id, user_id),
        INDEX idx_cr_user (user_id),
        FOREIGN KEY (message_id) REFERENCES comm_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  'comm_attachments' => "CREATE TABLE comm_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) DEFAULT NULL,
        file_size INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES comm_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  'comm_typing' => "CREATE TABLE comm_typing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ct (conversation_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tables as $name => $sql) {
  try {
    $exists = db()->fetchOne("SHOW TABLES LIKE ?", [$name]);
    if (!$exists) {
      db()->query($sql);
      $results[] = "✅ Created $name";
    } else {
      $results[] = "⏭️ $name already exists";
    }
  } catch (Throwable $e) {
    $results[] = "❌ $name: " . $e->getMessage();
  }
}

// Drop old messaging tables
$old = [
  'typing_indicators',
  'message_reactions',
  'message_attachments',
  'student_messages',
  'conversation_messages',
  'conversation_participants',
  'conversations',
  'message_recipients',
  'messages',
  'user_online_status'
];
foreach ($old as $t) {
  try {
    $exists = db()->fetchOne("SHOW TABLES LIKE ?", [$t]);
    if ($exists) {
      db()->query("DROP TABLE `$t`");
      $results[] = "🗑️ Dropped old table $t";
    }
  } catch (Throwable $e) {
    $results[] = "⚠️ Could not drop $t: " . $e->getMessage();
  }
}

echo "<h2>Communication Migration</h2><pre>\n";
foreach ($results as $r) echo "$r\n";
echo "</pre><p><strong>Done.</strong></p>\n";
