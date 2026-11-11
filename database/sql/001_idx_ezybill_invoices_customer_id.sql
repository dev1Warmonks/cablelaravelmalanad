-- Approved by the project owner (2026-11-11). Non-breaking: only adds an index.
-- Apply to the LOCAL COPY first; for production run it in the go-live runbook (idempotent via the check below).
-- Speeds up "latest invoice/package per customer" lookups on m_ezybill_invoices (600k rows): ~26 s -> milliseconds.
SET @exists := (SELECT COUNT(1) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'm_ezybill_invoices' AND index_name = 'idx_customer_id');
SET @sql := IF(@exists = 0, 'ALTER TABLE m_ezybill_invoices ADD INDEX idx_customer_id (customer_id)', 'SELECT "idx_customer_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
