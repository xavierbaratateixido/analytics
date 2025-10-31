# Jincana de Halloween

Aplicación web para gestionar una jincana de 14 pruebas con temática de Halloween. Permite el acceso de participantes mediante autenticación, resolución de pruebas con pistas a través de códigos QR y un panel de administración con creación de pruebas y analíticas en tiempo real.

## Requisitos previos

### Firebase (recomendado)
1. Crea un proyecto en [Firebase Console](https://console.firebase.google.com/).
2. Habilita **Authentication** con el proveedor de correo/contraseña.
3. Habilita **Cloud Firestore** en modo de producción.
4. Habilita **Storage** y crea una carpeta `challenges/` (se genera automáticamente al subir la primera imagen).
5. Configura las reglas para permitir lecturas y escrituras autenticadas. Un ejemplo básico:
   ```
   rules_version = '2';
   service cloud.firestore {
     match /databases/{database}/documents {
       match /challenges/{document=**} {
         allow read: if request.auth != null;
         allow write: if request.auth != null && request.auth.token.email in ["maestro@halloween.com"]; 
       }
       match /players/{document=**} {
         allow read, write: if request.auth != null;
       }
     }
   }
   ```
   Ajusta la lista de correos autorizados para administración en las reglas y en `assets/js/admin.js` (`ALLOWED_ADMIN_EMAILS`).
6. Obtén la configuración web de Firebase (appId, apiKey, etc.) y reemplázala si fuese necesario en `assets/js/app.js` y `assets/js/admin.js`.

### Supabase (opcional)
Si prefieres Supabase:
1. Crea un proyecto en [Supabase](https://supabase.com/).
2. Habilita la autenticación por correo/contraseña.
3. Crea tablas equivalentes a `challenges` y `players`. Por ejemplo:
   ```sql
   create table challenges (
     id uuid primary key default gen_random_uuid(),
     title text not null,
     description text not null,
     announcement text not null,
     answer text not null,
     qr_code_value text not null,
     qr_hint text not null,
     image_url text,
     image_path text,
     created_at timestamptz default now(),
     updated_at timestamptz default now()
   );

   create table players (
     id uuid primary key default auth.uid(),
     name text,
     email text,
     score int default 0,
     completed_challenges text[] default array[]::text[],
     created_at timestamptz default now(),
     updated_at timestamptz default now()
   );
   ```
4. Crea políticas RLS que permitan lectura a usuarios autenticados y escritura solo a admins.
5. Sustituye las importaciones de Firebase por el SDK de Supabase en ambos archivos JS.

## Estructura

- `index.html`: Portal de participantes con login, listado de pruebas y escáner QR.
- `admin.html`: Panel de administración para crear pruebas y ver analíticas.
- `assets/css/style.css`: Estilos con temática de Halloween.
- `assets/js/app.js`: Lógica de participantes (autenticación, puntuaciones, escáner QR).
- `assets/js/admin.js`: Lógica del panel administrador (gestión de pruebas y métricas).

Cada participante recibe un conjunto aleatorio de hasta 14 pruebas. La selección queda registrada en su documento de `players`
en el campo `assignedChallenges`, de modo que aunque recargue la página siempre verá la misma combinación. Si necesitas reasignar
las pruebas de un equipo, edita o vacía manualmente ese array desde Firestore.

## Personalización

- Actualiza `ALLOWED_ADMIN_EMAILS` en `assets/js/admin.js` con los correos autorizados para administrar la jincana.
- Sustituye la imagen de relleno en `assets/js/app.js` por la que prefieras como fallback.
- Puedes añadir más campos a las pruebas editando tanto la interfaz como la estructura guardada en Firestore.

## Puesta en marcha

1. Sirve la carpeta del proyecto con cualquier servidor estático (por ejemplo `php -S localhost:8000` o `npx serve`).
2. Abre `index.html` para los participantes y `admin.html` para el panel de administración.
3. Crea las 14 pruebas desde el panel y comparte los accesos a los participantes.

## Escáner QR

La aplicación utiliza [html5-qrcode](https://github.com/mebjas/html5-qrcode) para leer pistas mediante la cámara del dispositivo. Asegúrate de que el navegador tenga permisos de cámara.
