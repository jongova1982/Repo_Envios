<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$configFile = __DIR__ . '/config.php';
$configurationMissing = !is_file($configFile);
require_once $configurationMissing ? __DIR__ . '/config.example.php' : $configFile;

$fieldLabels = [
    'remitente_nombre' => 'Nombre del remitente',
    'remitente_departamento' => 'Departamento del remitente',
    'remitente_ciudad' => 'Ciudad del remitente',
    'remitente_direccion' => 'Dirección del remitente',
    'remitente_telefono' => 'Teléfono del remitente',
    'destinatario_nombre' => 'Nombre del destinatario',
    'destinatario_departamento' => 'Departamento del destinatario',
    'destinatario_ciudad' => 'Ciudad del destinatario',
    'destinatario_direccion' => 'Dirección del destinatario',
    'destinatario_telefono' => 'Teléfono del destinatario',
    'descripcion' => 'Descripción del envío',
];

$fieldLimits = [
    'remitente_nombre' => 120,
    'remitente_departamento' => 100,
    'remitente_ciudad' => 100,
    'remitente_direccion' => 255,
    'remitente_telefono' => 30,
    'destinatario_nombre' => 120,
    'destinatario_departamento' => 100,
    'destinatario_ciudad' => 100,
    'destinatario_direccion' => 255,
    'destinatario_telefono' => 30,
    'descripcion' => 1500,
];

$form = array_fill_keys(array_keys($fieldLabels), '');
$errors = [];
$envios = [];
$totalEnvios = 0;
$databaseReady = false;
$databaseError = null;
$pdo = null;

try {
    $pdo = jongox_pdo();
    jongox_create_tables($pdo);
    $databaseReady = true;
} catch (Throwable $exception) {
    $databaseError = $configurationMissing
        ? 'Falta config.php. Copia config.example.php como config.php y completa la conexión a MySQL.'
        : 'No fue posible conectar con la base de datos. Verifica la configuración de config.php e inténtalo de nuevo.';
    error_log('Jongox Envíos: ' . $exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fieldLabels as $field => $label) {
        $value = $_POST[$field] ?? '';
        $form[$field] = is_string($value) ? trim($value) : '';
    }

    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals(jongox_csrf_token(), $postedToken)) {
        $errors['general'] = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
    }

    if (!$databaseReady) {
        $errors['general'] = $databaseError;
    }

    foreach ($fieldLabels as $field => $label) {
        if ($form[$field] === '') {
            $errors[$field] = $label . ' es obligatorio.';
            continue;
        }

        if (jongox_string_length($form[$field]) > $fieldLimits[$field]) {
            $errors[$field] = $label . ' supera el límite permitido.';
        }
    }

    foreach (['remitente_telefono', 'destinatario_telefono'] as $field) {
        if ($form[$field] !== '' && !preg_match('/^[0-9+(). -]{7,30}$/u', $form[$field])) {
            $errors[$field] = 'Ingresa un teléfono válido (7 a 30 caracteres).';
        }
    }

    if (empty($errors) && $pdo instanceof PDO) {
        try {
            $pdo->beginTransaction();

            $insertRemitente = $pdo->prepare(
                'INSERT INTO remitente (nombre, departamento, ciudad, direccion, telefono)
                 VALUES (:nombre, :departamento, :ciudad, :direccion, :telefono)'
            );
            $insertRemitente->execute([
                ':nombre' => $form['remitente_nombre'],
                ':departamento' => $form['remitente_departamento'],
                ':ciudad' => $form['remitente_ciudad'],
                ':direccion' => $form['remitente_direccion'],
                ':telefono' => $form['remitente_telefono'],
            ]);
            $idRemitente = (int) $pdo->lastInsertId();

            $insertDestinatario = $pdo->prepare(
                'INSERT INTO destinatario (nombre, departamento, ciudad, direccion, telefono)
                 VALUES (:nombre, :departamento, :ciudad, :direccion, :telefono)'
            );
            $insertDestinatario->execute([
                ':nombre' => $form['destinatario_nombre'],
                ':departamento' => $form['destinatario_departamento'],
                ':ciudad' => $form['destinatario_ciudad'],
                ':direccion' => $form['destinatario_direccion'],
                ':telefono' => $form['destinatario_telefono'],
            ]);
            $idDestinatario = (int) $pdo->lastInsertId();

            $insertEnvio = $pdo->prepare(
                'INSERT INTO envio (id_remitente, id_destinatario, descripcion)
                 VALUES (:id_remitente, :id_destinatario, :descripcion)'
            );
            $insertEnvio->execute([
                ':id_remitente' => $idRemitente,
                ':id_destinatario' => $idDestinatario,
                ':descripcion' => $form['descripcion'],
            ]);
            $idEnvio = (int) $pdo->lastInsertId();

            $pdo->commit();
            $_SESSION['jongox_flash'] = [
                'type' => 'success',
                'message' => 'Envío registrado correctamente. Guía generada: JGX-' . str_pad((string) $idEnvio, 6, '0', STR_PAD_LEFT) . '.',
            ];

            header('Location: index.php#historial', true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'No fue posible registrar el envío. No se guardó información parcial.';
            error_log('Jongox Envíos al registrar: ' . $exception->getMessage());
        }
    }
}

