-- Esquema de referencia. La aplicación ejecuta estas sentencias automáticamente
-- al cargarse por primera vez, por lo que no es necesario importarlo a mano.

CREATE TABLE IF NOT EXISTS remitente (
    id_remitente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    departamento VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS destinatario (
    id_destinatario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    departamento VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS envio (
    id_envio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_remitente INT UNSIGNED NOT NULL,
    id_destinatario INT UNSIGNED NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_envio_remitente FOREIGN KEY (id_remitente)
        REFERENCES remitente(id_remitente) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_envio_destinatario FOREIGN KEY (id_destinatario)
        REFERENCES destinatario(id_destinatario) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_envio_remitente (id_remitente),
    INDEX idx_envio_destinatario (id_destinatario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
