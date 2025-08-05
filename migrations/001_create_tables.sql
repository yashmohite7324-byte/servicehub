-- MySQL migration for PHP Service project
-- Drop tables if they exist (for development/testing)
DROP TABLE IF EXISTS dl_pdfs;
DROP TABLE IF EXISTS llr_tokens;
DROP TABLE IF EXISTS payment_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS service_requests;
DROP TABLE IF EXISTS user_service_prices;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 0.0,
    is_blocked BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    default_price DECIMAL(10,2) DEFAULT 0.0,
    fields JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- User Service Prices table
CREATE TABLE user_service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Service Requests table
CREATE TABLE service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    service_price DECIMAL(10,2) NOT NULL,
    field_data JSON,
    status ENUM('pending','success','failed') DEFAULT 'pending',
    admin_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Payment History table
CREATE TABLE payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('credit','debit','refund','pending_credit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    reference_id VARCHAR(64),
    balance_after DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','success','failed') DEFAULT 'pending',
    qr_code_url TEXT,
    upi_id VARCHAR(100),
    payment_link TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- LLR Tokens table
CREATE TABLE llr_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    service_price DECIMAL(10,2) NOT NULL,
    token VARCHAR(128) NOT NULL,
    applno VARCHAR(64),
    applname VARCHAR(100),
    dob VARCHAR(20),
    queue VARCHAR(50),
    rtocode VARCHAR(20),
    rtoname VARCHAR(100),
    statecode VARCHAR(20),
    statename VARCHAR(100),
    status ENUM('submitted','processing','completed','refunded') DEFAULT 'submitted',
    api_response JSON,
    pdf_data MEDIUMBLOB,
    filename VARCHAR(255),
    remarks TEXT,
    last_checked DATETIME,
    completed_at DATETIME,
    refund_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- DL PDFs table
CREATE TABLE dl_pdfs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    service_price DECIMAL(10,2) NOT NULL,
    dlno VARCHAR(64) NOT NULL,
    pdf_type VARCHAR(20),
    blood_group VARCHAR(10),
    address_type VARCHAR(20),
    status ENUM('completed','failed') DEFAULT 'completed',
    name VARCHAR(100),
    dob VARCHAR(20),
    pdf_data MEDIUMBLOB,
    api_response JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_dl_pdfs_user_id ON dl_pdfs(user_id);
CREATE INDEX idx_dl_pdfs_dlno ON dl_pdfs(dlno);
CREATE INDEX idx_dl_pdfs_created_at ON dl_pdfs(created_at DESC);

-- STORED PROCEDURES AND TRIGGERS FOR BUSINESS LOGIC

-- Stored Procedure: Add Payment History
DELIMITER $$
CREATE PROCEDURE add_payment_history(
    IN p_user_id INT,
    IN p_transaction_type ENUM('credit','debit','refund','pending_credit'),
    IN p_amount DECIMAL(10,2),
    IN p_description TEXT,
    IN p_reference_id VARCHAR(64)
)
BEGIN
    DECLARE current_balance DECIMAL(10,2);
    SELECT wallet_balance INTO current_balance FROM users WHERE id = p_user_id;
    INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after)
    VALUES (p_user_id, p_transaction_type, p_amount, p_description, p_reference_id, current_balance);
END $$
DELIMITER ;

-- Stored Procedure: Update Wallet
DELIMITER $$
CREATE PROCEDURE update_wallet(
    IN p_user_id INT,
    IN p_amount DECIMAL(10,2),
    IN p_type ENUM('credit','debit','refund')
)
BEGIN
    IF p_type = 'credit' THEN
        UPDATE users SET wallet_balance = wallet_balance + p_amount WHERE id = p_user_id;
    ELSE
        UPDATE users SET wallet_balance = wallet_balance - p_amount WHERE id = p_user_id;
    END IF;
END $$
DELIMITER ;

-- Trigger: On Payment Success, Credit Wallet and Add Payment History
DELIMITER $$
CREATE TRIGGER trg_payment_success AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'success' AND OLD.status != 'success' THEN
        CALL update_wallet(NEW.user_id, NEW.amount, 'credit');
        CALL add_payment_history(NEW.user_id, 'credit', NEW.amount, 'Wallet top-up via payment gateway', NEW.transaction_id);
    END IF;
END $$
DELIMITER ;

-- Trigger: On Service Request Failed, Refund
DELIMITER $$
CREATE TRIGGER trg_service_request_failed AFTER UPDATE ON service_requests
FOR EACH ROW
BEGIN
    IF NEW.status = 'failed' AND OLD.status != 'failed' THEN
        CALL update_wallet(NEW.user_id, NEW.service_price, 'credit');
        CALL add_payment_history(NEW.user_id, 'refund', NEW.service_price, CONCAT('Refund for failed ', NEW.service_id), NEW.id);
    END IF;
END $$
DELIMITER ;

-- Sample admin and user
INSERT INTO admins (username, password) VALUES ('admin', 'admin123');
INSERT INTO users (name, mobile, password, wallet_balance) VALUES ('Test User', '9999999999', 'user123', 1000.00);

-- Sample service
INSERT INTO services (name, description, default_price, fields) VALUES ('Sample Service', 'A test service', 100.00, JSON_ARRAY('field1', 'field2'));

-- Sample user_service_price
INSERT INTO user_service_prices (user_id, service_id, price) VALUES (1, 1, 100.00); 