$flash = $_SESSION['jongox_flash'] ?? null;
unset($_SESSION['jongox_flash']);

if ($databaseReady && $pdo instanceof PDO) {
    try {
        $totalEnvios = (int) $pdo->query('SELECT COUNT(*) FROM envio')->fetchColumn();
        $statement = $pdo->query(
            'SELECT
                e.id_envio,
                e.descripcion,
                e.fecha_creacion,
                r.nombre AS remitente_nombre,
                r.ciudad AS remitente_ciudad,
                d.nombre AS destinatario_nombre,
                d.ciudad AS destinatario_ciudad
             FROM envio e
             INNER JOIN remitente r ON r.id_remitente = e.id_remitente
             INNER JOIN destinatario d ON d.id_destinatario = e.id_destinatario
             ORDER BY e.fecha_creacion DESC, e.id_envio DESC
             LIMIT 12'
        );
        $envios = $statement->fetchAll();
    } catch (Throwable $exception) {
        $errors['general'] = 'El envío se guardó, pero no fue posible cargar el historial.';
        error_log('Jongox Envíos al listar: ' . $exception->getMessage());
    }
}

function form_value(array $form, string $field): string
{
    return jongox_escape($form[$field] ?? '');
}

function field_error_attributes(array $errors, string $field): string
{
    return isset($errors[$field])
        ? ' aria-invalid="true" aria-describedby="' . $field . '-error"'
        : '';
}

