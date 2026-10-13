-- Apply once or rerun safely on an installed PK DTS database.
-- One durable receipt per user and approval-step activation.
CREATE TABLE IF NOT EXISTS task_notification_receipts (
    user_id BIGINT UNSIGNED NOT NULL,
    step_id BIGINT UNSIGNED NOT NULL,
    activation INT NOT NULL,
    shown_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, step_id, activation),
    KEY idx_task_receipt_step (step_id),
    CONSTRAINT fk_task_receipt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_task_receipt_step FOREIGN KEY (step_id) REFERENCES workflow_steps (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
