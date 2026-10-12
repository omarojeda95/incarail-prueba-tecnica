# Inca Rail · Plataforma de fútbol

Prueba técnica Senior Full Stack. Es una API en Laravel 13 (PHP 8.5) con Passport y roles y permisos, que
consume [football-data.org](https://www.football-data.org) y guarda una copia propia en PostgreSQL con Eloquent.
La acompaña una SPA en Vue 3 para iniciar sesión, consultar ligas, equipos y jugadores, y administrar usuarios,
roles y permisos.

- **Backend:** Laravel 13, PHP 8.5, Passport, spatie/laravel-permission, PostgreSQL 17, Redis 7.
- **Frontend:** Vue 3, TypeScript, Vue Router, Pinia, Vuetify, CASL.
- **Infraestructura:** Docker Compose con nginx, PHP-FPM, worker de colas, scheduler, PostgreSQL y Redis.
- **Calidad:** 96 pruebas de backend y 21 de frontend, Pint, ESLint y GitHub Actions.

## 1. Cómo levantarlo

Necesitas Docker con Compose v2 y un token gratuito de football-data.org
([registro](https://www.football-data.org/client/register)).

```bash
git clone <url-del-repositorio> incarail && cd incarail
cp .env.example .env          # pega tu token en FOOTBALL_DATA_TOKEN
docker compose up -d --build
```

La primera vez tarda unos minutos. Cuando `docker compose ps` muestre `app` como `healthy`:

- SPA: <http://localhost:8081>
- API: <http://localhost:8001> (salud en `/up`)

No hay pasos ocultos: al arrancar, el contenedor `app` corre las migraciones, genera las llaves de Passport y
carga los roles, permisos y usuarios de prueba (`php artisan app:instalar`, que se puede repetir sin problema).
Si los puertos están ocupados, cámbialos en `.env` (`SPA_PORT`, `API_PORT`).

```bash
docker compose logs -f app worker          # ver la instalación y las importaciones en cola
docker compose exec app php artisan test   # correr las pruebas
docker compose down                        # detener (con -v borra también los datos)
```

Sin token igual funciona el inicio de sesión, la administración y lo ya guardado; lo que necesita football-data
responde `503 football_data_sin_configurar`.

### Usuarios de prueba

Contraseña de todos: `password`.

| Usuario | Rol | Puede |
|---|---|---|
| `admin@incarail.test` | admin | Todo, incluida la administración de usuarios y roles |
| `analista@incarail.test` | analista | Ver ligas, equipos y jugadores, y forzar la actualización de una liga |
| `consulta@incarail.test` | consulta | Solo ver ligas, equipos y jugadores |

En la demo, el login tiene accesos rápidos a estos usuarios. Docker los activa con `MOSTRAR_USUARIOS_PRUEBA=true`;
un build sin esa variable no los muestra.

### Sin Docker

Necesitas PHP 8.5 (con `pdo_pgsql` o `pdo_sqlite`, `intl` y `bcmath`), Composer 2 y Node 24.

```bash
cd backend && composer install && cp .env.example .env   # token y datos de la base
# Sin PostgreSQL ni Redis: DB_CONNECTION=sqlite, CACHE_STORE=database, QUEUE_CONNECTION=database
php artisan key:generate && php artisan app:instalar
php artisan serve --port=8001          # y en otras terminales: queue:work y schedule:work

cd ../frontend && cp .env.example .env && npm ci && npm run dev   # http://localhost:5173
```

### Variables de entorno

`.env.example` (raíz) es lo que usa Docker; `backend/.env.example` trae la configuración completa de Laravel.
Ninguna tiene secretos.

| Variable | Por defecto | Para qué |
|---|---|---|
| `FOOTBALL_DATA_TOKEN` | — | Token de football-data. Solo en `.env`. |
| `FOOTBALL_DATA_MAX_REQUESTS_PER_MINUTE` | `10` | Peticiones por minuto hacia football-data. |
| `FOOTBALL_DATA_AVAILABLE_PLANS` | `TIER_ONE` | Planes que puede leer el token (el gratuito, solo `TIER_ONE`). |
| `FOOTBALL_NATIONAL_TEAM_COMPETITIONS` | `WC,EC` | Competiciones de selecciones. |
| `FOOTBALL_COMPETITIONS_TTL_MINUTES` | `1440` | Vigencia del catálogo de ligas. |
| `FOOTBALL_COMPETITION_DETAIL_TTL_MINUTES` | `720` | Vigencia de una liga importada. |
| `PASSPORT_TOKEN_TTL_MINUTES` | `480` | Duración del token de sesión. |

## 2. La API

Las cinco rutas del enunciado van sin el prefijo `/api`, tal como están escritas. Todas, salvo el login, piden
`Authorization: Bearer <token>`, un usuario activo y un permiso.

| Ruta | Permiso | Qué hace |
|---|---|---|
| `POST /auth/iniciar-sesion` | público (5 intentos por minuto) | Devuelve el token, el usuario y sus reglas CASL |
| `GET /auth/sesion` · `POST /auth/cerrar-sesion` | sesión | Recupera la sesión al recargar · revoca el token |
| `GET /competitions` | `ligas.ver` | Todas las ligas (filtros `buscar`, `area`, `disponibles`, `importadas`) |
| `GET /competitions/{competition_id}` | `ligas.ver` | Una liga por id (`2021`) o código (`PL`); al consultarla se guardan sus equipos y jugadores |
| `GET /team` · `GET /team/{team_id}` | `equipos.ver` | Equipos guardados (filtros `buscar`, `liga_id`, `tipo`) · un equipo con su plantel |
| `GET /players` | `jugadores.ver` | Jugadores guardados (filtros `buscar`, `equipo_id`, `posicion`, `nacionalidad`, orden) |
| `POST /competitions/{id}/importar` | `ligas.importar` | Encola una actualización y responde `202` |
| `/usuarios`, `/roles`, `/permisos` | `usuarios.*`, `roles.*` | Administración de usuarios, roles y permisos |

Para probarla sin escribir nada, importa en Postman
[`docs/incarail.postman_collection.json`](docs/incarail.postman_collection.json) y ejecuta «Iniciar sesión (admin)»:
guarda el token y las demás peticiones lo usan. Trae los cinco endpoints, la administración y un ejemplo de cada
error.

Todos los errores tienen la misma forma. `codigo` es para el programa y `mensaje` para la persona:

```json
{ "error": { "estado": 429, "codigo": "football_data_limite_alcanzado",
  "mensaje": "football-data.org permite 10 consultas por minuto y ya se usaron. La importación quedó en cola…",
  "detalles": { "reintentar_en": 42, "en_cola": true } } }
```

Los códigos que usa: 401 sin sesión o credenciales malas, 403 sin permiso o cuenta desactivada, 404 recurso
inexistente, 409 regla de negocio (por ejemplo, borrar un rol en uso), 422 datos inválidos, 429 límite de la API
o de football-data (con `Retry-After`), 502 error de football-data y 503 football-data caído o sin token.

## 3. El límite de 10 peticiones por minuto

La idea es que la API responda desde su propia base y que football-data solo la alimente. Así el límite casi no
se nota y lo guardado se sigue viendo aunque football-data esté caído o sin cupo.

![Decisión del límite de peticiones](docs/limite-peticiones.png)

- `/team` y `/players` nunca llaman afuera. Una liga importada vale 12 horas y el catálogo 24; mientras tanto se
  responden desde PostgreSQL.
- Importar una liga cuesta una sola petición: `/competitions/{id}/teams` trae la liga, sus equipos y sus planteles.
- Antes de cada petición, `ClienteFootballData` pide un cupo a un contador en Redis
  (`PresupuestoVentanaDeslizante`). Está en Redis porque la API, el worker y el scheduler son procesos distintos y
  comparten el mismo cupo; un lock evita que dos lo tomen a la vez (y si otro proceso lo retiene demasiado, se trata
  como falta de cupo, no como un error). Cuenta las peticiones de los últimos 60
  segundos y no por minuto de reloj, porque así no pueden salir 10 a las 12:00:59 y otras 10 a las 12:01:00.
- Si football-data avisa que no queda cupo (por sus cabeceras o con un 429), el contador se bloquea hasta la hora
  que ellos indican.
- Sin cupo no se pierde nada: la importación queda en la cola (un job por liga) y el worker la hace cuando hay
  lugar. Mientras tanto se responde lo guardado, marcado como desactualizado, o un 429; la SPA muestra una cuenta
  regresiva y reintenta sola.
- Las ligas que no están en el plan gratuito se rechazan sin gastar una petición.
- El scheduler actualiza el catálogo una vez al día y, cada hora, las ligas vencidas.

Lo probé con una prueba que abre 11 ligas seguidas: salen exactamente 10 peticiones y la 11.ª responde 429 y
queda en cola. Descarté solo cachear las respuestas (no frena una ráfaga de ligas distintas), poner `sleep()`
entre llamadas (el usuario esperaría hasta un minuto) e importar todo con un cron (gasta cupo en ligas que nadie
pidió, y el enunciado dice que la liga se guarda al consultarla).

## 4. Cómo lo resolví

### Arquitectura

Usé arquitectura hexagonal solo en el borde con football-data, que es lo que de verdad cambia y falla. Comparé
tres opciones:

- **Capas simples de Laravel:** menos código, pero la integración y el límite quedaban mezclados con los
  controladores y eran difíciles de probar.
- **Clean Architecture completa:** entidades, repositorios y mapeos propios para algo que es casi solo lectura;
  mucho código que no aporta.
- **Hexagonal en el borde (la elegida):** el caso de uso depende de un puerto (`ProveedorDatosFutbol`).
  `ClienteFootballData` lo implementa con HTTP y, en las pruebas, `ProveedorFutbolEnMemoria` lo reemplaza sin
  tocar el caso de uso. Encolar una importación también pasa por un puerto (`ColaDeImportaciones`), así que el
  dominio no conoce Laravel. No puse repositorios delante de Eloquent porque ya es la abstracción de la base.

El código está en `app/Dominio` (puerto, DTOs, excepciones y evento), `app/Aplicacion` (casos de uso) y
`app/Infraestructura` (el cliente de football-data, el contador y la cola). `ImportarLiga` solo orquesta: pide los
datos, guarda todo en una transacción con `GuardarPlanteles` y publica el evento. Los controladores tienen una a tres líneas: la
validación va en Form Requests, la salida en Resources y todos los errores pasan por `RenderizadorErroresApi`.

Los comandos (`ImportarLiga`, gestión de usuarios y roles) están separados de las consultas (`DetalleLiga`,
`ConsultasEquipos`…), sin bases separadas: las consultas no tienen efectos y solo los comandos gastan cupo
(CQRS ligero). Al importar una liga se emite el evento `LigaImportada`, que dos listeners usan para guardar la
auditoría y limpiar una caché; agregar otra reacción no toca el caso de uso.

### Datos

- Las claves primarias son los ids de football-data y cada importación es un `upsert` por id dentro de una
  transacción. Consultar la misma liga dos veces no duplica nada, y si algo falla no queda una liga a medias.
- `ligas` guarda competiciones (ligas y copas). Un equipo puede jugar varias, por eso existe `liga_equipo`.
- Clubes y selecciones van en la misma tabla `equipos`, porque football-data los describe igual; los distingue la
  columna `tipo` (`club` o `seleccion`), que la base restringe a esos dos valores.
- Un jugador puede estar en el plantel de su club y de su selección (Luis Díaz está en el Bayern y en Colombia).
  Por eso el plantel es la tabla `equipo_jugador`, la única relación entre equipos y jugadores. El club de un
  jugador es el equipo de tipo `club` de su plantel.
- Una liga trae los planteles completos, así que al importarla sale del plantel quien ya no está (un traspaso o un
  préstamo). Las copas solo agregan, porque football-data manda sus planteles incompletos.
- La nacionalidad es un texto del proveedor, no una relación: Quiñones es colombiano y juega por México.
- No hay SQL escrito a mano en las consultas. La única excepción es el índice único sobre `lower(correo)` en una
  migración.

Elegí PostgreSQL por la búsqueda: la SPA busca por partes del nombre y eso solo se acelera con índices trigram
(`pg_trgm`), que MySQL no tiene. Lo medí a mano con `EXPLAIN ANALYZE` sobre datos sintéticos (150.000 jugadores):
la búsqueda bajó de unos 140 ms a 7 ms, y el detalle de una liga de 650 a 9 ms tras indexar las claves foráneas.
Las pruebas de la base verifican que esos índices existan. Los textos del proveedor van en `text`, porque
una URL de más de 255 caracteres hacía fallar la importación.

Lo propio está en español (tablas, clases, permisos, mensajes). En inglés queda lo que no depende de mí: las
rutas del enunciado, lo que define el framework o un paquete, y los datos tal como llegan del proveedor.

### Autenticación y permisos

- El login verifica la contraseña con bcrypt y Passport emite un token personal que dura 8 horas; el logout lo
  revoca. No usé el *password grant* porque obliga a dejar un `client_secret` en la SPA. Si el correo no existe o
  la contraseña está mal, la respuesta y el tiempo son los mismos, y hay un máximo de 5 intentos por minuto.
- Los permisos se definen en `config/acl.php`, cada uno con su equivalente CASL, y cada ruta exige el suyo. Ocultar
  un botón en la SPA es solo comodidad: quien decide es el servidor.
- Al desactivar un usuario se revocan sus tokens y pierde el acceso en la siguiente petición.
- No se puede desactivar a uno mismo, quitar al último administrador ni borrar un rol con usuarios (409).

### Frontend

Vue 3 con Composition API y TypeScript, Pinia para la sesión, Vuetify y CASL. Las reglas que entrega la API arman
el menú, protegen las rutas y muestran u ocultan botones; al recargar se piden de nuevo, así que un cambio de rol
se refleja sin volver a entrar. Un interceptor de Axios trata todos los errores: con un 401 vuelve al login con un
mensaje, y sin permiso muestra una página propia. Hay esqueletos de carga, estados vacíos, búsqueda con espera,
filtros, paginación, la cuenta regresiva del límite y avisos de datos desactualizados, con componentes
reutilizables como `EstadoAsincrono` y `AlertaError`.

## 5. Diagramas

Están en [`docs/`](docs/) como PNG, y todos juntos en [`docs/diagramas.drawio`](docs/diagramas.drawio), un
archivo de draw.io con una pestaña por diagrama. El del límite está en la sección 3.

### Arquitectura

![Arquitectura](docs/arquitectura.png)

La SPA llega por nginx a la API. Dentro de la API están Passport y los permisos, los casos de uso y el cliente de
football-data. PostgreSQL guarda los datos; Redis, la caché, la cola y el contador de peticiones. La flecha naranja
es el camino del límite: pedir cupo y recién entonces llamar a football-data.

### Importación de una liga

![Importación de una liga](docs/flujo-importacion.png)

`GET /competitions/PL` de punta a punta: permisos, consulta local, cupo, llamada a football-data, guardado en una
transacción y evento de auditoría. Si no hay cupo, el job queda en la cola.

### Autenticación y permisos

![Autenticación y permisos](docs/autenticacion-acl.png)

Login con Passport, entrega de las reglas CASL, cómo las usa la SPA y cómo la API sigue decidiendo hasta el
cierre de sesión, que revoca el token.

### Modelo de datos

![Modelo de datos](docs/modelo-datos.png)

Modelo físico sacado de PostgreSQL, con columnas, tipos y claves. Arriba las tablas de fútbol; abajo usuarios,
roles, permisos y tokens. `model_has_roles.model_id` no tiene clave foránea porque spatie la hace polimórfica, y
el paquete borra esos roles junto con el usuario. No dibujé las tablas propias del framework (`cache`, `jobs`,
`migrations`, el resto de `oauth_*`).

## 6. Pruebas e integración continua

```bash
docker compose exec app php artisan test     # backend
cd frontend && npm test                      # frontend
```

Hay pruebas unitarias del contador y del cliente de football-data; de integración de la API con la base,
incluidos los permisos de cada rol en cada ruta; de aceptación con flujos completos (el analista importa una liga
y sigue trabajando con football-data caído; el admin crea un usuario que solo ve lo permitido); y de reglas de la
base. En el frontend hay pruebas del guardia del router (sesión y permisos), de los componentes de error y de
estados de carga, de las reglas CASL y del manejo de respuestas fuera de orden. Corren en SQLite y, en la CI, también en PostgreSQL. Las llamadas a football-data se simulan, así que no
gastan tu cupo.

Varias piezas se hicieron con TDD: en el historial, los commits `test: … (red)` van antes de sus
`feat:`/`fix: … (green)`. También los errores que encontré al probar el sistema se corrigieron primero con una
prueba que fallaba.

La CI (`.github/workflows/ci.yml`) corre Pint y las pruebas del backend en SQLite y PostgreSQL, el lint, las
pruebas y el build del frontend, y construye las imágenes de Docker.

## 7. Supuestos

- Consultar una liga la importa para cualquier rol con `ligas.ver`, porque así lo pide el enunciado. El permiso
  `ligas.importar` sirve para forzar una actualización aunque los datos estén vigentes.
- El número de camiseta no siempre viene en el plan gratuito; si no viene queda vacío. Pedirlo jugador por jugador
  rompería el límite.
- En la Champions casi todos los equipos llegan sin plantel. Se guarda lo que llega, la SPA lo avisa y los
  jugadores aparecen al importar la liga nacional de cada club.
- Al importar el Mundial o la Eurocopa, el jugador se suma al plantel de su selección sin tocar el de su club.
  Si llega primero por su selección, no tiene club conocido hasta que se importa la liga de su club.
- football-data dice en qué plantel juega cada jugador, no de quién es su contrato: un jugador a préstamo aparece
  en el club donde juega.
- A veces un jugador viene en dos planteles de la misma liga; se guarda una vez y queda en los dos.
- Solo las ligas del plan gratuito se pueden importar; las demás se listan deshabilitadas.

## 8. Mejoras que haría

Lo obligatorio del enunciado está hecho y probado. Para llevarlo a producción agregaría:

- Pruebas end-to-end del navegador con Playwright.
- Refresh tokens en una cookie `httpOnly`, en vez de guardar el token en `localStorage`.
- Avisar a la SPA cuando termina una importación en cola (WebSockets con Laravel Reverb).
- Saber de qué club es el contrato de cada jugador, con `/persons/{id}` en segundo plano y el mismo límite.
- Métricas del contador de peticiones y de la cola (Horizon o Pulse).
- Una imagen de producción sin dependencias de desarrollo, con HTTPS y los secretos fuera de `.env`.

## 9. Estructura

```
backend/        API Laravel
  app/Dominio, app/Aplicacion, app/Infraestructura    capas de la arquitectura
  app/Http                                            controladores, requests, resources, middleware
  config/acl.php, config/futbol.php                   permisos y configuración de football-data
  database/, tests/                                   migraciones y seeders · pruebas
frontend/       SPA Vue 3
docs/           diagramas (PNG), diagramas.drawio y la colección de Postman
docker/         imágenes de PHP-FPM y nginx
compose.yml     stack completo
```
