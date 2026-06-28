# Guía de Despliegue a Producción
## Sistema de Gestión de Red — Laravel 12 / PHP 8.2

---

## 1. Preparar el proyecto localmente (antes de subir a GitHub)

### 1.1 Crear `.gitignore` correcto
El `.gitignore` ya excluye correctamente: `/vendor`, `/node_modules`, `/public/build`, `/public/storage`, `.env`, `/storage/*.key`.

Verificar que **no** está ignorado:
- `storage/app/public/media/` (íconos SVG de los switches) — agregar excepción si hace falta:
```
# Al final del .gitignore
!storage/app/public/media/
!storage/app/public/media/*.svg
```

### 1.2 Crear repositorio en GitHub
```bash
# En la raíz del proyecto (C:\xampp\htdocs\laravel\diagramas)
git init
git add .
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
| PHP Extensions | ver abajo | Dependencias de Laravel |
| Composer | 2.x | Gestor de paquetes PHP |
| Node.js | 20 LTS | Compilar assets (Vite) |
| npm | 10.x | Paquetes JS |
| MySQL | 8.0 | Base de datos |
| Python | 3.9+ | Scripts de diagramas |
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

# Asegurar instalación
sudo mysql_secure_installation
# Responder: contraseña root, remover usuarios anónimos, deshabilitar login remoto root, etc.

# Crear base de datos y usuario
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

# Instalar librerías de los scripts
pip3 install matplotlib networkx Pillow numpy
# Verificar:
python3 -c "import matplotlib, networkx, PIL, numpy; print('OK')"
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

### 4.2 Instalar dependencias PHP
```bash
composer install --no-dev --optimize-autoloader
```

### 4.3 Instalar dependencias JS y compilar assets
```bash
npm install
npm run build
# Esto genera public/build/ con los assets compilados por Vite
```

### 4.4 Crear y configurar el archivo `.env`
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

### 4.5 Generar clave de aplicación
```bash
php artisan key:generate
```

### 4.6 Ejecutar migraciones
```bash
php artisan migrate --force
```

### 4.7 Crear el enlace simbólico de storage
```bash
php artisan storage:link
# Crea: public/storage → storage/app/public
```

### 4.8 Permisos de directorios
```bash
sudo chown -R www-data:www-data /var/www/diagramas/storage
sudo chown -R www-data:www-data /var/www/diagramas/bootstrap/cache
sudo chmod -R 775 /var/www/diagramas/storage
sudo chmod -R 775 /var/www/diagramas/bootstrap/cache
```

### 4.9 Optimizar Laravel para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 5. Configurar Nginx

Crear el archivo de configuración:
```bash
sudo nano /etc/nginx/sites-available/diagramas
```

Contenido:
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

Activar el sitio:
```bash
sudo ln -s /etc/nginx/sites-available/diagramas /etc/nginx/sites-enabled/
sudo nginx -t          # Verificar configuración
sudo systemctl reload nginx
```

---

## 6. SSL con Let's Encrypt (HTTPS)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d TU_DOMINIO.com -d www.TU_DOMINIO.com
# Seguir el asistente: ingresar email, aceptar términos, elegir redirigir HTTP→HTTPS
sudo systemctl reload nginx
```

Renovación automática (ya configurada por certbot, verificar):
```bash
sudo certbot renew --dry-run
```

---

## 7. Configurar Queue Worker (procesamiento de áreas)

La app usa `QUEUE_CONNECTION=database`. Crear un servicio systemd para el worker:

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

Cada vez que hagas cambios locales y quieras actualizar producción:

```bash
# Local: subir cambios
git add .
git commit -m "descripción del cambio"
git push origin main
```

```bash
# Servidor: aplicar cambios
cd /var/www/diagramas

# 1. Bajar cambios
git pull origin main

# 2. Actualizar dependencias (solo si cambió composer.json o package.json)
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Migraciones nuevas (si las hay)
php artisan migrate --force

# 4. Limpiar y re-cachear
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache

# 5. Reiniciar worker
sudo systemctl restart diagramas-worker
```

---

## 10. Verificación final

```bash
# Comprobar que la app responde
curl -I http://TU_DOMINIO.com

# Comprobar logs si algo falla
tail -f /var/www/diagramas/storage/logs/laravel.log

# Comprobar que PHP-FPM está corriendo
sudo systemctl status php8.2-fpm

# Comprobar que el worker está activo
sudo systemctl status diagramas-worker

# Probar Python manualmente
cd /var/www/diagramas
python3 scripts/topology_generator.py --help
```

---

## Resumen de puertos y servicios

| Servicio | Puerto | Estado |
|---|---|---|
| Nginx | 80, 443 | Abierto |
| PHP-FPM | socket unix | Interno |
| MySQL | 3306 | Solo localhost |
| Queue Worker | — | Proceso systemd |

---

## Notas importantes

- **`APP_DEBUG=false`** obligatorio en producción — nunca exponer errores al usuario
- **Backups de DB**: configurar `mysqldump` periódico o usar un servicio gestionado
- **Permisos de Python**: el usuario `www-data` debe poder ejecutar `python3` y los scripts en `/var/www/diagramas/scripts/`
- **Storage persistente**: el directorio `storage/app/public/` contiene archivos subidos por usuarios — excluirlo de resets de git
- **Firewall**: `sudo ufw allow 'Nginx Full'` y `sudo ufw enable`

