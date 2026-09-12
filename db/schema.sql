-- Cilantro Café — bookings schema (MySQL / MariaDB)
--
-- Run once in Hostinger: hPanel > Databases > phpMyAdmin > Import.
-- Safe to re-run: every statement is IF NOT EXISTS.
--
-- Note on status/source columns: these are VARCHAR with the allowed values
-- enforced in PHP (booking_statuses() / booking_sources()), not MySQL ENUM.
-- Adding a status to an ENUM needs an ALTER TABLE on a live table; this way it
-- is a one-line code change. It also lets the test suite run the same SQL on
-- SQLite.

CREATE TABLE IF NOT EXISTS customers (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(120)  NOT NULL,
    phone                 VARCHAR(32)   NOT NULL,
    phone_key             VARCHAR(20)   NOT NULL,
    email                 VARCHAR(190)  NOT NULL,
    notes                 TEXT          NULL,
    marketing_consent     TINYINT       NOT NULL DEFAULT 0,
    marketing_consent_at  DATETIME      NULL,
    created_at            DATETIME      NOT NULL,
    updated_at            DATETIME      NOT NULL,
    anonymised_at         DATETIME      NULL,
    UNIQUE KEY uq_customers_phone_key (phone_key),
    KEY idx_customers_email (email),
    KEY idx_customers_name  (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT UNSIGNED NOT NULL,
    booking_date    DATE         NOT NULL,
    booking_time    VARCHAR(5)   NOT NULL,
    guests          SMALLINT     NOT NULL,
    seating         VARCHAR(40)  NOT NULL,
    notes           TEXT         NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'requested',
    source          VARCHAR(20)  NOT NULL DEFAULT 'website',
    google_event_id VARCHAR(255) NULL,
    changed_by      VARCHAR(60)  NOT NULL DEFAULT 'staff',
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    CONSTRAINT fk_bookings_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    KEY idx_bookings_date_time (booking_date, booking_time),
    KEY idx_bookings_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No foreign key here on purpose: the audit trail has to outlive the booking
-- it describes, otherwise deleting a booking quietly erases its own history.
CREATE TABLE IF NOT EXISTS booking_audit (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NOT NULL,
    action      VARCHAR(40)  NOT NULL,
    old_status  VARCHAR(20)  NULL,
    new_status  VARCHAR(20)  NULL,
    actor       VARCHAR(60)  NOT NULL,
    ip          VARCHAR(45)  NULL,
    at          DATETIME     NOT NULL,
    KEY idx_audit_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
