# Inca Rail · Plataforma de fútbol (prueba técnica Senior Full Stack)

API REST en **Laravel 13 / PHP 8.5** con **Passport** y control de acceso por roles y permisos, que consume
[football-data.org](https://www.football-data.org) y guarda una copia propia con **Eloquent** (PostgreSQL),
más una **SPA en Vue 3** (TypeScript, Vue Router, Pinia, Vuetify y CASL) para iniciar sesión, consultar ligas,
equipos y jugadores, y administrar usuarios, roles y permisos.

| | |
|---|---|
| **Backend** | Laravel 13.35 · PHP 8.5 · Passport 13 · spatie/laravel-permission 8 · PostgreSQL 17 · Redis 7 |
| **Frontend** | Vue 3.5 · TypeScript · Vue Router · Pinia · Vuetify 4 · CASL · Vite |
| **Infra** | Docker Compose: nginx + PHP-FPM, worker de colas, scheduler, PostgreSQL y Redis (Linux/Alpine) |
| **Calidad** | 78 pruebas PHPUnit (unitarias, integración y aceptación), 10 de Vitest, Pint, ESLint/Oxlint, Prettier, GitHub Actions |

---

## Índice

1. [Puesta en marcha (Docker)](#1-puesta-en-marcha-docker)
2. [Usuarios de prueba](#2-usuarios-de-prueba)
3. [Puesta en marcha sin Docker](#3-puesta-en-marcha-sin-docker)
4. [Variables de entorno](#4-variables-de-entorno)
5. [La API](#5-la-api)
6. [El límite de 10 peticiones por minuto](#6-el-límite-de-10-peticiones-por-minuto)
7. [Cómo abordé la solución y por qué](#7-cómo-abordé-la-solución-y-por-qué)
8. [Diagramas](#8-diagramas)
9. [Frontend](#9-frontend)
10. [Pruebas, TDD e integración continua](#10-pruebas-tdd-e-integración-continua)
11. [Supuestos](#11-supuestos)
12. [Pendiente y cómo lo haría](#12-pendiente-y-cómo-lo-haría)
13. [Estructura del repositorio](#13-estructura-del-repositorio)

---

## 1. Puesta en marcha (Docker)

**Requisitos:** Docker 24+ con Docker Compose v2, ~3 GB libres en disco y un token gratuito de
football-data.org ([registro](https://www.football-data.org/client/register)).

```bash
git clone <url-del-repositorio> incarail && cd incarail

cp .env.example .env
# Edita .env y pega tu token:  FOOTBALL_DATA_TOKEN=tu_token

docker compose up -d --build
```

La primera vez tarda unos minutos (instala dependencias y compila la SPA). Cuando `docker compose ps`
muestre `app` como `healthy`:

| Qué | Dónde |
|---|---|
| **SPA** | <http://localhost:8081> |
| **API** | <http://localhost:8001> (p. ej. `POST /auth/login`, `GET /competitions`) |
| Salud de la API | <http://localhost:8001/up> |

No hay pasos ocultos: el contenedor `app` ejecuta `php artisan app:install`, un comando idempotente que corre
las migraciones, genera las llaves de Passport, crea el cliente de tokens personales y siembra roles,
permisos y usuarios de prueba. La `APP_KEY` se genera una vez y se guarda en el volumen de `storage`.

Si los puertos 8081 u 8001 están ocupados, cámbialos en `.env` (`SPA_PORT`, `API_PORT`).

```bash
docker compose logs -f app worker        # ver la instalación y las importaciones en cola
docker compose exec app php artisan test # correr las pruebas dentro del contenedor
docker compose down                      # detener (añade -v para borrar también los datos)
```

> Sin token la plataforma igual arranca: el login, la administración y los datos ya guardados funcionan, y
> las pantallas que necesitan football-data muestran el error `503 football_data_not_configured`.

## 2. Usuarios de prueba

Contraseña de todos: **`password`** (la pantalla de login tiene accesos rápidos).

| Usuario | Rol | Puede |
|---|---|---|
| `admin@incarail.test` | `admin` | Todo: ligas, equipos, jugadores, usuarios, roles y permisos |
| `analista@incarail.test` | `analista` | Ver ligas, equipos y jugadores; forzar la importación de ligas en segundo plano |
| `consulta@incarail.test` | `consulta` | Solo consulta: ver ligas, equipos y jugadores |

## 3. Puesta en marcha sin Docker

Requisitos: PHP 8.5 (extensiones `pdo_pgsql` o `pdo_sqlite`, `intl`, `bcmath`), Composer 2, Node 24 y,
opcionalmente, PostgreSQL y Redis.

```bash
# API
cd backend
composer install
cp .env.example .env            # pon FOOTBALL_DATA_TOKEN y tus credenciales de base de datos
#   Sin PostgreSQL/Redis: DB_CONNECTION=sqlite (y borra los demás DB_*),
#   CACHE_STORE=database y QUEUE_CONNECTION=database; luego: touch database/database.sqlite
php artisan key:generate
php artisan app:install          # migraciones + llaves Passport + seeders
php artisan serve --port=8001    # API en http://localhost:8001
php artisan queue:work           # en otra terminal: importaciones en segundo plano
php artisan schedule:work        # opcional: refresco programado

# SPA
cd ../frontend
cp .env.example .env             # VITE_API_BASE_URL=http://localhost:8001
npm ci
npm run dev                      # http://localhost:5173
```

## 4. Variables de entorno

Hay dos plantillas, ninguna con secretos:

- **`.env.example`** (raíz) — lo lee `compose.yml`: `FOOTBALL_DATA_TOKEN`, `SPA_PORT`, `API_PORT`.
- **`backend/.env.example`** — configuración completa de Laravel para correrlo sin Docker.

| Variable | Por defecto | Para qué |
|---|---|---|
| `FOOTBALL_DATA_TOKEN` | — | Token personal de football-data.org. Solo en `.env`, nunca en el código ni en el repositorio. |
| `FOOTBALL_DATA_MAX_REQUESTS_PER_MINUTE` | `10` | Presupuesto de peticiones salientes por minuto. |
| `FOOTBALL_DATA_AVAILABLE_PLANS` | `TIER_ONE` | Planes de football-data que el token puede leer (el gratuito solo `TIER_ONE`). |
| `FOOTBALL_COMPETITIONS_TTL_MINUTES` | `1440` | Cada cuánto se considera vencido el catálogo de ligas. |
| `FOOTBALL_COMPETITION_DETAIL_TTL_MINUTES` | `720` | Cada cuánto se considera vencida una liga importada. |
| `PASSPORT_TOKEN_TTL_MINUTES` | `480` | Vida del token de acceso emitido en el login. |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173,http://localhost:8081` | Orígenes que pueden llamar a la API desde el navegador. |

## 5. La API

Las rutas no llevan prefijo `/api`, para respetar **exactamente** las del enunciado (`/competitions`, `/team`,
`/players`). Todas, salvo el login, exigen `Authorization: Bearer <token>`, un usuario activo y un permiso.

| Método | Ruta | Permiso | Descripción |
|---|---|---|---|
| `POST` | `/auth/login` | público (5 intentos/min) | Correo + contraseña → token, usuario y reglas CASL |
| `GET` | `/auth/me` | sesión | Usuario y reglas CASL (para restaurar la sesión al recargar) |
| `POST` | `/auth/logout` | sesión | Revoca el token |
| `GET` | **`/competitions`** | `competitions.read` | Todas las ligas. Filtros: `search`, `area`, `available`, `imported` |
| `GET` | **`/competitions/{competition_id}`** | `competitions.read` | Detalle de una liga (por id `2021` o código `PL`). **Al consultarla se guardan sus equipos y jugadores** |
| `GET` | **`/team`** | `teams.read` | Equipos guardados, paginados. Filtros: `search`, `competition_id`, `per_page` |
| `GET` | **`/team/{team_id}`** | `teams.read` | Detalle de un equipo con su plantel y competiciones |
| `GET` | **`/players`** | `players.read` | Jugadores guardados, paginados. Filtros: `search`, `team_id`, `position`, `nationality`, `sort`, `direction` |
| `GET` | `/players/{player_id}` | `players.read` | Detalle de un jugador (extra) |
| `POST` | `/competitions/{competition_id}/import` | `competitions.import` | Encola una actualización en segundo plano → `202` (extra) |
| `GET/POST` | `/users` | `users.read` / `users.create` | Listar (con `search`) y crear usuarios |
| `GET/PUT` | `/users/{id}` | `users.read` / `users.update` | Ver y editar (nombre, correo, contraseña, roles) |
| `PATCH` | `/users/{id}/deactivate` · `/activate` | `users.deactivate` | Desactivar (revoca sus tokens al instante) y reactivar |
| `GET/POST` | `/roles` | `roles.read` / `roles.manage` | Listar y crear roles |
| `PUT/DELETE` | `/roles/{id}` | `roles.manage` | Editar permisos de un rol, eliminarlo |
| `GET` | `/permissions` | `roles.read` | Catálogo de permisos con su equivalente CASL |

Respuesta del login (las `abilities` se usan tal cual con CASL):

```json
{
  "token_type": "Bearer",
  "access_token": "eyJ0eXAiOiJKV1Qi...",
  "expires_at": "2026-10-10T14:00:00+00:00",
  "user": { "id": 3, "name": "Carla Consulta", "email": "consulta@incarail.test", "is_active": true, "roles": ["consulta"] },
  "abilities": [
    { "action": "read", "subject": "Competition" },
    { "action": "read", "subject": "Player" },
    { "action": "read", "subject": "Team" }
  ]
}
```

### Errores: un solo formato

Todos los errores salen con la misma forma y el código HTTP que corresponde. `code` es estable (para
programas) y `message` está en español (para personas):

```json
{ "error": { "status": 429, "code": "football_data_rate_limited",
  "message": "football-data.org permite 10 consultas por minuto y ya se usaron. La importación quedó en cola y estará lista en unos 42 segundos.",
  "details": { "retry_after": 42, "queued": true } } }
```

| HTTP | `code` | Cuándo |
|---|---|---|
| 401 | `unauthenticated` · `invalid_credentials` | Sin token, token vencido o revocado · credenciales incorrectas |
| 403 | `forbidden` · `account_disabled` · `competition_not_in_plan` | Falta el permiso · usuario desactivado · liga fuera del plan gratuito |
| 404 | `not_found` · `competition_not_found` | Equipo, jugador, usuario o rol inexistente («El equipo solicitado no existe.») · liga inexistente en football-data |
| 409 | `conflict` | Regla de negocio: desactivarse a sí mismo, dejar el sistema sin administradores, borrar un rol en uso |
| 422 | `validation_failed` | Datos inválidos; `details.fields` trae los mensajes por campo |
| 429 | `too_many_requests` · `football_data_rate_limited` | Límite de nuestra API (120/min por usuario) · sin cupo hacia football-data (con `Retry-After`) |
| 502 | `football_data_error` | football-data respondió con un error inesperado |
| 503 | `football_data_unavailable` · `football_data_not_configured` | Sin conexión con football-data (timeout, DNS…) · falta el token |

Ejemplo rápido con `curl`:

```bash
TOKEN=$(curl -s -X POST localhost:8001/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"analista@incarail.test","password":"password"}' | jq -r .access_token)

curl -s localhost:8001/competitions?available=1 -H "Authorization: Bearer $TOKEN" | jq '.data[].code'
curl -s localhost:8001/competitions/PL           -H "Authorization: Bearer $TOKEN" | jq '.data.teams | length'
curl -s "localhost:8001/players?position=Goalkeeper" -H "Authorization: Bearer $TOKEN" | jq '.meta.total'
curl -s localhost:8001/team/999999               -H "Authorization: Bearer $TOKEN"   # 404 con formato uniforme
```

## 6. El límite de 10 peticiones por minuto

**La idea central: nuestra base de datos es la fuente de las respuestas; football-data solo la alimenta.**
Una vez guardada, una liga se responde sin llamar afuera, y cada llamada externa pasa por un presupuesto
compartido que nunca deja salir más de 10 peticiones en ningún intervalo de 60 segundos.

![Decisión del límite de peticiones](docs/limite-peticiones.png)

Lo que hace cada pieza:

1. **Datos locales primero (read-through).** `GET /team`, `/team/{id}` y `/players` *nunca* llaman a
   football-data. `GET /competitions` y `GET /competitions/{id}` responden desde PostgreSQL si los datos están
   frescos (catálogo: 24 h; liga: 12 h). Solo si faltan o vencieron se intenta refrescarlos.
2. **Importar una liga cuesta 1 petición.** `/v4/competitions/{id}/teams` trae la competición, sus equipos y
   el plantel de cada uno. Diez clics en diez ligas distintas consumen el minuto; volver a abrirlas no cuesta
   nada.
3. **Presupuesto compartido con ventana deslizante** (`SlidingWindowRequestBudget`). Antes de cada petición el
   adaptador pide un cupo. El registro de las últimas peticiones vive en **Redis** bajo un **lock atómico**,
   así los procesos web, el worker y el scheduler gastan el mismo presupuesto y no hay carreras. Elegí ventana
   deslizante y no fija porque una ventana fija permite 20 peticiones en 2 segundos alrededor del cambio de
   minuto (10 a las 00:59 y 10 a las 01:00).
4. **football-data manda.** Tras cada respuesta se leen `X-Requests-Available-Minute` y
   `X-RequestCounter-Reset`. Si dicen 0, o si llega un `429`, el presupuesto se bloquea hasta el reset que
   ellos indican. Así nos corregimos aunque otro cliente use el mismo token.
5. **Sin cupo, nada se pierde.** Si no hay cupo, la importación se encola (`ImportCompetitionJob`, único por
   liga para no duplicar). El job usa la misma lógica; si sigue sin cupo hace `release(retryAfter)` y vuelve a
   la cola justo cuando se libera un lugar. Mientras tanto:
   - si ya había una copia (aunque vencida) se responde **200 con `meta.stale: true`**;
   - si no había nada, **429** con `Retry-After` y `queued: true`. La SPA muestra la cuenta regresiva y
     **reintenta sola**; para entonces el worker suele haber terminado y la respuesta sale de la base.
6. **No gastar cupo en lo imposible.** El catálogo trae el `plan` de cada liga; las que no son `TIER_ONE`
   (fuera del plan gratuito) se rechazan con 403 sin llamar a football-data, y la SPA las muestra
   deshabilitadas.
7. **Caída de football-data.** Timeout o error de red → se sirve la copia local si existe (`stale`); si no,
   `503` (o `502` si respondió con error). Lo ya guardado siempre se responde, con o sin football-data y con o
   sin token.
8. **En segundo plano.** El scheduler sincroniza el catálogo una vez al día y encola cada hora el refresco de
   las ligas vencidas. Los jobs se autorregulan con el mismo presupuesto.

**Lo probé así:** `FootballEndpointsTest::the_eleventh_import_in_a_minute_is_not_sent_and_gets_queued_with_429`
abre 11 ligas seguidas y verifica que salen exactamente 10 peticiones, que la 11.ª responde 429 con
`Retry-After` y que su importación queda en cola. Otras pruebas cubren la ventana deslizante
(`SlidingWindowRequestBudgetTest`), la lectura de cabeceras y el `429` del proveedor
(`FootballDataClientTest`), el `release` del job (`ImportCompetitionJobTest`) y el servicio con
football-data caído (`AnalystExploresALeagueTest`).

**Alternativas que descarté:**
- *Solo caché HTTP de las respuestas:* evita repetir llamadas, pero no controla ráfagas de ligas distintas ni
  sirve datos cuando la caché expira y el proveedor no está.
- *`sleep()` entre llamadas dentro del request:* bloquea workers de PHP-FPM y el usuario espera hasta un minuto.
- *Importar todo por adelantado con un cron:* el plan gratuito tiene 12 ligas y ~20 equipos por liga; sería
  viable, pero contradice «al consultarla se guardan» y gasta cupo en ligas que nadie pidió. Lo dejé como
  refresco de lo ya importado.
- *Middleware `RateLimited` de Laravel para jobs:* sirve para los jobs pero no para las llamadas síncronas;
  con un solo presupuesto propio ambos caminos comparten la misma regla.

## 7. Cómo abordé la solución y por qué

### Arquitectura: hexagonal solo donde paga (O2 · O5)

| Opción | A favor | En contra para este proyecto |
|---|---|---|
| **Capas simples de Laravel** (controlador → modelo) | Mínimo código, idiomático | La integración con football-data y el límite se mezclarían con controladores y modelos; difícil de probar sin HTTP |
| **Clean Architecture completa** | Dominio totalmente independiente | Entidades propias + repositorios + mapeos para un CRUD de lectura: mucho código que no aporta |
| **Hexagonal (puertos y adaptadores) pragmática** ✅ | Aísla lo que realmente varía y falla (el proveedor externo); se prueba con un adaptador en memoria | Requiere disciplina para no sobrediseñar el resto |

Elegí **hexagonal aplicada solo a la frontera que lo justifica**:

- **Dominio** (`app/Domain/Football`): el **puerto** `FootballDataProvider`, DTOs inmutables
  (`CompetitionData`, `TeamData`, `PlayerData`, `CompetitionSquads`), excepciones de dominio y el evento
  `CompetitionImported`. No conoce HTTP, Laravel HTTP ni el formato JSON de football-data.
- **Infraestructura** (`app/Infrastructure/FootballData`): el **adaptador** `FootballDataClient`, la única clase
  que habla HTTP con football-data (mapea el JSON a DTOs y los errores HTTP a excepciones de dominio), y el
  `SlidingWindowRequestBudget`.
- **Aplicación** (`app/Application`): casos de uso. En las pruebas el puerto se reemplaza por
  `InMemoryFootballDataProvider` (un segundo adaptador), sin tocar el código de aplicación.
- **Eloquent sin repositorios.** Los modelos son el modelo de persistencia y de lectura. Ponerles un repositorio
  delante no aislaría nada valioso: Eloquent ya es la abstracción de la base y el enunciado pide usarlo.
  Por eso elegí hexagonal y no Clean Architecture «de libro».

### CQRS ligero (O3)

- **Comandos** (escriben): `ImportCompetition`, `SyncCompetitionCatalog`, `UserManagement`, `RoleManagement`.
- **Consultas** (solo leen): `CompetitionCatalog`, `CompetitionDetail`, `TeamQueries`, `PlayerQueries`.
  Devuelven el dato y su procedencia (`QueryResult`: `source`, `stale`, `synced_at`), que la API expone en
  `meta`.

No separé bases de lectura y escritura: con este volumen sería sobrediseño. La separación ya aporta: las
consultas no tienen efectos y los comandos son los únicos que gastan cupo de football-data.

### Eventos y colas (O4 · O18)

- `CompetitionImported` se emite tras cada importación; el listener `RecordCompetitionImport` guarda la
  auditoría (`competition_imports`), que la SPA muestra como «última importación». Otro consumidor (avisar,
  invalidar cachés) se agrega sin tocar el caso de uso.
- `ImportCompetitionJob` (`ShouldBeUnique` por liga, `retryUntil` de 2 h, `release` según `retryAfter`).
- Scheduler: `football:sync-competitions` (diario) y `football:refresh-stale` (cada hora). También existe
  `php artisan football:import PL PD SA [--queue]` para cargar ligas desde la consola.

### Datos (B4)

- Las **claves primarias son los ids de football-data**. Cada importación es un `upsert` de Eloquent por id, así
  que consultar la misma liga dos veces **no duplica** nada (hay pruebas que lo verifican). La relación
  liga–equipo es un pivote `competition_team` con la temporada; un equipo puede estar en varias ligas
  (Premier League y Champions).
- De cada jugador se guarda nombre, posición, número de camiseta, fecha de nacimiento y nacionalidad.
- Sin SQL escrito a mano: migraciones, modelos, relaciones, *scopes* (`#[Scope]`) y `upsert`.

### Autenticación y ACL (B1 · B2)

- **Passport** con *personal access tokens*: el login valida con `Hash::check` (bcrypt, cast `hashed`), emite un
  JWT con caducidad configurable y el logout lo revoca. No usé el *password grant* porque obligaría a exponer un
  `client_secret` en la SPA. Los intentos de login están limitados (5/min por correo+IP).
- **Roles y permisos con spatie/laravel-permission** en el guard `api`. `config/acl.php` es la fuente única:
  permisos, su equivalente CASL (`competitions.import` → `{action: import, subject: Competition}`), roles
  sembrados y roles protegidos.
- **Se decide en el servidor:** cada ruta declara su permiso (`can:teams.read`), y el middleware `active` corta en
  el acto a un usuario desactivado aunque tenga un token vigente (además, desactivarlo revoca sus tokens).
- **Permisos fijos, roles dinámicos.** Un permiso nuevo no tendría efecto si el código no lo verifica, así que
  el catálogo lo define el código. Los roles sí son datos: desde la SPA se crean y se les asignan permisos.
- Reglas de negocio: nadie puede desactivarse a sí mismo, no se puede quitar el último administrador activo,
  el rol `admin` no se edita ni se borra y un rol con usuarios no se elimina (`409`).

### Organización del código (B7 · O6)

Controladores de 1–3 líneas por acción; validación en **Form Requests**; respuestas en **API Resources**; lógica en
casos de uso; un único `ApiExceptionRenderer` para el formato de errores; dependencias por constructor
(inversión de dependencias en el puerto y el presupuesto); clases `final` y `readonly` donde corresponde.

## 8. Diagramas

Están en [`docs/`](docs/), cada uno como **Mermaid editable (`.mmd`)**, **SVG** y **PNG**.

### Arquitectura — [`docs/arquitectura.png`](docs/arquitectura.png) · [`.svg`](docs/arquitectura.svg) · [`.mmd`](docs/arquitectura.mmd)

![Arquitectura](docs/arquitectura.png)

La SPA habla con nginx, que sirve la build estática y pasa las llamadas a la API en PHP-FPM. Dentro de la API se
ven las capas: HTTP (Passport, permisos, Form Requests, Resources), aplicación (comandos y consultas), dominio
(puerto, DTOs, evento) e infraestructura (adaptador de football-data y presupuesto de peticiones). PostgreSQL
guarda los datos y los usuarios/tokens; Redis guarda la caché, la cola y el presupuesto de 10 req/min que
comparten la API, el worker y el scheduler.

### Límite de peticiones — [`docs/limite-peticiones.png`](docs/limite-peticiones.png) · [`.svg`](docs/limite-peticiones.svg) · [`.mmd`](docs/limite-peticiones.mmd)

El árbol de decisión de la [sección 6](#6-el-límite-de-10-peticiones-por-minuto): cuándo se responde desde la
base, cuándo se gasta un cupo, qué pasa ante un 429 o una caída, y cuándo se encola. Lo dibujé aparte porque es
la decisión más importante de la prueba.

### Secuencia de importación de una liga — [`docs/flujo-importacion.png`](docs/flujo-importacion.png) · [`.svg`](docs/flujo-importacion.svg) · [`.mmd`](docs/flujo-importacion.mmd)

![Importación de una liga](docs/flujo-importacion.png)

`GET /competitions/PL` de punta a punta: autorización, consulta local, adquisición de cupo, llamada a
football-data, *upserts* en una transacción, evento de auditoría y, en la rama sin cupo, el job en cola.

### Autenticación y ACL — [`docs/autenticacion-acl.png`](docs/autenticacion-acl.png) · [`.svg`](docs/autenticacion-acl.svg) · [`.mmd`](docs/autenticacion-acl.mmd)

![Autenticación y ACL](docs/autenticacion-acl.png)

Login con Passport, entrega de las reglas CASL, cómo la SPA las usa para menú/rutas/botones y cómo la API sigue
siendo la que decide (401 vs 403, logout).

### Modelo de datos — [`docs/modelo-datos.png`](docs/modelo-datos.png) · [`.svg`](docs/modelo-datos.svg) · [`.mmd`](docs/modelo-datos.mmd)

![Modelo de datos](docs/modelo-datos.png)

Tablas de fútbol (con ids de football-data como clave), pivote liga–equipo con temporada, auditoría de
importaciones, y las tablas de usuarios, roles/permisos (spatie) y tokens (Passport).

> Para regenerar las imágenes: `npx -p @mermaid-js/mermaid-cli mmdc -i docs/arquitectura.mmd -o docs/arquitectura.svg`
> (o pega el `.mmd` en <https://mermaid.live>).

## 9. Frontend

- **Vue 3 + Composition API + TypeScript estricto**, Vue Router, **Pinia** (sesión y notificaciones) y
  **Vuetify** (responsive: menú lateral fijo en escritorio y desplegable en el celular; tablas que pasan a
  tarjetas en pantallas chicas).
- **CASL**: las reglas que entrega la API alimentan una única `ability`. Con ella se arma el menú, el router
  bloquea rutas (`meta.can`) y se muestran u ocultan botones (importar, crear, editar, desactivar). Al recargar,
  `/auth/me` trae reglas frescas: si un administrador cambió el rol, se refleja sin volver a iniciar sesión.
- **Sesión y permisos sin pantallas rotas:** un interceptor de Axios normaliza todos los errores. Un `401`
  limpia la sesión y vuelve al login con «Tu sesión no es válida o ha expirado» (y regresa a la página donde
  estabas). Un usuario desactivado sale con su mensaje. Sin permiso se ve una página «No tienes permiso».
- **Experiencia de uso:** *skeletons* de carga, estados vacíos con una acción sugerida, búsquedas con
  *debounce*, filtros, paginación y orden en el servidor. Ante el límite de football-data aparece una cuenta
  regresiva y se reintenta solo. Si la API está caída o sin conexión, el mensaje lo dice. Los datos
  desactualizados llevan un aviso. Las respuestas que llegan fuera de orden se descartan.
- **Componentes reutilizables:** `AsyncState` (carga/vacío/error), `ErrorAlert` (con reintento y cuenta
  regresiva), `PageHeader`, `CrestAvatar` (escudo con iniciales de respaldo), `StaleBanner`, `ConfirmDialog`,
  `NoticeStack`, y los composables `useAsync`, `useDebouncedRef` y `useCountdown`.

Pantallas: login · ligas (búsqueda, país, disponibles/importadas) · detalle de liga (equipos, estadísticas,
actualizar en segundo plano) · equipos · detalle de equipo (ficha, competiciones, plantel por línea) ·
jugadores (tabla con filtros y orden) · usuarios (crear, editar, roles, activar/desactivar) · roles y permisos
(editor con el equivalente CASL de cada permiso).

## 10. Pruebas, TDD e integración continua

```bash
# Backend (Docker)
docker compose exec app php artisan test
docker compose exec app php artisan test --testsuite=Acceptance
# Backend (local):  cd backend && php artisan test   ·   vendor/bin/pint --test

# Frontend
cd frontend && npm test && npm run type-check && npm run ci:lint
```

| Tipo | Dónde | Qué cubre |
|---|---|---|
| **Unitarias** (O7) | `backend/tests/Unit` | Ventana deslizante del presupuesto; adaptador de football-data: mapeo, token, cabeceras, 429, traducción de errores, red caída, presupuesto agotado sin llamar |
| **Integración** (O8) | `backend/tests/Feature` | API + base de datos: login/logout/token revocado, **matriz de permisos por rol** sobre todas las rutas, los 5 endpoints, 404/422/429/502/503, importación idempotente, job en cola, gestión de usuarios y roles |
| **Aceptación** (O9) | `backend/tests/Acceptance` | Flujos completos con los seeders reales: el analista importa y recorre una liga y sigue trabajando con football-data caído; el admin crea un rol y un usuario, que solo ve lo permitido y pierde acceso al ser desactivado |
| **Frontend** | `frontend/src/__tests__` | Normalización de errores, reglas CASL, `useAsync` sin respuestas fuera de orden, posiciones |

Las pruebas usan SQLite en memoria y en CI corren además contra **PostgreSQL**. HTTP se simula con
`Http::fake` y payloads con la forma de football-data v4, así que **no consumen tu cupo**.

**TDD (O10):** el historial muestra pruebas antes que el código en las piezas centrales: los commits
`test: … (red)` del presupuesto de peticiones, del adaptador de football-data y del caso de uso de importación
van antes de sus `feat: … (green)` (`git log --oneline`).

**CI (O11):** [`.github/workflows/ci.yml`](.github/workflows/ci.yml): Pint + pruebas del backend en SQLite y
PostgreSQL; lint, formato, type-check, pruebas y build del frontend; build de las imágenes Docker.

## 11. Supuestos

- **Rutas sin `/api`** para que coincidan literalmente con el enunciado. La SPA en Docker usa `/api/*` en su mismo
  origen y nginx lo reenvía a la API, así que no hay CORS en producción.
- **`GET /competitions/{competition_id}` importa para cualquier rol con `competitions.read`**, porque así lo pide
  el enunciado («al consultarla se guardan»). El permiso `competitions.import` es para forzar una actualización
  en segundo plano aunque los datos estén frescos.
- `{competition_id}` acepta el **id** (`2021`) o el **código** (`PL`) de football-data. Los ids de equipos y
  jugadores son los de football-data.
- **Número de camiseta:** el plantel de football-data v4 no siempre lo trae (depende del plan). Se guarda cuando
  viene y si no queda `null`. Pedir `/persons/{id}` jugador por jugador costaría cientos de peticiones y
  rompería el límite.
- Solo las ligas del plan **`TIER_ONE`** son importables con el token gratuito (configurable). El resto se lista,
  pero la SPA las muestra deshabilitadas.
- Una liga se considera **fresca durante 12 h** y el catálogo durante 24 h (configurables).
- Si un jugador pasa a un equipo que todavía no importamos, conserva su último equipo conocido hasta la siguiente
  importación de su nuevo club.
- Los mensajes de la API están en español porque su consumidor es una SPA en español. Los `code` de error están
  en inglés y son estables.

## 12. Pendiente y cómo lo haría

- **Pruebas E2E del navegador** (Playwright/Cypress) sobre la SPA. Hoy la aceptación está a nivel de API y el
  frontend tiene pruebas unitarias.
- **Refresh tokens** para renovar la sesión sin volver a iniciarla (hoy el token dura 8 h y se pide login).
  Los guardaría en una cookie `httpOnly` en lugar de `localStorage`.
- **Avisar a la SPA cuando termina una importación en cola** (Laravel Reverb / WebSockets) en vez de reintentar
  con la cuenta regresiva.
- **Observabilidad:** métricas del presupuesto (cupo usado, jobs en espera) en Horizon o Pulse.
- **Despliegue productivo:** imagen sin dependencias de desarrollo, `config:cache`/`route:cache`, HTTPS, secretos
  en un gestor y no en `.env`.

## 13. Estructura del repositorio

```
.
├── backend/                     API Laravel 13
│   ├── app/
│   │   ├── Domain/              Football: puerto, DTOs, excepciones, evento · Access: excepciones
│   │   ├── Application/         Casos de uso: comandos y consultas (Football, Access)
│   │   ├── Infrastructure/      FootballDataClient (adaptador) y SlidingWindowRequestBudget
│   │   ├── Http/                Controllers (delgados), Requests, Resources, Middleware
│   │   ├── Jobs/ Listeners/     ImportCompetitionJob, RecordCompetitionImport
│   │   ├── Console/Commands/    app:install, football:import, football:sync-competitions, football:refresh-stale
│   │   ├── Models/              Eloquent
│   │   └── Support/Http/        ApiExceptionRenderer (formato único de errores)
│   ├── config/acl.php           Permisos, equivalencia CASL y roles
│   ├── config/football.php      football-data: token, límite, planes, frescura
│   ├── database/                Migraciones, factories, seeders
│   └── tests/                   Unit · Feature · Acceptance
├── frontend/                    SPA Vue 3 + TypeScript
│   └── src/ (api, ability, stores, router, layouts, components, composables, views)
├── docs/                        Diagramas (.mmd editable, .svg, .png)
├── docker/                      Dockerfiles de PHP-FPM y nginx, entrypoint, nginx.conf
├── compose.yml                  Stack completo
├── .env.example                 Variables para Docker (sin secretos)
└── .github/workflows/ci.yml     Integración continua
```
