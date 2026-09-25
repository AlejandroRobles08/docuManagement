#!/bin/bash
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f .env ]; then
    echo "[entrypoint] No existe .env, copiando desde .env.example..."
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Generando APP_KEY..."
    php artisan key:generate --force --ansi
fi

echo "[entrypoint] Esperando a que MySQL (${DB_HOST}:${DB_PORT}) esté disponible..."
until php -r "
try {
    new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    exit(0);
} catch (\Throwable \$e) {
    exit(1);
}
" >/dev/null 2>&1; do
    sleep 2
done
echo "[entrypoint] MySQL disponible."

php artisan migrate --force

# --force en vez de solo comprobar que exista: si el proyecto se corrió
# antes fuera de Docker (p. ej. con Laragon en Windows), public/storage
# puede existir pero apuntar a una ruta de Windows que no sirve de nada
# dentro del contenedor.
php artisan storage:link --force

echo "[entrypoint] Ajustando permisos de storage/ y bootstrap/cache/..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
