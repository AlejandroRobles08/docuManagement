#!/bin/bash
set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

echo "=========================================="
echo " DocuManagement - Gestor de entorno local"
echo "=========================================="
echo "1) Levantar con Docker (docker compose up)"
echo "2) Levantar con Laragon"
echo "3) Bajar contenedores (docker compose down)"
echo "=========================================="
read -rp "Elige una opción [1-3]: " opcion

case "$opcion" in
    1)
        echo ""
        echo "Levantando contenedores con Docker..."
        docker compose up -d --build
        echo ""
        echo "Listo. Servicios disponibles en:"
        echo "  App:        http://localhost"
        echo "  Vite (HMR): http://localhost:5174"
        echo "  phpMyAdmin: http://localhost:8081  (usuario: root, sin contraseña)"
        echo "  MySQL:      127.0.0.1:3307"
        ;;
    2)
        echo ""
        echo "Deteniendo contenedores Docker (si están activos) para liberar el puerto 80..."
        docker compose down 2>/dev/null || true

        # Elimina el marcador de Vite dev-server de Docker para que Laravel
        # vuelva a servir los assets compilados (public/build) bajo Laragon.
        rm -f "$PROJECT_DIR/public/hot"

        # public/storage puede haber quedado apuntando a la ruta de Docker
        # (/var/www/html/...), que no existe en Windows: se recrea para que
        # apunte a la ruta real del proyecto y las imágenes/PDFs vuelvan a
        # cargar bajo Laragon.
        php artisan storage:link --force

        LARAGON_EXE="/c/laragon/laragon.exe"
        if [ -f "$LARAGON_EXE" ]; then
            echo "Iniciando Laragon..."
            "$LARAGON_EXE" &
            disown
            echo "Laragon se está abriendo. Dentro de Laragon, presiona 'Start All' para iniciar Apache y MySQL."
            echo "La app quedará disponible en: http://documanagement.test"
        else
            echo "No se encontró Laragon en $LARAGON_EXE."
            echo "Abre Laragon manualmente y presiona 'Start All'."
        fi
        ;;
    3)
        echo ""
        echo "Bajando contenedores..."
        docker compose down
        echo "Contenedores detenidos."
        ;;
    *)
        echo "Opción no válida. Usa 1, 2 o 3."
        exit 1
        ;;
esac
