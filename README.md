# Jongox Envíos

Aplicación PHP + MySQL para registrar envíos. En un único formulario solicita los datos del remitente, del destinatario y la descripción del paquete; al guardarlo genera una guía basada en el identificador del envío y lo muestra en el historial.

## Puesta en marcha

1. Sube el contenido de este proyecto a la carpeta web de tu hosting con PHP.
2. Crea config.php a partir de config.example.php, o configura las variables de entorno JONGOX_DB_HOST, JONGOX_DB_NAME, JONGOX_DB_USER y JONGOX_DB_PASSWORD.
3. Abre index.php en el navegador. En la primera carga, la aplicación crea las tablas automáticamente si todavía no existen.

El servidor debe tener PHP 7.4 o superior, la extensión pdo_mysql habilitada y permisos para crear tablas dentro de la base de datos configurada.

config.php se excluye deliberadamente de Git y del ZIP de distribución. Si despliegas desde un repositorio o con el flujo FTP de GitHub, súbelo de manera segura después del despliegue, o crea config.php en el servidor a partir de config.example.php. Si falta, la aplicación mostrará una indicación de configuración en vez de un error fatal.

Para probar de forma local con PHP instalado:

    php -S localhost:8000

Luego visita http://localhost:8000.

## Tablas creadas

El sistema normaliza los nombres de columnas para que las relaciones sean consistentes:

| Tabla | Campos principales |
| --- | --- |
| remitente | id_remitente, nombre, departamento, ciudad, direccion, telefono |
| destinatario | id_destinatario, nombre, departamento, ciudad, direccion, telefono |
| envio | id_envio, id_remitente, id_destinatario, descripcion, fecha_creacion |

envio tiene claves foráneas hacia remitente y destinatario. Todas las tablas usan InnoDB y utf8mb4, por lo que los datos con tildes y ñ se almacenan correctamente.

## Seguridad

- Las inserciones usan consultas preparadas y una transacción: si falla una parte, no queda un remitente o destinatario aislado.
- El formulario valida los campos en el navegador y en el servidor.
- Los datos del historial se escapan antes de mostrarse y el formulario incluye protección CSRF.
- config.php está en .gitignore; no lo subas a un repositorio público. config.example.php sirve como plantilla sin credenciales.

## Diseño

La interfaz es responsive y no depende de bibliotecas externas. La ilustración original de logística está en assets/images/jongox-logistica-hero.png.
