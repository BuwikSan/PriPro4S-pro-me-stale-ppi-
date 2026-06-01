CREATE TABLE IF NOT EXISTS history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cipher_type VARCHAR(20)  NOT NULL,
    typ_operace VARCHAR(10)  NOT NULL,
    cipher_key  TEXT,
    input       TEXT,
    output      TEXT,
    parent_id   INT          NULL,                   -- dec záznam odkazuje na svůj enc rodič
    timestamp   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cipher_type (cipher_type),
    INDEX idx_parent_id   (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
