  CREATE DATABASE maslahastore_db;
USE maslahastore_db;

-- Users (with roles)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- use password_hash()
    role ENUM('owner', 'manager', 'cashier') DEFAULT 'cashier',
    pin VARCHAR(6) DEFAULT NULL,     -- for quick PIN login
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products (main inventory)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    unit ENUM('kg', 'pieces', 'liters', 'crates', 'other') NOT NULL,
    conversion_factor DECIMAL(10,2) DEFAULT 1.00,  -- e.g., 1 crate = 24 bottles
    cost_price DECIMAL(12,2) NOT NULL,
    selling_price DECIMAL(12,2) NOT NULL,
    low_stock_threshold INT DEFAULT 10,
    description TEXT,
    barcode VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Batches (for expiry & batch tracking – critical for perishables)
CREATE TABLE product_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_number VARCHAR(50),
    manufacture_date DATE,
    expiry_date DATE,
    quantity INT NOT NULL DEFAULT 0,
    cost_price DECIMAL(12,2),  -- can vary per batch
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Current total stock = SUM(product_batches.quantity) per product (view/query)

-- Sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2) NOT NULL,
    vat DECIMAL(12,2) DEFAULT 0.00,          -- 7.5% of subtotal
    grand_total DECIMAL(12,2) NOT NULL,
    payment_method ENUM('Cash', 'OPay', 'PalmPay', 'Bank Transfer', 'Other'),
    discount DECIMAL(12,2) DEFAULT 0.00,
    is_synced TINYINT(1) DEFAULT 1,          -- for offline
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NOT NULL,                   -- which batch deducted
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(12,2) NOT NULL,            -- selling price at time
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (batch_id) REFERENCES product_batches(id)
);

-- Expenses
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(100),
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Returns / Refunds
CREATE TABLE returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reason TEXT,
    return_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id)
);

-- Stock Adjustments (damages, gifts, manual)
CREATE TABLE stock_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_id INT,
    adjustment INT NOT NULL,          -- positive = add, negative = remove
    reason VARCHAR(100),              -- 'Damage', 'Gift', 'Correction', etc.
    adjustment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id INT NOT NULL
);