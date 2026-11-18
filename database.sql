CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    balance INT NOT NULL DEFAULT 300,
    xp INT UNSIGNED NOT NULL DEFAULT 0,
    last_work_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(40) NOT NULL,
    species ENUM('Cão', 'Gato', 'Coelho', 'Hamster', 'Calopsita') NOT NULL,
    variety VARCHAR(60) NOT NULL,
    coat VARCHAR(30) NOT NULL,
    trait VARCHAR(30) NOT NULL,
    starting_age_months TINYINT UNSIGNED NOT NULL DEFAULT 3,
    hunger TINYINT UNSIGNED NOT NULL DEFAULT 82,
    thirst TINYINT UNSIGNED NOT NULL DEFAULT 90,
    hygiene TINYINT UNSIGNED NOT NULL DEFAULT 85,
    energy TINYINT UNSIGNED NOT NULL DEFAULT 88,
    health TINYINT UNSIGNED NOT NULL DEFAULT 100,
    happiness TINYINT UNSIGNED NOT NULL DEFAULT 82,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 35,
    obedience TINYINT UNSIGNED NOT NULL DEFAULT 0,
    home_cleanliness TINYINT UNSIGNED NOT NULL DEFAULT 85,
    vaccinated_until DATE NULL,
    neutered TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    memorial_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY pets_user_active (user_id, memorial_at),
    CONSTRAINT pets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
    user_id INT UNSIGNED NOT NULL,
    item_code VARCHAR(40) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, item_code),
    CONSTRAINT inventory_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medical_records (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pet_id INT UNSIGNED NOT NULL,
    record_type VARCHAR(30) NOT NULL,
    description VARCHAR(255) NOT NULL,
    cost INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY medical_pet_date (pet_id, created_at),
    CONSTRAINT medical_pet_fk FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
