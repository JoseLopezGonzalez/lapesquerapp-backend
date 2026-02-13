# Instalar y arrancar Docker en WSL (Ubuntu)

Sigue estos pasos **en tu terminal WSL**. Te pedirá la contraseña de `sudo` cuando haga falta.

---

## 1. Instalar Docker

```bash
sudo apt-get update
sudo apt-get install -y docker.io
```

---

## 2. Arrancar el servicio Docker (cada vez que abras WSL, si no está configurado para iniciarse solo)

```bash
sudo service docker start
```

**Comprobar que está en marcha:**

```bash
docker ps
```

Debe mostrar una tabla (aunque esté vacía), sin error "Cannot connect to the Docker daemon".

---

## 3. (Opcional) Usar Docker sin `sudo`

Para no tener que escribir `sudo` cada vez:

```bash
sudo usermod -aG docker $USER
```

Cierra la terminal WSL y vuelve a abrirla (o ejecuta `newgrp docker`). Después podrás usar `docker` y `./vendor/bin/sail` sin sudo.

---

## 4. (Opcional) Que Docker se inicie solo al abrir WSL

Crea o edita `~/.bashrc` y añade al final:

```bash
# Iniciar Docker al entrar en WSL (si está instalado)
if service docker status 2>/dev/null | grep -q "is not running"; then
  sudo service docker start >/dev/null 2>&1
fi
```

Así, al abrir una nueva terminal, Docker se pondrá en marcha si estaba parado.

---

## Resumen rápido (copiar y pegar)

```bash
# Instalar
sudo apt-get update && sudo apt-get install -y docker.io

# Arrancar ahora
sudo service docker start

# Comprobar
docker ps
```

Cuando `docker ps` funcione, **instala Docker Compose** (Sail lo necesita):

```bash
sudo bash instalar-docker-compose.sh
```

(Ese script instala el plugin `docker-compose` y crea el comando `docker-compose` que usa Sail.)

Luego ejecuta el deploy:

```bash
./deploy-dev.sh
```

---

## Si Sail dice "Docker is not running" pero ya arrancaste el servicio

Sail comprueba con `docker info`. Si ese comando falla por **permisos** (no por que el servicio esté parado), verás "Docker is not running".

1. Prueba: `docker info` (o `sudo docker info`).
2. Si solo funciona con `sudo`, añade tu usuario al grupo docker y recarga el grupo:
   ```bash
   sudo usermod -aG docker $USER
   newgrp docker
   ```
3. Vuelve a ejecutar Sail (sin sudo).

Si usas **Docker Desktop** en Windows (WSL2), no uses `sudo service docker start` en WSL: arranca Docker Desktop en Windows y activa la integración WSL en su configuración.

---

## Si Sail dice "docker-compose: command not found"

Instala Docker Compose y el wrapper para Sail:

```bash
sudo apt-get update && sudo apt-get install -y docker-compose-plugin
printf '%s\n' '#!/bin/sh' 'exec docker compose "$@"' | sudo tee /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

O desde la raíz del proyecto: `sudo bash instalar-docker-compose.sh`
