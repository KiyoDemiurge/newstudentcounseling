CREATE DATABASE discipline_system;
USE discipline_system;

-- USERS TABLE (ADMIN, COUNSELOR, STUDENT)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin','counselor','student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DISCIPLINE REPORTS TABLE
CREATE TABLE discipline_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    reported_by INT,
    offense VARCHAR(255),
    description TEXT,
    date_reported DATE,
    status ENUM('Pending','Resolved') DEFAULT 'Pending',
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
);

-- COUNSELING RECORDS TABLE
CREATE TABLE counseling_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    counselor_id INT,
    session_date DATE,
    notes TEXT,
    recommendation TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (counselor_id) REFERENCES users(id)
);
