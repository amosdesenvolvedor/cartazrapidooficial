#!/usr/bin/env bash

# Start Cartaz Rápido stack
set -euo pipefail

PROJECT_DIR="/home/corporativo/Documentos/cartazRapido"

cd "$PROJECT_DIR"

# Pull latest images (if tags are used) and start in detached mode
docker compose pull || true
docker compose up -d
