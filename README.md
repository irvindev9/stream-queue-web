# Servidor de Cola para Stream Downloader (PHP + SQLite)

Este es un backend de cola de mensajes ultra-ligero diseñado para correr en servidores de bajos recursos (como droplets de 1GB RAM) usando la instalación existente de Nginx + PHP + SQLite.

## Estructura de Archivos
* `index.php`: Interfaz web interactiva con diseño premium (modo oscuro, responsive, actualizaciones en tiempo real vía AJAX).
* `api.php`: API para agregar enlaces, consumirlos desde la máquina local y actualizar su estado.
* `queue.sqlite`: Base de datos SQLite (se genera automáticamente).

---

## 1. Despliegue en el Servidor (Droplet)

1. Sube los archivos `index.php` y `api.php` a la carpeta de tu servidor web, por ejemplo:
   ```bash
   /var/www/html/cola/
   ```
2. Asegúrate de que el servidor web (habitualmente el usuario `www-data` en Ubuntu) tenga permisos de escritura en la carpeta donde residen los scripts, ya que SQLite necesita crear y modificar el archivo `queue.sqlite` y sus archivos temporales:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/cola
   sudo chmod -R 775 /var/www/html/cola
   ```

---

## 2. Configurar Autenticación Básica en Nginx

Para asegurar que solo tú y tu cliente puedan acceder a la cola, configuramos Basic Auth a nivel de Nginx. Esto consume 0 recursos del CPU/PHP para peticiones no autorizadas.

1. **Instalar utilidad de contraseñas** (si no la tienes):
   ```bash
   sudo apt-get install apache2-utils
   ```
2. **Crear archivo de contraseñas** (cambia `usuario_cliente` por tu nombre de usuario deseado):
   ```bash
   sudo htpasswd -c /etc/nginx/.htpasswd_cola usuario_cliente
   ```
   *Te pedirá ingresar y confirmar la contraseña.*

3. **Configurar en el bloque de Nginx**:
   Edita el archivo de configuración de tu sitio (ej. `/etc/nginx/sites-available/default` o donde tengas configurado el sitio):
   ```nginx
   location /cola {
       auth_basic "Acceso Restringido - Grabador de Streams";
       auth_basic_user_file /etc/nginx/.htpasswd_cola;
       
       # Configuración estándar para pasar a PHP-FPM
       try_files $uri $uri/ =404;
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php-fpm.sock; # Asegura que coincida con tu versión de PHP-FPM (ej: php8.1-fpm.sock o php7.4-fpm.sock)
       }
   }
   ```
4. **Validar y reiniciar Nginx**:
   ```bash
   sudo nginx -t
   sudo systemctl restart nginx
   ```

---

## 3. Endpoints de la API

Dado que el sitio está protegido por Basic Auth, todas las peticiones desde el grabador local deben incluir la cabecera `Authorization` con las credenciales cifradas en Base64.

### A. Obtener el siguiente stream para grabar
* **Método**: `GET`
* **URL**: `https://tu-servidor.com/cola/api.php?action=next`
* **Acción**: Busca de forma atómica el trabajo más antiguo que esté `pending`, cambia su estado a `recording` y lo devuelve.
* **Respuesta si hay trabajo**:
  ```json
  {
    "success": true,
    "job": {
      "id": 1,
      "url": "https://url-del-stream.com",
      "status": "recording",
      "created_at": "2026-06-26 22:20:00",
      "updated_at": "2026-06-26 22:21:00"
    }
  }
  ```
* **Respuesta si no hay trabajo**:
  ```json
  {
    "success": true,
    "job": null,
    "message": "No pending jobs in queue"
  }
  ```

### B. Actualizar el estado del trabajo
Una vez tu script local termine de grabar (con éxito o error), debe actualizar el estado del trabajo.
* **Método**: `POST`
* **URL**: `https://tu-servidor.com/cola/api.php?action=update`
* **Payload (JSON)**:
  ```json
  {
    "id": 1,
    "status": "completed" 
  }
  ```
  *(O `"status": "failed"` con `"error_message": "Error al codificar ffmpeg"`)*

---

## 4. Ejemplo de script integrador en Node.js (Cliente Local)

Puedes usar un script similar a este en tu computadora local para sincronizarte con el servidor de cola remoto:

```javascript
import fetch from 'node-fetch';

const SERVER_URL = 'https://tu-servidor.com/cola/api.php';
const USERNAME = 'usuario_cliente';
const PASSWORD = 'contrasena_ingresada';

const headers = {
  'Content-Type': 'application/json',
  'Authorization': 'Basic ' + Buffer.from(`${USERNAME}:${PASSWORD}`).toString('base64')
};

async function processQueue() {
  try {
    // 1. Obtener siguiente URL de la cola
    const res = await fetch(`${SERVER_URL}?action=next`, { headers });
    const data = await res.json();
    
    if (data.success && data.job) {
      const { id, url } = data.job;
      console.log(`[Grabador Local] Iniciando descarga de: ${url}`);
      
      try {
        // --- AQUÍ SE CORRERÍA PUPPETEER / GRABACIÓN LOCAL ---
        // await recordStream(url); 
        
        // 2. Reportar éxito
        await fetch(`${SERVER_URL}?action=update`, {
          method: 'POST',
          headers,
          body: JSON.stringify({ id, status: 'completed' })
        });
        console.log(`[Grabador Local] Grabación #${id} completada exitosamente`);
      } catch (err) {
        // 2. Reportar error
        await fetch(`${SERVER_URL}?action=update`, {
          method: 'POST',
          headers,
          body: JSON.stringify({ id, status: 'failed', error_message: err.message })
        });
        console.log(`[Grabador Local] Grabación #${id} fallida: ${err.message}`);
      }
    }
  } catch (err) {
    console.error('Error al consultar la cola remota:', err);
  }
}

// Polling continuo cada 15 segundos si no hay descargas activas
setInterval(processQueue, 15000);
```
