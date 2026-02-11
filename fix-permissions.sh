#!/bin/bash
# Script para arreglar permisos de Symfony en Docker

set -e

PROJECT_DIR="symfony"

echo "🔧 Arreglando permisos de Symfony..."

# En el host - cambiar propietario de TODO el proyecto a tu usuario
sudo chown -R "$USER":"$USER" "$PROJECT_DIR"

# Dar permisos de escritura a propietario en todos los archivos
chmod -R u+w "$PROJECT_DIR"

# Permisos especiales para cache y logs de Symfony
if [ -d "$PROJECT_DIR/var" ]; then
	chmod -R 775 "$PROJECT_DIR/var"
fi

echo "✅ Permisos arreglados correctamente"
echo ""
echo "Propietario: $USER:$USER"
echo "Todos los archivos tienen permisos de escritura para el propietario"
