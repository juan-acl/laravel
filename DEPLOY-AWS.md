# Despliegue en AWS Elastic Beanstalk

La aplicación es Laravel 13 y requiere PHP 8.3 o posterior. No necesita una base de datos para las preguntas: están en el código. Las sesiones y el caché usan archivos, por lo que la configuración inicial está pensada para una sola instancia de aplicación.

## 1. Preparar el código

En la raíz del proyecto:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

`.ebignore` evita enviar dependencias locales, sesiones, bases SQLite de desarrollo, archivos de configuración local y credenciales. Elastic Beanstalk instalará las dependencias PHP de producción con Composer.

Antes de desplegar, ejecuta las comprobaciones locales:

```powershell
composer ci:check
npm run build
```

Instala y configura AWS CLI y EB CLI con un perfil de AWS autorizado. Después, desde la raíz del proyecto:

```powershell
eb init
eb create
eb deploy
```

Durante `eb init` selecciona tu región y la plataforma PHP 8.3 o posterior en Amazon Linux 2023. Crea un entorno web con balanceador de aplicación para poder configurar HTTPS. `eb create` puede generar recursos facturables; revisa las opciones y costos en AWS antes de confirmarlo.

## 2. Crear el entorno

En la consola de AWS:

1. Crea una aplicación de Elastic Beanstalk con plataforma **PHP en Amazon Linux 2023** y PHP 8.3 o superior.
2. Usa un entorno **Web server** con balanceador de carga y mantén el número de instancias en una mientras la sesión use almacenamiento local.
3. Sube el paquete del paso anterior. `.ebextensions/01-laravel.config` fija `/public` como raíz web, instala Composer sin dependencias de desarrollo y configura el endpoint `/up` para el chequeo de salud.
4. Configura HTTPS en el listener 443 del balanceador con un certificado de AWS Certificate Manager. Redirige HTTP a HTTPS antes de compartir el sitio. La configuración activa cookies seguras, así que la ruleta necesita HTTPS para conservar su sesión.

## 3. Configurar variables privadas

En **Configuration → Updates, monitoring, and logging → Environment properties**, establece:

- `APP_KEY`: genera una clave con `php artisan key:generate --show` en tu equipo y guárdala como secreto en AWS. No la escribas en `.ebextensions`, `.env.example`, GitHub ni en el paquete.
- `APP_URL`: URL HTTPS pública del entorno o dominio, por ejemplo `https://ruleta.ejemplo.com`.

Limita quién puede ver o modificar la configuración del entorno en IAM. Conserva el mismo `APP_KEY` entre despliegues; cambiarlo invalida las sesiones existentes.

## 4. Verificar

Cuando el entorno indique estado **Healthy**:

1. Abre `https://<dominio>/up`; debe responder correctamente.
2. Abre la página raíz, inicia una partida, gira, responde, confirma el puntaje y reinicia.
3. Revisa los logs de Elastic Beanstalk si el entorno no queda saludable. Mantén `APP_DEBUG=false` en producción.

## Consideraciones de sesiones y costos

- La aplicación no requiere RDS: el banco de preguntas está en el código y no se consulta una base de datos.
- La sesión de Laravel guarda la partida en archivos y el marcador vive en el navegador. Mantén una instancia para evitar que solicitudes de una misma partida lleguen a servidores con sesiones distintas. Si necesitas escalar o mantener partidas durante reemplazos de instancias, migra las sesiones a un almacén compartido (por ejemplo Redis o DynamoDB) antes de aumentar la capacidad.
- Un balanceador, instancias, transferencia de datos, registros y certificados/dominios pueden generar cargos según región y uso. Revisa el estimador de AWS, configura alertas de presupuesto y elimina el entorno cuando ya no lo uses.
- No se ha creado ningún recurso en AWS desde este proyecto. El despliegue requiere acceso a tu cuenta, región, dominio/certificado y una decisión explícita de publicar.