function history_excerpt(string $value, int $limit = 220): string
{
    if (jongox_string_length($value) <= $limit) {
        return $value;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($characters) ? implode('', array_slice($characters, 0, $limit)) : substr($value, 0, $limit);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Jongox Envíos: registra y consulta tus envíos de forma sencilla.">
    <title>Jongox Envíos | Gestión logística</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <a class="skip-link" href="#nuevo-envio">Saltar al formulario</a>

    <header class="site-header">
        <div class="container nav-bar">
            <a class="brand" href="#inicio" aria-label="Jongox Envíos, inicio">
                <span class="brand-mark" aria-hidden="true">J</span>
                <span>Jongox <strong>Envíos</strong></span>
            </a>
            <nav aria-label="Navegación principal">
                <a href="#nuevo-envio">Nuevo envío</a>
                <a href="#historial">Historial</a>
            </nav>
        </div>
    </header>

    <main id="inicio">
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow">LOGÍSTICA QUE TE CONECTA</p>
                    <h1>Gestiona tus envíos con <span>claridad y confianza.</span></h1>
                    <p class="hero-lead">Registra remitente, destinatario y detalles del paquete en un solo paso. Jongox organiza cada envío para ti.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="#nuevo-envio">Registrar envío</a>
                        <a class="text-link" href="#historial">Ver historial <span aria-hidden="true">↓</span></a>
                    </div>
                    <ul class="hero-benefits" aria-label="Beneficios">
                        <li><span aria-hidden="true">✓</span> Registro en un solo formulario</li>
                        <li><span aria-hidden="true">✓</span> Guía automática por envío</li>
                    </ul>
                </div>
                <div class="hero-visual">
                    <img src="assets/images/jongox-logistica-hero.png" alt="Ilustración de una camioneta de reparto, paquetes y una ruta de entrega">
                </div>
            </div>
        </section>

        <section class="form-section" id="nuevo-envio" aria-labelledby="form-title">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">NUEVO REGISTRO</p>
                    <h2 id="form-title">Genera un envío</h2>
                    <p>Completa los datos de origen, destino y contenido. Todos los campos son obligatorios.</p>
                </div>

                <?php if ($flash && is_array($flash)): ?>
                    <div class="alert alert-success" role="status">
                        <span class="alert-icon" aria-hidden="true">✓</span>
                        <p><?= jongox_escape((string) ($flash['message'] ?? 'Envío registrado.')) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span class="alert-icon" aria-hidden="true">!</span>
                        <p><?= jongox_escape($errors['general']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!$databaseReady && $databaseError): ?>
                    <div class="connection-note" role="status">
                        <span class="connection-dot" aria-hidden="true"></span>
                        Modo de conexión pendiente: configura el acceso a MySQL para habilitar el registro.
                    </div>
                <?php endif; ?>

                <form class="shipping-form" method="post" data-shipping-form>
                    <input type="hidden" name="csrf_token" value="<?= jongox_escape(jongox_csrf_token()) ?>">

                    <div class="form-columns">
                        <fieldset class="form-panel">
                            <legend>
                                <span class="panel-number">01</span>
                                Datos del remitente
                            </legend>
                            <p class="panel-intro">La persona o empresa que realiza el envío.</p>

                            <div class="field-group">
                                <label for="remitente_nombre">Nombre completo</label>
                                <input id="remitente_nombre" name="remitente_nombre" type="text" maxlength="120" autocomplete="name" value="<?= form_value($form, 'remitente_nombre') ?>" required<?= field_error_attributes($errors, 'remitente_nombre') ?>>
                                <?php if (isset($errors['remitente_nombre'])): ?><small id="remitente_nombre-error" class="field-error"><?= jongox_escape($errors['remitente_nombre']) ?></small><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label for="remitente_departamento">Departamento</label>
                                    <input id="remitente_departamento" name="remitente_departamento" type="text" maxlength="100" autocomplete="address-level1" value="<?= form_value($form, 'remitente_departamento') ?>" required<?= field_error_attributes($errors, 'remitente_departamento') ?>>
                                    <?php if (isset($errors['remitente_departamento'])): ?><small id="remitente_departamento-error" class="field-error"><?= jongox_escape($errors['remitente_departamento']) ?></small><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="remitente_ciudad">Ciudad</label>
                                    <input id="remitente_ciudad" name="remitente_ciudad" type="text" maxlength="100" autocomplete="address-level2" value="<?= form_value($form, 'remitente_ciudad') ?>" required<?= field_error_attributes($errors, 'remitente_ciudad') ?>>
                                    <?php if (isset($errors['remitente_ciudad'])): ?><small id="remitente_ciudad-error" class="field-error"><?= jongox_escape($errors['remitente_ciudad']) ?></small><?php endif; ?>
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="remitente_direccion">Dirección</label>
                                <input id="remitente_direccion" name="remitente_direccion" type="text" maxlength="255" autocomplete="street-address" value="<?= form_value($form, 'remitente_direccion') ?>" required<?= field_error_attributes($errors, 'remitente_direccion') ?>>
                                <?php if (isset($errors['remitente_direccion'])): ?><small id="remitente_direccion-error" class="field-error"><?= jongox_escape($errors['remitente_direccion']) ?></small><?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label for="remitente_telefono">Teléfono</label>
                                <input id="remitente_telefono" name="remitente_telefono" type="tel" maxlength="30" inputmode="tel" autocomplete="tel" value="<?= form_value($form, 'remitente_telefono') ?>" required data-phone<?= field_error_attributes($errors, 'remitente_telefono') ?>>
                                <?php if (isset($errors['remitente_telefono'])): ?><small id="remitente_telefono-error" class="field-error"><?= jongox_escape($errors['remitente_telefono']) ?></small><?php endif; ?>
                            </div>
                        </fieldset>

                        <fieldset class="form-panel">
                            <legend>
                                <span class="panel-number">02</span>
                                Datos del destinatario
                            </legend>
                            <p class="panel-intro">La persona o empresa que recibirá el paquete.</p>

                            <div class="field-group">
                                <label for="destinatario_nombre">Nombre completo</label>
                                <input id="destinatario_nombre" name="destinatario_nombre" type="text" maxlength="120" autocomplete="name" value="<?= form_value($form, 'destinatario_nombre') ?>" required<?= field_error_attributes($errors, 'destinatario_nombre') ?>>
                                <?php if (isset($errors['destinatario_nombre'])): ?><small id="destinatario_nombre-error" class="field-error"><?= jongox_escape($errors['destinatario_nombre']) ?></small><?php endif; ?>
                            </div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label for="destinatario_departamento">Departamento</label>
                                    <input id="destinatario_departamento" name="destinatario_departamento" type="text" maxlength="100" autocomplete="address-level1" value="<?= form_value($form, 'destinatario_departamento') ?>" required<?= field_error_attributes($errors, 'destinatario_departamento') ?>>
                                    <?php if (isset($errors['destinatario_departamento'])): ?><small id="destinatario_departamento-error" class="field-error"><?= jongox_escape($errors['destinatario_departamento']) ?></small><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="destinatario_ciudad">Ciudad</label>
                                    <input id="destinatario_ciudad" name="destinatario_ciudad" type="text" maxlength="100" autocomplete="address-level2" value="<?= form_value($form, 'destinatario_ciudad') ?>" required<?= field_error_attributes($errors, 'destinatario_ciudad') ?>>
                                    <?php if (isset($errors['destinatario_ciudad'])): ?><small id="destinatario_ciudad-error" class="field-error"><?= jongox_escape($errors['destinatario_ciudad']) ?></small><?php endif; ?>
                                </div>
                            </div>
                            <div class="field-group">
                                <label for="destinatario_direccion">Dirección</label>
                                <input id="destinatario_direccion" name="destinatario_direccion" type="text" maxlength="255" autocomplete="street-address" value="<?= form_value($form, 'destinatario_direccion') ?>" required<?= field_error_attributes($errors, 'destinatario_direccion') ?>>
                                <?php if (isset($errors['destinatario_direccion'])): ?><small id="destinatario_direccion-error" class="field-error"><?= jongox_escape($errors['destinatario_direccion']) ?></small><?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label for="destinatario_telefono">Teléfono</label>
                                <input id="destinatario_telefono" name="destinatario_telefono" type="tel" maxlength="30" inputmode="tel" autocomplete="tel" value="<?= form_value($form, 'destinatario_telefono') ?>" required data-phone<?= field_error_attributes($errors, 'destinatario_telefono') ?>>
                                <?php if (isset($errors['destinatario_telefono'])): ?><small id="destinatario_telefono-error" class="field-error"><?= jongox_escape($errors['destinatario_telefono']) ?></small><?php endif; ?>
                            </div>
                        </fieldset>
                    </div>

                    <fieldset class="form-panel shipment-panel">
                        <legend>
                            <span class="panel-number">03</span>
                            Detalle del envío
                        </legend>
                        <div class="shipment-panel-content">
                            <div class="field-group">
                                <label for="descripcion">Descripción del paquete</label>
                                <textarea id="descripcion" name="descripcion" rows="4" maxlength="1500" placeholder="Ejemplo: Caja con documentos, frágil; entregar en recepción." required<?= field_error_attributes($errors, 'descripcion') ?>><?= form_value($form, 'descripcion') ?></textarea>
                                <div class="field-meta">
                                    <small>Indica el contenido y cualquier instrucción útil para la entrega.</small>
                                    <small><span data-character-count>0</span>/1500</small>
                                </div>
                                <?php if (isset($errors['descripcion'])): ?><small id="descripcion-error" class="field-error"><?= jongox_escape($errors['descripcion']) ?></small><?php endif; ?>
                            </div>
                            <div class="submit-area">
                                <p>Al continuar, se creará una guía única para este envío.</p>
                                <button class="button button-primary submit-button" type="submit" <?= !$databaseReady ? 'disabled' : '' ?>>
                                    <span>Generar envío</span>
                                    <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </section>

        <section class="history-section" id="historial" aria-labelledby="history-title">
            <div class="container">
                <div class="history-heading">
                    <div>
                        <p class="eyebrow">SEGUIMIENTO</p>
                        <h2 id="history-title">Últimos envíos</h2>
                    </div>
                    <span class="history-count"><?= $totalEnvios ?> registrados</span>
                </div>

                <?php if (empty($envios)): ?>
                    <div class="empty-state">
                        <div class="empty-icon" aria-hidden="true">□</div>
                        <h3>Aún no hay envíos registrados</h3>
                        <p>Cuando generes el primero, aparecerá aquí con su información principal.</p>
                        <a class="text-link" href="#nuevo-envio">Registrar el primer envío <span aria-hidden="true">→</span></a>
                    </div>
                <?php else: ?>
                    <div class="table-wrap" role="region" aria-label="Historial de los últimos envíos" tabindex="0">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Guía</th>
                                    <th scope="col">Remitente</th>
                                    <th scope="col">Destinatario</th>
                                    <th scope="col">Descripción</th>
                                    <th scope="col">Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($envios as $envio): ?>
                                    <tr>
                                        <td data-label="Guía"><span class="tracking-code">JGX-<?= str_pad((string) $envio['id_envio'], 6, '0', STR_PAD_LEFT) ?></span></td>
                                        <td data-label="Remitente"><strong><?= jongox_escape($envio['remitente_nombre']) ?></strong><small><?= jongox_escape($envio['remitente_ciudad']) ?></small></td>
                                        <td data-label="Destinatario"><strong><?= jongox_escape($envio['destinatario_nombre']) ?></strong><small><?= jongox_escape($envio['destinatario_ciudad']) ?></small></td>
                                        <td data-label="Descripción" class="description-cell">
                                            <?php $descripcion = (string) $envio['descripcion']; ?>
                                            <?php if (jongox_string_length($descripcion) > 220): ?>
                                                <details class="description-details">
                                                    <summary><?= jongox_escape(history_excerpt($descripcion)) ?>… <span>Ver detalle</span></summary>
                                                    <p><?= jongox_escape($descripcion) ?></p>
                                                </details>
                                            <?php else: ?>
                                                <?= jongox_escape($descripcion) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Registro"><time datetime="<?= jongox_escape(date('c', strtotime($envio['fecha_creacion']))) ?>"><?= jongox_escape(date('d/m/Y H:i', strtotime($envio['fecha_creacion']))) ?></time></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand" href="#inicio" aria-label="Volver al inicio">
                <span class="brand-mark" aria-hidden="true">J</span>
                <span>Jongox <strong>Envíos</strong></span>
            </a>
            <p>Gestión logística simple, clara y segura.</p>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
