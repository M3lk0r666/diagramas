# Guía de Despliegue a Producción
## Sistema de Gestión de Red — Laravel 12 / PHP 8.2

---

## HISTORIAL DE VERSIONES

| Versión | Fecha | Descripción |
|---|---|---|
| v1.0 | Deploy inicial | Inventario, topología, áreas, diagrama de puertos, vista Isométrica básica |
| v2.0 | Actualización | IVE, selector de cliente Isométrica, edición inline de puertos, iconos de dispositivos, landing page, fix de rutas |

---

## ⚡ ACTUALIZACIÓN v1 → v2 (servidor ya desplegado)

Si ya tienes el primer deploy corriendo, **este bloque es todo lo que necesitas ejecutar**.  
Si es instalación nueva, ve directamente a la [sección 1](#1-preparar-el-proyecto-localmente).

### Archivos nuevos / modificados en v2

| Tipo | Archivo / Ruta |
|---|---|
| Controlador nuevo | `app/Http/Controllers/Admin/SwitchPortController.php` |
| Controlador nuevo | `app/Http/Controllers/Admin/IsoIndexController.php` |
| Controlador nuevo | `app/Http/Controllers/Admin/IveController.php` |
| Vista nueva | `resources/views/admin/iso/index.blade.php` |
| Vista nueva | `resources/views/welcome.blade.php` (reescrita — landing page) |
| Vistas IVE | `resources/views/admin/ive/` (directorio completo) |
| Ruta modificada | `routes/admin.php` (rutas IVE, iso.index, ports.description) |
| Ruta modificada | `routes/web.php` (landing page, fix circular dashboard) |
| Config modificada | `config/fortify.php` (`home` → `/admin/dashboard`) |
| Script Python | `scripts/switch_diagram_generator.py` (iconos + tamaño) |
| Sidebar | `resources/views/layouts/includes/admin/sidebar.blade.php` |
| **Iconos PNG/SVG** | `scripts/icons/` (directorio completo — 30 archivos) |

### Pasos en el servidor

```bash
cd /var/www/diagramas

# 1. Bajar cambios
git pull origin main

# 2. Sin migraciones nuevas en v2 — omitir php artisan migrate

# 3. Limpiar caches (obligatorio por cambio en config/fortify.php y rutas)
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Re-cachear para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar worker (buena práctica tras cualquier deploy)
sudo systemctl restart diagramas-worker
```

### ⚠️ Verificar que los iconos llegaron al servidor

```bash
# Deben aparecer ~30 archivos PNG/SVG
ls -la /var/www/diagramas/scripts/icons/

# Archivos esperados (mínimo indispensable):
# access_switch.png/svg  backbone_switch.png/svg  core_switch.png/svg
# dist_switch.png/svg    stack_switch.png/svg
# acces_point.png  firewall.png  router.png  server_rack.png
# server_torre.png  modem.png  internet.png  network_cloud.png
# laptop.png  pc_desktop.png  ip_phone.png  printer.png
# security_camera.png  storage.png  vpn_conection.png
# load_balancer.png  wireless_controler.png  switch-24p.png  switch-48p.png
```

Si falta algún ícono (porque el directorio no estaba trackeado en el primer commit):

```bash
# En local — forzar track de todos los iconos
git add scripts/icons/
git commit -m "feat: add device icons for port diagram and PNG export"
git push origin main

# En servidor
git pull origin main
ls -la /var/www/diagramas/scripts/icons/  # Verificar
```

### ⚠️ Verificar permiso de escritura para Python en storage

El script `switch_diagram_generator.py` escribe PNGs en `storage/app/`:
```bash
sudo chown -R www-data:www-data /var/www/diagramas/storage/app
sudo chmod -R 775 /var/www/diagramas/storage/app
```

---

---

## 1. Preparar el proyecto localmente (antes de subir a GitHub)

### 1.1 Crear `.gitignore` correcto

El `.gitignore` ya excluye correctamente: `/vendor`, `/node_modules`, `/public/build`, `/public/storage`, `.env`, `/storage/*.key`.

Verificar que **no** está ignorado — agregar excepciones si hace falta:
```gitignore
# Al final del .gitignore
!storage/app/public/media/
!storage/app/public/media/*.svg
```

> **`scripts/icons/` NO está en `.gitignore`** — los iconos PNG/SVG se suben con git normalmente.  
> Si recién agregaste los iconos, asegúrate de hacer `git add scripts/icons/` explícitamente antes del primer commit.

### 1.2 Crear repositorio en GitHub
```bash
# En la raíz del proyecto (C:\xampp\htdocs\laravel\diagramas)
git init
git add .
git add scripts/icons/          # Forzar track de binarios PNG/SVG
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
git push -u origin main
```

### 1.3 Archivos a NO subir (ya en .gitignore)
- `.env` — se crea manualmente en el servidor
- `vendor/` — se instala con Composer en el servidor
- `node_modules/` — se instala con npm en el servidor
- `public/build/` — se compila en el servidor

---

## 2. Requisitos del servidor

### Sistema operativo recomendado
Ubuntu 22.04 LTS o Ubuntu 24.04 LTS

### Software requerido
| Software | Versión mínima | Uso |
|---|---|---|
| PHP | 8.2 | Runtime de Laravel |
| Composer | 2.x | Gestor de paquetes PHP |
| Node.js | 20 LTS | Compilar assets (Vite) |
| npm | 10.x | Paquetes JS |
| MySQL | 8.0 | Base de datos |
| Python | 3.9+ | Diagramas PNG (matplotlib, PIL) |
| pip | 3.x | Paquetes Python |
| Nginx o Apache | cualquiera | Web server |
| Git | 2.x | Clonar repo |

### Extensiones PHP requeridas
```
php8.2-cli php8.2-fpm php8.2-mysql php8.2-mbstring
php8.2-xml php8.2-curl php8.2-zip php8.2-gd
php8.2-bcmath php8.2-tokenizer php8.2-fileinfo
php8.2-intl php8.2-pdo
```

### Paquetes Python requeridos

```txt
matplotlib
networkx
Pillow
numpy
```

> Los scripts solo usan librerías estándar de Python además de estas (`re`, `json`, `os`, `sys` — ya incluidas).

---

## 3. Instalación del servidor (Ubuntu)

### 3.1 Actualizar sistema
```bash
sudo apt update && sudo apt upgrade -y
```

### 3.2 Instalar PHP 8.2
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
  php8.2-gd php8.2-bcmath php8.2-tokenizer php8.2-fileinfo \
  php8.2-intl php8.2-pdo

php -v  # Verificar: PHP 8.2.x
```

### 3.3 Instalar Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version  # Verificar: Composer 2.x
```

### 3.4 Instalar Node.js 20 LTS
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # Verificar: v20.x
npm -v    # Verificar: 10.x
```

### 3.5 Instalar MySQL 8
```bash
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql
sudo mysql_secure_installation

sudo mysql -u root -p
```
```sql
CREATE DATABASE diagramas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'diagramas_user'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURO';
GRANT ALL PRIVILEGES ON diagramas.* TO 'diagramas_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.6 Instalar Python y dependencias
```bash
sudo apt install -y python3 python3-pip python3-venv

pip3 install matplotlib networkx Pillow numpy

# Verificar
python3 -c "import matplotlib, networkx, PIL, numpy; print('OK')"

# Verificar que puede leer iconos (necesario para switch_diagram_generator.py)
python3 -c "from PIL import Image; img = Image.open('/var/www/diagramas/scripts/icons/access_switch.png'); print('Icono OK', img.size)"
```

### 3.7 Instalar Nginx
```bash
sudo apt install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

---

## 4. Clonar y configurar la aplicación

### 4.1 Clonar el repositorio
```bash
cd /var/www
sudo git clone https://github.com/TU_USUARIO/TU_REPO.git diagramas
sudo chown -R $USER:www-data /var/www/diagramas
cd /var/www/diagramas
```

### 4.2 Verificar que los iconos están presentes
```bash
ls scripts/icons/ | wc -l   # Debe mostrar ~30
```

Si el directorio está vacío o no existe:
```bash
# No se trackearon en el commit inicial — copiarlos manualmente o hacer:
# En local: git add scripts/icons/ → commit → push
# En servidor: git pull
```

### 4.3 Instalar dependencias PHP
```bash
composer install --no-dev --optimize-autoloader
```

### 4.4 Instalar dependencias JS y compilar assets
```bash
npm install
npm run build
# Genera public/build/ con los assets compilados por Vite
```

### 4.5 Crear y configurar el archivo `.env`
```bash
cp .env.example .env
nano .env
```

Configuración mínima para producción:
```ini
APP_NAME="Diagramas de Red"
APP_ENV=production
APP_KEY=                         # Se genera en el siguiente paso
APP_DEBUG=false
APP_URL=https://TU_DOMINIO.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=diagramas
DB_USERNAME=diagramas_user
DB_PASSWORD=TU_PASSWORD_SEGURO

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
```

### 4.6 Generar clave de aplicación
```bash
php artisan key:generate
```

### 4.7 Ejecutar migraciones
```bash
php artisan migrate --force
```

### 4.8 Crear el enlace simbólico de storage
```bash
php artisan storage:link
# Crea: public/storage → storage/app/public
```

### 4.9 Permisos de directorios
```bash
sudo chown -R www-data:www-data /var/www/diagramas/storage
sudo chown -R www-data:www-data /var/www/diagramas/bootstrap/cache
sudo chown -R www-data:www-data /var/www/diagramas/scripts
sudo chmod -R 775 /var/www/diagramas/storage
sudo chmod -R 775 /var/www/diagramas/bootstrap/cache
sudo chmod -R 755 /var/www/diagramas/scripts
```

> `scripts/` debe ser legible por `www-data` para que Laravel pueda ejecutar los scripts Python.

### 4.10 Optimizar Laravel para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 5. Configurar Nginx

```bash
sudo nano /etc/nginx/sites-available/diagramas
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name TU_DOMINIO.com www.TU_DOMINIO.com;

    root /var/www/diagramas/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/diagramas /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. SSL con Let's Encrypt (HTTPS)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d TU_DOMINIO.com -d www.TU_DOMINIO.com
sudo systemctl reload nginx

# Verificar renovación automática
sudo certbot renew --dry-run
```

---

## 7. Configurar Queue Worker

```bash
sudo nano /etc/systemd/system/diagramas-worker.service
```

```ini
[Unit]
Description=Diagramas Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/diagramas
ExecStart=/usr/bin/php /var/www/diagramas/artisan queue:work database \
    --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/www/diagramas/storage/logs/worker.log
StandardError=append:/var/www/diagramas/storage/logs/worker-error.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable diagramas-worker
sudo systemctl start diagramas-worker
sudo systemctl status diagramas-worker
```

---

## 8. Scheduler de Laravel (cron)

```bash
sudo crontab -u www-data -e
```

Agregar:
```cron
* * * * * cd /var/www/diagramas && php artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Actualizar la aplicación (deployments futuros)

### 9.1 Flujo estándar (sin cambios especiales)

```bash
# Local
git add .
git commit -m "descripción del cambio"
git push origin main
```

```bash
# Servidor
cd /var/www/diagramas

git pull origin main

# Solo si cambió composer.json
composer install --no-dev --optimize-autoloader

# Solo si cambió package.json o algún asset JS/CSS
npm install && npm run build

# Solo si hay migraciones nuevas
php artisan migrate --force

# Siempre — limpiar y re-cachear
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache

sudo systemctl restart diagramas-worker
```

### 9.2 Cuando se añadan iconos nuevos a `scripts/icons/`

Los archivos PNG/SVG en `scripts/icons/` se usan en dos lugares:
- **Diagrama de puertos (browser)** — servidos via `ConnectionController::serveIcon()` → ruta `admin.topology.icon`
- **Diagrama PNG (Python)** — leídos directamente con `PIL.Image.open()` en `switch_diagram_generator.py`

Si agregas iconos nuevos:
```bash
# Local
git add scripts/icons/nuevo_icono.png
git commit -m "feat: add nuevo_icono for port diagram"
git push origin main

# Servidor
git pull origin main
# No requiere cache clear — los archivos los lee Python directamente
```

### 9.3 Cuando se modifiquen scripts Python

```bash
# No requiere reiniciar servicios PHP ni limpiar cache de Laravel
# Solo hacer git pull en el servidor
# Los scripts se ejecutan como procesos independientes por cada request
git pull origin main
```

---

## 10. Inventario de archivos críticos en producción

Archivos que **no están en git** y deben gestionarse manualmente:

| Archivo / Directorio | Descripción | Acción |
|---|---|---|
| `.env` | Variables de entorno | Crear manualmente. Nunca en git. |
| `storage/app/` | Archivos subidos + PNGs generados | Persistir. No borrar en deployments. |
| `storage/logs/` | Logs de Laravel y worker | Rotar periódicamente. |
| `public/storage` | Symlink a `storage/app/public` | Recrear con `php artisan storage:link` si desaparece. |

Archivos que **sí están en git** y son críticos verificar en cada deploy:

| Archivo / Directorio | Descripción |
|---|---|
| `scripts/icons/` | 30 PNG/SVG — iconos de dispositivos y roles de switch |
| `scripts/switch_diagram_generator.py` | Generador de PNG por switch |
| `scripts/topology_generator.py` | Generador de topología global |
| `scripts/requirements.txt` | Dependencias Python |
| `config/fortify.php` | `home` debe apuntar a `/admin/dashboard` |
| `routes/web.php` | Ruta raíz → landing page (no redirect a /admin) |

---

## 11. Verificación final (instalación nueva o tras actualización mayor)

```bash
# App responde en la raíz — debe mostrar la landing page
curl -I http://TU_DOMINIO.com
# Esperar: HTTP 200 (no 302 → /admin → 404)

# Login funcional — debe redirigir a /admin/dashboard tras autenticarse
# Verificar manualmente en el navegador

# Logs de Laravel
tail -f /var/www/diagramas/storage/logs/laravel.log

# PHP-FPM
sudo systemctl status php8.2-fpm

# Worker activo
sudo systemctl status diagramas-worker

# Python y sus dependencias
python3 -c "import matplotlib, networkx, PIL, numpy; print('Python OK')"

# Iconos accesibles por Python
python3 -c "
from PIL import Image
import os
icons = os.listdir('/var/www/diagramas/scripts/icons/')
print(f'Iconos encontrados: {len(icons)}')
img = Image.open('/var/www/diagramas/scripts/icons/access_switch.png')
print(f'access_switch.png: {img.size}')
"
```

---

## Resumen de puertos y servicios

| Servicio | Puerto | Estado |
|---|---|---|
| Nginx | 80, 443 | Abierto al exterior |
| PHP-FPM | socket unix | Interno |
| MySQL | 3306 | Solo localhost |
| Queue Worker | — | Proceso systemd |

---

## Notas importantes

- **`APP_DEBUG=false`** obligatorio en producción
- **`config/fortify.php` `home`** debe ser `/admin/dashboard` — si queda en `'/'` el login/logout redirigiría a la landing, no al dashboard
- **Backups de DB**: configurar `mysqldump` periódico o usar servicio gestionado
- **`www-data` y Python**: el usuario `www-data` debe poder ejecutar `python3` y leer los iconos en `scripts/icons/`
- **Storage persistente**: `storage/app/` contiene archivos subidos y PNGs generados — nunca borrar en un reset de git
- **Firewall**: `sudo ufw allow 'Nginx Full'` y `sudo ufw enable`
- **Iconos SVG vs PNG**: los SVG (`.svg`) se usan en el sidebar y vistas blade; los PNG (`.png`) los consume Python para los diagramas exportables
