# Despliegue — Z Informática en Hostinger (columbiatcc.online)

| Dato | Valor |
|---|---|
| URL | https://zinformatica.columbiatcc.online |
| IP VPS | 179.199.138.253 |
| Proyecto Compose | `zinformatica` (no cambiarlo nunca: separa volúmenes) |
| Rutas en el VPS | `/opt/zinformatica/src` (código) · `/opt/zinformatica/config` (secretos) |

## Arquitectura (un solo subdominio, ruteo por ruta)

```
Internet ─443─> Traefik (compartido)
   ├─ /api/*, /storage/*  -> api    (Laravel 10, PHP 8.3, nginx+FPM :8080)
   ├─ /admin/*            -> admin  (CoreUI Angular 20, nginx :80)
   └─ /*                  -> tienda (Angular 17 SPA, nginx :80)
api ──red interna──> db (PostgreSQL 16, volumen db_data)
```
Ventajas: un solo registro DNS y un solo certificado, y sin CORS (mismo origen).

## 0. Comprobaciones previas (en el VPS)
```bash
docker --version && docker compose version
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
docker inspect <CONTENEDOR_TRAEFIK> --format '{{.HostConfig.NetworkMode}}'
dig +short A zinformatica.columbiatcc.online     # debe dar 179.199.138.253
dig +short AAAA zinformatica.columbiatcc.online  # idealmente vacío
```
- Si NetworkMode = `host` → usar el compose tal cual (Caso A).
- Si es una red bridge → agregar al servicio api, admin y tienda la red del proxy (ver sección 9 de la guía) y la etiqueta `traefik.docker.network=<RED>`.
- Confirmar con el administrador entrypoint (`websecure`) y resolvedor (`letsencrypt`).

## 1. Subir el código
```bash
# desde tu PC
scp -P <PUERTO_SSH> ecommerce-despliegue.zip <USUARIO>@179.199.138.253:/tmp/
# en el VPS
unzip -l /tmp/ecommerce-despliegue.zip | head
sudo mkdir -p /opt/zinformatica/config
sudo unzip /tmp/ecommerce-despliegue.zip -d /opt/zinformatica/tmp
sudo mv /opt/zinformatica/tmp/ecommerce /opt/zinformatica/src && sudo rmdir /opt/zinformatica/tmp
```

## 2. Secretos
```bash
cd /opt/zinformatica
sudo cp src/deploy/db.env.example  config/db.env
sudo cp src/deploy/api.env.example config/api.env
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # pegar en api.env
echo "JWT_SECRET=$(openssl rand -base64 48)"       # pegar en api.env
openssl rand -base64 24                            # contraseña de BD (db.env y api.env)
sudo nano config/db.env && sudo nano config/api.env
sudo chmod 700 config && sudo chmod 600 config/*.env
```
APP_KEY y JWT_SECRET se generan **una sola vez**; cambiarlos invalida sesiones y datos cifrados.

## 3. Construir y arrancar
```bash
cd /opt/zinformatica/src
docker compose -p zinformatica -f docker-compose.produccion.yml config -q
docker compose -p zinformatica -f docker-compose.produccion.yml build
docker compose -p zinformatica -f docker-compose.produccion.yml up -d
docker compose -p zinformatica -f docker-compose.produccion.yml ps
docker compose -p zinformatica -f docker-compose.produccion.yml logs --tail=100 api
```

## 4. Base de datos (solo la primera vez)
Opción A — esquema limpio con catálogos y usuario admin:
```bash
docker compose -p zinformatica -f docker-compose.produccion.yml exec api php artisan migrate --force
docker compose -p zinformatica -f docker-compose.produccion.yml exec api php artisan db:seed --force
```
Opción B — importar tu respaldo local (`pg_dump -Fc`):
```bash
docker compose -p zinformatica -f docker-compose.produccion.yml cp respaldo.dump db:/tmp/r.dump
docker compose -p zinformatica -f docker-compose.produccion.yml exec db \
  pg_restore -U zinformatica -d zinformatica --no-owner /tmp/r.dump
```
Imágenes ya subidas en local (`storage/app/public/*`):
```bash
docker compose -p zinformatica -f docker-compose.produccion.yml cp ./public/. api:/var/www/html/storage/app/public/
docker compose -p zinformatica -f docker-compose.produccion.yml exec -u root api chown -R www-data:www-data /var/www/html/storage/app
```

## 5. Verificación
```bash
curl -sS -D - -o /dev/null http://zinformatica.columbiatcc.online/      # 30x a https
curl -sS -D - -o /dev/null https://zinformatica.columbiatcc.online/     # 200 tienda
curl -sS https://zinformatica.columbiatcc.online/api/tienda/home | head -c 300
curl -sS -D - -o /dev/null https://zinformatica.columbiatcc.online/admin/
curl -sS -o /dev/null -w '%{http_code}\n' https://zinformatica.columbiatcc.online/.env  # no debe mostrar secretos
```
En el navegador: certificado válido, login admin, ver productos con imagen, subir una imagen de prueba, registro con correo, login con Google, chat IA.

Google Cloud Console → Credenciales → tu OAuth Client → *Orígenes JavaScript autorizados*: agregar `https://zinformatica.columbiatcc.online`.

## 6. Actualizar
```bash
# respaldo antes de cambiar nada
docker compose -p zinformatica -f docker-compose.produccion.yml exec db \
  pg_dump -U zinformatica -Fc zinformatica > /opt/zinformatica/respaldo_$(date +%F).dump
# nuevo código en src/, luego:
docker compose -p zinformatica -f docker-compose.produccion.yml build
docker compose -p zinformatica -f docker-compose.produccion.yml up -d
docker compose -p zinformatica -f docker-compose.produccion.yml exec api php artisan migrate --force   # si hay migraciones nuevas
```
Prohibido: `down -v`, `docker volume prune`, `docker system prune`. Copiar los respaldos fuera del VPS.

Volúmenes persistentes: `zinformatica_db_data` (BD) y `zinformatica_api_storage` (imágenes, avatares, comprobantes).

## Problemas frecuentes
- 404 de Traefik: labels/Host/entrypoint; ver `docker ps` y logs del proxy.
- 502: el contenedor no levantó (`logs api`) o la topología de red no es la asumida.
- Imágenes con `http://`: revisar APP_URL y que TrustProxies esté aplicado.
- Error 500 en API: `logs api` (LOG_CHANNEL=stderr). No activar APP_DEBUG en producción.
