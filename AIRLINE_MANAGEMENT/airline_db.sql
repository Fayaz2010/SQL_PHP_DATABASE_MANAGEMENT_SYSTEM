-- ============================================================
--  Airline Management System — Complete Database (All Features)
--  Run this entire file in phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS airline_db;
USE airline_db;

CREATE TABLE IF NOT EXISTS airports (
    airport_id   INT AUTO_INCREMENT PRIMARY KEY,
    airport_name VARCHAR(100) NOT NULL,
    city         VARCHAR(100) NOT NULL,
    country      VARCHAR(100) NOT NULL,
    airport_code VARCHAR(10)  NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS aircraft (
    aircraft_id     INT AUTO_INCREMENT PRIMARY KEY,
    model           VARCHAR(100) NOT NULL,
    capacity        INT          NOT NULL,
    manufacturer    VARCHAR(100) NOT NULL,
    airline_company VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS passengers (
    passenger_id    INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    date_of_birth   DATE         NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    passport_number VARCHAR(50)  NOT NULL UNIQUE,
    nationality     VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS passenger_phones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT         NOT NULL,
    phone        VARCHAR(20) NOT NULL,
    FOREIGN KEY (passenger_id) REFERENCES passengers(passenger_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS employees (
    employee_id   INT AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    street        VARCHAR(100),
    city          VARCHAR(100),
    country       VARCHAR(100),
    role          ENUM('Pilot','Co-Pilot','Cabin Crew','Ground Staff') NOT NULL,
    salary        DECIMAL(10,2),
    hire_date     DATE NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    supervisor_id INT DEFAULT NULL,
    FOREIGN KEY (supervisor_id) REFERENCES employees(employee_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employee_languages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT         NOT NULL,
    language    VARCHAR(50) NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- base_price set by admin when creating a flight (Economy base)
-- Business = base_price + 150, First Class = base_price + 250
CREATE TABLE IF NOT EXISTS flights (
    flight_id            INT AUTO_INCREMENT PRIMARY KEY,
    aircraft_id          INT NOT NULL,
    departure_airport_id INT NOT NULL,
    arrival_airport_id   INT NOT NULL,
    departure_time       DATETIME NOT NULL,
    arrival_time         DATETIME NOT NULL,
    flight_status        ENUM('Scheduled','Delayed','Cancelled') DEFAULT 'Scheduled',
    base_price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (aircraft_id)          REFERENCES aircraft(aircraft_id),
    FOREIGN KEY (departure_airport_id) REFERENCES airports(airport_id),
    FOREIGN KEY (arrival_airport_id)   REFERENCES airports(airport_id)
);

CREATE TABLE IF NOT EXISTS crew_assignment (
    assignment_id   INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT         NOT NULL,
    flight_id       INT         NOT NULL,
    aircraft_id     INT         NOT NULL,
    role            VARCHAR(50) NOT NULL,
    assignment_date DATE        NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (flight_id)   REFERENCES flights(flight_id)     ON DELETE CASCADE,
    FOREIGN KEY (aircraft_id) REFERENCES aircraft(aircraft_id)
);

CREATE TABLE IF NOT EXISTS bookings (
    booking_id     INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id   INT         NOT NULL,
    flight_id      INT         NOT NULL,
    booking_date   DATE        NOT NULL,
    seat_number    VARCHAR(10) NOT NULL,
    booking_status ENUM('Confirmed','Cancelled','Pending') DEFAULT 'Pending',
    FOREIGN KEY (passenger_id) REFERENCES passengers(passenger_id) ON DELETE CASCADE,
    FOREIGN KEY (flight_id)    REFERENCES flights(flight_id)       ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tickets (
    ticket_id    INT AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT           NOT NULL UNIQUE,
    ticket_price DECIMAL(10,2) NOT NULL,
    seat_class   ENUM('Economy','Business','First Class') NOT NULL,
    issue_date   DATE NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    booking_id     INT           NOT NULL UNIQUE,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Card','Cash','Online') NOT NULL,
    payment_date   DATE NOT NULL,
    payment_status ENUM('Pending','Completed','Refunded') DEFAULT 'Pending',
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

-- Baggage: weak entity, composite PK (booking_id, bag_number)
-- bag_number is the partial key
CREATE TABLE IF NOT EXISTS baggage (
    booking_id     INT          NOT NULL,
    bag_number     INT          NOT NULL,
    weight         DECIMAL(5,2) NOT NULL,
    baggage_status ENUM('Checked-In','Loaded','In Transit','Delivered','Lost') DEFAULT 'Checked-In',
    PRIMARY KEY (booking_id, bag_number),
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
);

-- ── If your database already exists, just run this one line: ──
-- ALTER TABLE flights ADD COLUMN base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER flight_status;
