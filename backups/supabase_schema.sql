    -- ============================================================
    -- PARE SYSTEM - PostgreSQL Schema (for Supabase)
    -- ============================================================

    -- Create ENUM types safely
    DO $$ BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
            CREATE TYPE user_role AS ENUM ('passenger', 'admin');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'passenger_type_enum') THEN
            CREATE TYPE passenger_type_enum AS ENUM ('Regular', 'Non-Regular', 'Discounted');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'trip_status') THEN
            CREATE TYPE trip_status AS ENUM ('active', 'completed', 'cancelled');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ticket_status') THEN
            CREATE TYPE ticket_status AS ENUM ('issued', 'validated', 'cancelled');
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'payment_method_enum') THEN
            CREATE TYPE payment_method_enum AS ENUM ('cash', 'qr', 'card');
        END IF;
    END $$;

    -- ============================================================
    -- TABLE: users (Passengers)
    -- ============================================================
    CREATE TABLE IF NOT EXISTS users (
        id              SERIAL PRIMARY KEY,
        full_name       VARCHAR(150) NOT NULL,
        id_number       VARCHAR(50)  NOT NULL UNIQUE,
        id_picture      VARCHAR(255) DEFAULT NULL,
        address         TEXT         NOT NULL,
        contact_number  VARCHAR(20)  NOT NULL,
        emergency_contact_name    VARCHAR(150) NOT NULL,
        emergency_contact_address TEXT         NOT NULL,
        email           VARCHAR(150) UNIQUE DEFAULT NULL,
        password        VARCHAR(255) NOT NULL,
        role            user_role DEFAULT 'passenger',
        is_active       BOOLEAN DEFAULT TRUE,
        created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );

    -- ============================================================
    -- TABLE: drivers
    -- ============================================================
    CREATE TABLE IF NOT EXISTS drivers (
        id              SERIAL PRIMARY KEY,
        full_name       VARCHAR(150) NOT NULL,
        license_number  VARCHAR(50)  NOT NULL UNIQUE,
        contact_number  VARCHAR(20)  NOT NULL,
        email           VARCHAR(150) UNIQUE DEFAULT NULL,
        password        VARCHAR(255) NOT NULL,
        profile_picture VARCHAR(255) DEFAULT NULL,
        is_active       BOOLEAN DEFAULT TRUE,
        created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );

    -- ============================================================
    -- TABLE: buses
    -- ============================================================
    CREATE TABLE IF NOT EXISTS buses (
        id            SERIAL PRIMARY KEY,
        plate_number  VARCHAR(20)  NOT NULL UNIQUE,
        body_number   VARCHAR(20)  NOT NULL UNIQUE,
        model         VARCHAR(100) DEFAULT NULL,
        capacity      INTEGER DEFAULT 22,
        driver_id     INTEGER NOT NULL,
        is_active     BOOLEAN DEFAULT TRUE,
        created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_bus_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT
    );

    -- ============================================================
    -- TABLE: stations
    -- ============================================================
    CREATE TABLE IF NOT EXISTS stations (
        id            SERIAL PRIMARY KEY,
        station_name  VARCHAR(100)    NOT NULL,
        km_marker     DECIMAL(6,2)    NOT NULL UNIQUE,
        latitude      DECIMAL(10,7)   DEFAULT NULL,
        longitude     DECIMAL(10,7)   DEFAULT NULL,
        is_terminal   BOOLEAN         DEFAULT FALSE,
        sort_order    INTEGER         NOT NULL DEFAULT 0,
        is_active     BOOLEAN         DEFAULT TRUE
    );
    CREATE INDEX IF NOT EXISTS idx_stations_sort ON stations(sort_order);

    -- ============================================================
    -- TABLE: fare_matrix
    -- ============================================================
    CREATE TABLE IF NOT EXISTS fare_matrix (
        id              SERIAL PRIMARY KEY,
        passenger_type  passenger_type_enum NOT NULL UNIQUE,
        base_km         DECIMAL(5,2)  NOT NULL DEFAULT 4.00,
        base_fare       DECIMAL(8,2)  NOT NULL DEFAULT 15.00,
        per_km_rate     DECIMAL(8,2)  NOT NULL DEFAULT 2.00,
        updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );

    -- ============================================================
    -- TABLE: trips
    -- ============================================================
    CREATE TABLE IF NOT EXISTS trips (
        id                SERIAL PRIMARY KEY,
        bus_id            INTEGER NOT NULL,
        driver_id         INTEGER NOT NULL,
        start_station_id  INTEGER NOT NULL,
        end_station_id    INTEGER NOT NULL,
        started_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        ended_at          TIMESTAMP WITH TIME ZONE NULL DEFAULT NULL,
        status            trip_status DEFAULT 'active',
        total_revenue     DECIMAL(10,2) DEFAULT 0.00,
        passenger_count   INTEGER DEFAULT 0,
        CONSTRAINT fk_trip_bus    FOREIGN KEY (bus_id)           REFERENCES buses(id)    ON DELETE RESTRICT,
        CONSTRAINT fk_trip_driver FOREIGN KEY (driver_id)        REFERENCES drivers(id)  ON DELETE RESTRICT,
        CONSTRAINT fk_trip_start  FOREIGN KEY (start_station_id) REFERENCES stations(id) ON DELETE RESTRICT,
        CONSTRAINT fk_trip_end    FOREIGN KEY (end_station_id)   REFERENCES stations(id) ON DELETE RESTRICT
    );
    CREATE INDEX IF NOT EXISTS idx_trip_bus_status ON trips(bus_id, status);

    -- ============================================================
    -- TABLE: tickets
    -- ============================================================
    CREATE TABLE IF NOT EXISTS tickets (
        id                  SERIAL PRIMARY KEY,
        ticket_code         VARCHAR(25)  NOT NULL UNIQUE,
        trip_id             INTEGER NOT NULL,
        passenger_id        INTEGER DEFAULT NULL,
        passenger_name      VARCHAR(150) DEFAULT 'Walk-in',
        passenger_type      passenger_type_enum NOT NULL DEFAULT 'Regular',
        origin_station_id   INTEGER NOT NULL,
        dest_station_id     INTEGER NOT NULL,
        origin_name         VARCHAR(100) NOT NULL,
        dest_name           VARCHAR(100) NOT NULL,
        distance_km         DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        fare_amount         DECIMAL(8,2) NOT NULL,
        issued_at           TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        status              ticket_status DEFAULT 'issued',
        CONSTRAINT fk_ticket_trip      FOREIGN KEY (trip_id)           REFERENCES trips(id)    ON DELETE RESTRICT,
        CONSTRAINT fk_ticket_passenger FOREIGN KEY (passenger_id)      REFERENCES users(id)    ON DELETE SET NULL,
        CONSTRAINT fk_ticket_origin    FOREIGN KEY (origin_station_id) REFERENCES stations(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ticket_dest      FOREIGN KEY (dest_station_id)   REFERENCES stations(id) ON DELETE RESTRICT
    );
    CREATE INDEX IF NOT EXISTS idx_ticket_trip ON tickets(trip_id);
    CREATE INDEX IF NOT EXISTS idx_ticket_issued ON tickets(issued_at);

    -- ============================================================
    -- TABLE: payments
    -- ============================================================
    CREATE TABLE IF NOT EXISTS payments (
        id              SERIAL PRIMARY KEY,
        ticket_id       INTEGER NOT NULL UNIQUE,
        amount_paid     DECIMAL(8,2) NOT NULL,
        payment_method  payment_method_enum DEFAULT 'cash',
        paid_at         TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        remitted        BOOLEAN DEFAULT FALSE,
        remitted_at     TIMESTAMP WITH TIME ZONE NULL DEFAULT NULL,
        CONSTRAINT fk_payment_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    );

    -- ============================================================
    -- TABLE: bus_locations
    -- ============================================================
    CREATE TABLE IF NOT EXISTS bus_locations (
        id          SERIAL PRIMARY KEY,
        bus_id      INTEGER NOT NULL,
        trip_id     INTEGER DEFAULT NULL,
        latitude    DECIMAL(10,7) NOT NULL,
        longitude   DECIMAL(10,7) NOT NULL,
        speed_kmh   DECIMAL(5,2)  DEFAULT 0.00,
        recorded_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_loc_bus  FOREIGN KEY (bus_id)  REFERENCES buses(id)  ON DELETE CASCADE,
        CONSTRAINT fk_loc_trip FOREIGN KEY (trip_id) REFERENCES trips(id)  ON DELETE SET NULL
    );
    CREATE INDEX IF NOT EXISTS idx_bus_time ON bus_locations(bus_id, recorded_at DESC);

    -- ============================================================
    -- SEED DATA
    -- ============================================================

    INSERT INTO fare_matrix (passenger_type, base_km, base_fare, per_km_rate) VALUES
    ('Regular',     4.00, 15.00, 2.00),
    ('Non-Regular', 4.00, 15.00, 2.00),
    ('Discounted',  4.00, 12.00, 1.60)
    ON CONFLICT (passenger_type) DO UPDATE SET base_fare = EXCLUDED.base_fare;

    INSERT INTO stations (station_name, km_marker, latitude, longitude, is_terminal, sort_order) VALUES
    ('Aglipay Terminal',   0.00,  16.4900, 121.1200, TRUE, 1),
    ('Brgy. Alicia',       2.50,  16.4750, 121.1280, FALSE, 2),
    ('Brgy. Luzon',        5.00,  16.4600, 121.1360, FALSE, 3),
    ('Maddela Junction',   8.00,  16.4400, 121.1480, FALSE, 4),
    ('Brgy. Dagupan',     11.00,  16.4200, 121.1600, FALSE, 5),
    ('Cabarroguis Center',14.50,  16.4000, 121.1750, FALSE, 6),
    ('Brgy. Progreso',    17.00,  16.3800, 121.1900, FALSE, 7),
    ('Diffun Terminal',   20.00,  16.3621, 121.0345, TRUE, 8)
    ON CONFLICT (km_marker) DO NOTHING;

    INSERT INTO users (full_name, id_number, address, contact_number, emergency_contact_name, emergency_contact_address, email, password, role)
    VALUES (
        'System Admin', 'ADMIN-001', 'PARE Office, Quirino', '000-0000-000',
        'N/A', 'N/A', 'admin@pare.local',
        '$2y$12$YDDl1tXbqH3V5OQ0YDLXaehAtbSNQ5IkbX2cQZa9E.LQ0kPpH/4yS',
        'admin'
    ) ON CONFLICT (id_number) DO NOTHING;

    INSERT INTO drivers (full_name, license_number, contact_number, email, password)
    VALUES (
        'Juan Dela Cruz', 'QR-1234567', '09171234567',
        'driver1@pare.local',
        '$2y$12$YDDl1tXbqH3V5OQ0YDLXaehAtbSNQ5IkbX2cQZa9E.LQ0kPpH/4yS'
    ) ON CONFLICT (license_number) DO NOTHING;

    INSERT INTO buses (plate_number, body_number, model, capacity, driver_id)
    VALUES ('QME-1234', 'BUS-001', 'E-Jeepney CMCI 2023', 22, 1)
    ON CONFLICT (plate_number) DO NOTHING;
