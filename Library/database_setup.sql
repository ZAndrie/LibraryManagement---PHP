-- Create Database
CREATE DATABASE library_system;
USE library_system;

-- Users Table (Multiple Admins & Clients)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'client') NOT NULL,
    reset_token VARCHAR(64),
    reset_expiry DATETIME,
    two_factor_secret VARCHAR(32),
    two_factor_enabled TINYINT(1) DEFAULT 0,
    last_login DATETIME,
    last_activity DATETIME,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_username (username)
);

-- Books Table
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    category VARCHAR(50),
    quantity INT DEFAULT 1,
    available INT DEFAULT 1,
    published_year INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category)
);

-- Patrons Table
CREATE TABLE patrons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- Borrowing Records Table
CREATE TABLE borrowings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT,
    patron_id INT,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (patron_id) REFERENCES patrons(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_book_id (book_id),
    INDEX idx_patron_id (patron_id)
);

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Login History Table
CREATE TABLE login_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- User Sessions Table
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id)
);

-- User Profiles Table (Extended Information)
CREATE TABLE user_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    profile_picture VARCHAR(255),
    bio TEXT,
    membership_status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_membership_status (membership_status)
);

-- User Borrowing History (Keep track of all borrowings per user)
CREATE TABLE user_borrowing_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_borrowed INT DEFAULT 0,
    currently_borrowed INT DEFAULT 0,
    total_returned INT DEFAULT 0,
    overdue_count INT DEFAULT 0,
    last_borrow_date DATETIME,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_stats (user_id)
);
-- Trigger to update stats when a book is borrowed
DELIMITER $$
CREATE TRIGGER update_borrow_stats_on_borrow
AFTER INSERT ON borrowings
FOR EACH ROW
BEGIN
    DECLARE user_email VARCHAR(100);
    DECLARE user_id_val INT;
    
    -- Get user email from patron
    SELECT email INTO user_email FROM patrons WHERE id = NEW.patron_id;
    
    -- Get user_id from users table
    SELECT id INTO user_id_val FROM users WHERE email = user_email;
    
    IF user_id_val IS NOT NULL THEN
        -- Insert or update stats
        INSERT INTO user_borrowing_stats (user_id, total_borrowed, currently_borrowed, last_borrow_date)
        VALUES (user_id_val, 1, 1, NOW())
        ON DUPLICATE KEY UPDATE
            total_borrowed = total_borrowed + 1,
            currently_borrowed = currently_borrowed + 1,
            last_borrow_date = NOW();
    END IF;
END$$

-- Trigger to update stats when a book is returned
CREATE TRIGGER update_borrow_stats_on_return
AFTER UPDATE ON borrowings
FOR EACH ROW
BEGIN
    DECLARE user_email VARCHAR(100);
    DECLARE user_id_val INT;
    
    IF OLD.status = 'borrowed' AND NEW.status = 'returned' THEN
        -- Get user email from patron
        SELECT email INTO user_email FROM patrons WHERE id = NEW.patron_id;
        
        -- Get user_id from users table
        SELECT id INTO user_id_val FROM users WHERE email = user_email;
        
        IF user_id_val IS NOT NULL THEN
            UPDATE user_borrowing_stats
            SET currently_borrowed = GREATEST(0, currently_borrowed - 1),
                total_returned = total_returned + 1
            WHERE user_id = user_id_val;
        END IF;
    END IF;
END$$

-- Trigger to update overdue count
CREATE TRIGGER update_overdue_stats
AFTER UPDATE ON borrowings
FOR EACH ROW
BEGIN
    DECLARE user_email VARCHAR(100);
    DECLARE user_id_val INT;
    
    IF OLD.status != 'overdue' AND NEW.status = 'overdue' THEN
        SELECT email INTO user_email FROM patrons WHERE id = NEW.patron_id;
        SELECT id INTO user_id_val FROM users WHERE email = user_email;
        
        IF user_id_val IS NOT NULL THEN
            UPDATE user_borrowing_stats
            SET overdue_count = overdue_count + 1
            WHERE user_id = user_id_val;
        END IF;
    END IF;
END$$

DELIMITER ;

-- Add fine/penalty table
CREATE TABLE fines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_id INT NOT NULL,
    patron_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255),
    days_overdue INT,
    status ENUM('unpaid', 'paid', 'waived') DEFAULT 'unpaid',
    paid_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON DELETE CASCADE,
    FOREIGN KEY (patron_id) REFERENCES patrons(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_patron_id (patron_id)
);

-- Add settings table for customizable values
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('default_borrow_days', '14', 'Default number of days for borrowing a book'),
('fine_per_day', '5.00', 'Fine amount per day for overdue books'),
('max_fine_amount', '500.00', 'Maximum fine amount per book');

-- Add trigger to automatically create fines for overdue books
DELIMITER $$
CREATE TRIGGER create_fine_on_overdue
AFTER UPDATE ON borrowings
FOR EACH ROW
BEGIN
    DECLARE days_late INT;
    DECLARE fine_amount DECIMAL(10,2);
    DECLARE fine_per_day DECIMAL(10,2);
    DECLARE max_fine DECIMAL(10,2);
    
    -- Only process when status changes to overdue
    IF OLD.status != 'overdue' AND NEW.status = 'overdue' THEN
        -- Get fine settings
        SELECT CAST(setting_value AS DECIMAL(10,2)) INTO fine_per_day 
        FROM system_settings WHERE setting_key = 'fine_per_day';
        
        SELECT CAST(setting_value AS DECIMAL(10,2)) INTO max_fine 
        FROM system_settings WHERE setting_key = 'max_fine_amount';
        
        -- Calculate days late
        SET days_late = DATEDIFF(CURDATE(), NEW.due_date);
        
        -- Calculate fine (with maximum limit)
        SET fine_amount = LEAST(days_late * fine_per_day, max_fine);
        
        -- Create fine record if doesn't exist
        INSERT INTO fines (borrowing_id, patron_id, amount, reason, days_overdue, status)
        VALUES (NEW.id, NEW.patron_id, fine_amount, 'Overdue book', days_late, 'unpaid')
        ON DUPLICATE KEY UPDATE 
            amount = fine_amount,
            days_overdue = days_late;
    END IF;
END$$
DELIMITER ;