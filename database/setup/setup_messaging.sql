-- Create messages table for communication between roles
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receiver (receiver_id),
    INDEX idx_sender (sender_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create AI analytics table for database-driven stats
CREATE TABLE IF NOT EXISTS ai_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL UNIQUE,
    accuracy_rate DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'training') DEFAULT 'inactive',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default AI models
INSERT INTO ai_analytics (model_name, accuracy_rate, status) VALUES
('Attendance Predictor', 0.00, 'inactive'),
('Behavior Analyzer', 0.00, 'inactive'),
('Grade Predictor', 0.00, 'inactive'),
('Dropout Prevention', 0.00, 'inactive')
ON DUPLICATE KEY UPDATE model_name=model_name;

