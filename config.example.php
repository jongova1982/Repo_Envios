<?php
declare(strict_types=1);

/*
 * Copia este archivo como config.php y reemplaza los valores de ejemplo.
 * También puedes definir JONGOX_DB_HOST, JONGOX_DB_NAME, JONGOX_DB_USER y
 * JONGOX_DB_PASSWORD como variables de entorno. No publiques config.php.
 */

function jongox_database_config(): array
{
    return [
        'host' => getenv('JONGOX_DB_HOST') ?: 'mysql-servidor.ejemplo',
        'name' => getenv('JONGOX_DB_NAME') ?: 'jongox_envios',
        'user' => getenv('JONGOX_DB_USER') ?: 'jongox',
        'password' => getenv('JONGOX_DB_PASSWORD') ?: 'jongox#@1234',
    ];
}

function jongox_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = jongox_database_config();
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['name']);

    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function jongox_create_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS remitente (
            id_remitente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            departamento VARCHAR(100) NOT NULL,
            ciudad VARCHAR(100) NOT NULL,
            direccion VARCHAR(255) NOT NULL,
            telefono VARCHAR(30) NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS destinatario (
            id_destinatario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            departamento VARCHAR(100) NOT NULL,
            ciudad VARCHAR(100) NOT NULL,
            direccion VARCHAR(255) NOT NULL,
            telefono VARCHAR(30) NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS envio (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function jongox_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jongox_csrf_token(): string
{
    if (empty($_SESSION['jongox_csrf'])) {
        $_SESSION['jongox_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['jongox_csrf'];
}

function jongox_string_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($characters) ? count($characters) : strlen($value);
}












