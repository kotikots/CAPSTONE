-- ============================================================
-- PARE SYSTEM - Full Database Migration Script
-- Safely drops old schema and creates complete normalized DB
-- Run once: mysql -u root pare < pare_migrate.sql
-- ============================================================

USE pare;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop old / partial tables
DROP TABLE IF EXISTS transactions;  -- replaced by tickets + payments
DROP TABLE IF EXISTS buses;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS fare_matrix;
DROP TABLE IF EXISTS stations;
DROP TABLE IF EXISTS users;

-- Drop new tables if re-running (clean slate)
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS bus_locations;
DROP TABLE IF EXISTS trips;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLE: users  (Passengers + Admins)
-- ============================================================
CREATE TABLE users (
    id                        INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    full_name                 VARCHAR(150)    NOT NULL,
    id_number                 VARCHAR(50)     NOT NULL UNIQUE,
    id_picture                VARCHAR(255)    DEFAULT NULL,
    address                   TEXT            NOT NULL,
    contact_number            VARCHAR(20)     NOT NULL,
    emergency_contact_name    VARCHAR(150)    NOT NULL,
    emergency_contact_address TEXT            NOT NULL,
    email                     VARCHAR(150)    UNIQUE DEFAULT NULL,
    password                  VARCHAR(255)    NOT NULL,
    role                      ENUM('passenger','admin') DEFAULT 'passenger',
    is_active                 TINYINT(1)      DEFAULT 1,
    created_at                TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: drivers
-- ============================================================
CREATE TABLE drivers (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)    NOT NULL,
    license_number  VARCHAR(50)     NOT NULL UNIQUE,
    contact_number  VARCHAR(20)     NOT NULL,
    email           VARCHAR(150)    UNIQUE DEFAULT NULL,
    password        VARCHAR(255)    NOT NULL,
    profile_picture VARCHAR(255)    DEFAULT NULL,
    is_active       TINYINT(1)      DEFAULT 1,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: buses  (permanently assigned to one driver)
-- ============================================================
CREATE TABLE buses (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    plate_number  VARCHAR(20)     NOT NULL UNIQUE,
    body_number   VARCHAR(20)     NOT NULL UNIQUE,
    model         VARCHAR(100)    DEFAULT NULL,
    capacity      INT UNSIGNED    DEFAULT 22,
    driver_id     INT UNSIGNED    NOT NULL,
    is_active     TINYINT(1)      DEFAULT 1,
    created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bus_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: stations  (single linear route, A → B)
-- ============================================================
CREATE TABLE stations (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    station_name  VARCHAR(100)    NOT NULL,
    km_marker     DECIMAL(6,2)    NOT NULL,
    latitude      DECIMAL(10,7)   DEFAULT NULL,
    longitude     DECIMAL(10,7)   DEFAULT NULL,
    is_terminal   TINYINT(1)      DEFAULT 0,
    sort_order    INT UNSIGNED    NOT NULL DEFAULT 0,
    is_active     TINYINT(1)      DEFAULT 1,
    UNIQUE KEY uq_km (km_marker),
    KEY idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: fare_matrix
-- ============================================================
CREATE TABLE fare_matrix (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    passenger_type  ENUM('Regular','Non-Regular','Discounted') NOT NULL UNIQUE,
    base_km         DECIMAL(5,2)    NOT NULL DEFAULT 4.00,
    base_fare       DECIMAL(8,2)    NOT NULL DEFAULT 15.00,
    per_km_rate     DECIMAL(8,2)    NOT NULL DEFAULT 2.00,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: trips  (one run from terminal A to terminal B)
-- ============================================================
CREATE TABLE trips (
    id                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    bus_id            INT UNSIGNED    NOT NULL,
    driver_id         INT UNSIGNED    NOT NULL,
    start_station_id  INT UNSIGNED    NOT NULL,
    end_station_id    INT UNSIGNED    NOT NULL,
    started_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    ended_at          TIMESTAMP       NULL DEFAULT NULL,
    status            ENUM('active','completed','cancelled') DEFAULT 'active',
    total_revenue     DECIMAL(10,2)   DEFAULT 0.00,
    passenger_count   INT UNSIGNED    DEFAULT 0,
    CONSTRAINT fk_trip_bus    FOREIGN KEY (bus_id)           REFERENCES buses(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_trip_driver FOREIGN KEY (driver_id)        REFERENCES drivers(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_trip_start  FOREIGN KEY (start_station_id) REFERENCES stations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_trip_end    FOREIGN KEY (end_station_id)   REFERENCES stations(id) ON DELETE RESTRICT,
    KEY idx_bus_status (bus_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: tickets  (every fare ticket, kiosk or web)
-- ============================================================
CREATE TABLE tickets (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    ticket_code         VARCHAR(25)     NOT NULL UNIQUE,
    trip_id             INT UNSIGNED    NOT NULL,
    passenger_id        INT UNSIGNED    DEFAULT NULL,
    passenger_name      VARCHAR(150)    DEFAULT 'Walk-in',
    passenger_type      ENUM('Regular','Non-Regular','Discounted') NOT NULL DEFAULT 'Regular',
    origin_station_id   INT UNSIGNED    NOT NULL,
    dest_station_id     INT UNSIGNED    NOT NULL,
    origin_name         VARCHAR(100)    NOT NULL,
    dest_name           VARCHAR(100)    NOT NULL,
    distance_km         DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    fare_amount         DECIMAL(8,2)    NOT NULL,
    issued_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    status              ENUM('issued','validated','cancelled') DEFAULT 'issued',
    CONSTRAINT fk_ticket_trip      FOREIGN KEY (trip_id)           REFERENCES trips(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_passenger FOREIGN KEY (passenger_id)      REFERENCES users(id)    ON DELETE SET NULL,
    CONSTRAINT fk_ticket_origin    FOREIGN KEY (origin_station_id) REFERENCES stations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_dest      FOREIGN KEY (dest_station_id)   REFERENCES stations(id) ON DELETE RESTRICT,
    KEY idx_trip   (trip_id),
    KEY idx_issued (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: payments
-- ============================================================
CREATE TABLE payments (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT UNSIGNED    NOT NULL UNIQUE,
    amount_paid     DECIMAL(8,2)    NOT NULL,
    payment_method  ENUM('cash','qr','card') DEFAULT 'cash',
    paid_at         TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    remitted        TINYINT(1)      DEFAULT 0,
    remitted_at     TIMESTAMP       NULL DEFAULT NULL,
    CONSTRAINT fk_payment_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: bus_locations  (real-time / simulated GPS)
-- ============================================================
CREATE TABLE bus_locations (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    bus_id      INT UNSIGNED    NOT NULL,
    trip_id     INT UNSIGNED    DEFAULT NULL,
    latitude    DECIMAL(10,7)   NOT NULL,
    longitude   DECIMAL(10,7)   NOT NULL,
    speed_kmh   DECIMAL(5,2)    DEFAULT 0.00,
    recorded_at TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loc_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id)  ON DELETE CASCADE,
    CONSTRAINT fk_loc_trip FOREIGN KEY (trip_id) REFERENCES trips(id)  ON DELETE SET NULL,
    INDEX idx_bus_time (bus_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Fare matrix
INSERT INTO fare_matrix (passenger_type, base_km, base_fare, per_km_rate) VALUES
('Regular',     4.00, 15.00, 2.00),
('Non-Regular', 4.00, 15.00, 2.00),
('Discounted',  4.00, 12.00, 1.60);

-- Stations: Aglipay → Diffun (linear, one-way route)
INSERT INTO stations (station_name, km_marker, latitude, longitude, is_terminal, sort_order) VALUES
('Aglipay Terminal',    0.00,  16.4900, 121.1200, 1, 1),
('Brgy. Alicia',        2.50,  16.4750, 121.1280, 0, 2),
('Brgy. Luzon',         5.00,  16.4600, 121.1360, 0, 3),
('Maddela Junction',    8.00,  16.4400, 121.1480, 0, 4),
('Brgy. Dagupan',      11.00,  16.4200, 121.1600, 0, 5),
('Cabarroguis Center', 14.50,  16.4000, 121.1750, 0, 6),
('Brgy. Progreso',     17.00,  16.3800, 121.1900, 0, 7),
('Diffun Terminal',    20.00,  16.3621, 121.0345, 1, 8);

-- Admin account  (password: Admin@1234)
INSERT INTO users (full_name, id_number, address, contact_number, emergency_contact_name, emergency_contact_address, email, password, role)
VALUES (
    'System Admin', 'ADMIN-001', 'PARE Office, Quirino', '090-0000-000',
    'N/A', 'N/A', 'admin@pare.local',
    '$2y$12$YDDl1tXbqH3V5OQ0YDLXaehAtbSNQ5IkbX2cQZa9E.LQ0kPpH/4yS', 'admin'
);

-- Sample driver  (password: Admin@1234)
INSERT INTO drivers (full_name, license_number, contact_number, email, password)
VALUES (
    'Juan Dela Cruz', 'QR-1234567', '09171234567',
    'driver1@pare.local',
    '$2y$12$YDDl1tXbqH3V5OQ0YDLXaehAtbSNQ5IkbX2cQZa9E.LQ0kPpH/4yS'
);

-- Sample bus permanently assigned to driver 1
INSERT INTO buses (plate_number, body_number, model, capacity, driver_id)
VALUES ('QME-1234', 'BUS-001', 'E-Jeepney CMCI 2023', 22, 1);
