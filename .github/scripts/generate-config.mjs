import { readFileSync, writeFileSync } from 'node:fs';

const requiredSecrets = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
const missingSecrets = requiredSecrets.filter((name) => !process.env[name]);

if (missingSecrets.length > 0) {
  console.error('Faltan secretos requeridos: ' + missingSecrets.join(', '));
  process.exit(1);
}

const phpString = (value) => {
  return "'" + value
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'")
    .replace(/\r/g, '\\r')
    .replace(/\n/g, '\\n') + "'";
};

const replacements = [
  ["'mysql-servidor.ejemplo'", phpString(process.env.DB_HOST)],
  ["'jongox_envios'", phpString(process.env.DB_NAME)],
  ["'tu_usuario'", phpString(process.env.DB_USER)],
  ["'tu_clave'", phpString(process.env.DB_PASSWORD)],
];

let config = readFileSync('config.example.php', 'utf8');

for (const [placeholder, value] of replacements) {
  const position = config.indexOf(placeholder);
  if (position === -1) {
    throw new Error('No se encontró el marcador de configuración: ' + placeholder);
  }

  config = config.slice(0, position) + value + config.slice(position + placeholder.length);
}

writeFileSync('config.php', config, { encoding: 'utf8', mode: 0o600 });
console.log('config.php se generó en el runner y se incluirá en el despliegue.');
