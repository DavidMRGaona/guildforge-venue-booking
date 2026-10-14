# Reservas del local

Sistema de reservas del local para gestionar espacios y horarios, con calendario interactivo, validación de disponibilidad, campos configurables, notificaciones por email y gestión desde el panel de administración.

## Características

- **Espacios reservables**: espacios con horario de apertura y modo de programación propios
- **Calendario interactivo**: vista de calendario con las franjas disponibles y las reservas existentes
- **Modos de programación**: franjas horarias (`time_slots`) o bloques predefinidos (`time_blocks`)
- **Modo de aprobación**: confirmación automática o aprobación manual en el panel
- **Campos configurables**: campos predefinidos y personalizados con visibilidad configurable
- **Validación de elegibilidad**: límite de reservas activas, antelación mínima y máximo de días futuros
- **Mis reservas**: cada usuario ve y cancela sus reservas en `/reservas/mis-reservas` y en la pestaña **Reservas** de su perfil
- **Integración con módulos**: asociación de la reserva con una mesa de rol o una campaña (configurable en el panel)
- **Notificaciones por email**: creación, confirmación, cancelación, rechazo y recordatorio antes del inicio
- **Panel de administración**: espacios, reservas, configuración y dos widgets para el escritorio

## Requisitos

- GuildForge core `^3.1`
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

## Instalación

1. Descarga `venue-bookings-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-venue-booking` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar / actualizar** y sube el ZIP.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones y seeders.
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update venue-bookings`.

### En desarrollo

Con el repositorio clonado en `src/modules/venue-bookings`:

```bash
php artisan module:discover
php artisan module:enable venue-bookings
php artisan module:build venue-bookings   # compila los componentes Vue (--force para recompilar)
```

En las releases los assets los compila el workflow y van dentro del ZIP.

## Configuración

Se configura en el panel: **Reservas del local → Configuración de reservas** (permiso `bookings.settings`; los administradores siempre tienen acceso). Los valores se guardan en la tabla `settings` (clave `module_settings:venue-bookings`), así que se conservan al actualizar el módulo. `config/settings.php` solo aporta los valores de una instalación nueva.

| Clave | Valor de una instalación nueva | Descripción |
|-------|--------------------------------|-------------|
| `approval_mode` | `require_approval` | Modo de aprobación: `auto_confirm` o `require_approval` |
| `max_active_bookings_per_user` | `3` | Máximo de reservas activas (pendientes o confirmadas) por usuario |
| `min_advance_minutes` | `60` | Antelación mínima en minutos para reservar |
| `max_future_days` | `30` | Máximo de días en el futuro para reservar |
| `time_block_presets` | ninguno | Bloques horarios predefinidos |
| `predefined_fields` | actividad, participantes, teléfono y notas, todos desactivados | Campos predefinidos con visibilidad configurable |
| `custom_fields` | ninguno | Campos personalizados |
| `notifications` | todas activadas, recordatorio 24 horas antes | Avisos por email y recordatorio |
| `associations` | desactivadas | Asociación con mesas de rol y campañas |

Las fechas y horas de las reservas se interpretan en la zona horaria de la aplicación (`app.timezone`).

Si una instalación antigua aún guarda la configuración en `config/settings.php`, `php artisan venue-bookings:migrate-settings` la copia a la tabla `settings` (no hace nada si ya existe).

### Bloques horarios predefinidos

Los bloques se definen en la configuración, cada uno con nombre, apertura y cierre (por ejemplo, Mañana 10:00-14:00, Tarde 14:00-20:00). Un bloque puede terminar al día siguiente (Noche 20:00-02:00). Solo se usan en los espacios con el modo `time_blocks`.

### Recordatorios

Con **Enviar recordatorio** activado, cada reserva confirmada recibe un único email de recordatorio entre las horas de antelación configuradas (**Horas de antelación del recordatorio**, 24 por defecto) y su hora de inicio. Las reservas que ya han empezado no se recuerdan. Si se cambia la fecha o la hora de inicio de una reserva ya recordada, recibe un recordatorio nuevo.

El módulo programa `venue-bookings:send-reminders` cada hora (sin ejecuciones solapadas), así que basta con que el planificador de Laravel esté en marcha (`php artisan schedule:work`, o `php artisan schedule:run` cada minuto). `php artisan schedule:list` muestra la tarea.

Al actualizar desde una versión anterior a la 2.3.0, la primera ejecución envía un recordatorio a todas las reservas confirmadas que empiecen dentro de la ventana configurada.

## Modos de programación

| Modo | Valor | Descripción |
|------|-------|-------------|
| Franjas horarias | `time_slots` | El usuario elige hora de inicio y fin dentro del horario del espacio |
| Bloques predefinidos | `time_blocks` | El usuario elige uno de los bloques horarios configurados |

## Estados de reserva

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Reserva creada, pendiente de aprobación |
| Confirmada | `confirmed` | Reserva aprobada |
| Completada | `completed` | Reserva finalizada |
| Cancelada | `cancelled` | Cancelada por el usuario o en el panel |
| No presentado | `no_show` | El usuario no se presentó |
| Rechazada | `rejected` | Rechazada en el panel |

### Transiciones válidas

- **Pendiente** → Confirmada, Cancelada, Rechazada
- **Confirmada** → Completada, Cancelada, No presentado

Los estados Completada, Cancelada, No presentado y Rechazada son finales.

## Panel de administración

Grupo **Reservas del local**:

| Elemento | Descripción |
|----------|-------------|
| Espacios | Espacios reservables, con su horario y la configuración de sus campos |
| Reservas | Listado, creación, edición y borrado de reservas; confirmar, rechazar, cancelar o marcar como no presentado |
| Configuración de reservas | Configuración del módulo (permiso `bookings.settings`) |

Widgets del escritorio (se activan y ordenan en la configuración del escritorio):

- `TodayBookingsWidget`: reservas de hoy y reservas pendientes de aprobación
- `UpcomingBookingsWidget`: próximas reservas

## Comandos Artisan

| Comando | Descripción |
|---------|-------------|
| `venue-bookings:send-reminders` | Envía los recordatorios pendientes (programado cada hora por el módulo) |
| `venue-bookings:migrate-settings` | Copia la configuración de `config/settings.php` a la tabla `settings` |

## Rutas web

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/reservas` | Calendario de reservas |
| `GET` | `/reservas/api/slots` | API: franjas horarias disponibles |
| `GET` | `/reservas/api/events` | API: eventos del calendario |
| `GET` | `/reservas/api/bookings/{booking}` | API: detalle de una reserva |
| `GET` | `/reservas/api/eligibility` | API: elegibilidad del usuario (auth) |
| `POST` | `/reservas` | Crear una reserva (auth) |
| `GET` | `/reservas/mis-reservas` | Mis reservas (auth) |
| `DELETE` | `/reservas/{booking}` | Cancelar una reserva (auth) |

## Componentes Vue

### Componentes

| Componente | Descripción |
|------------|-------------|
| `BookingCalendar` | Calendario interactivo con vista de reservas |
| `BookingCard` | Tarjeta resumen de una reserva |
| `BookingDetailModal` | Modal con el detalle completo de una reserva |
| `BookingForm` | Formulario de creación de reserva |
| `BookingSlotPicker` | Selector de franja horaria disponible |
| `BookingStatusBadge` | Insignia de estado de la reserva con colores |
| `BookingTooltip` | Tooltip con información de la reserva |
| `ResourceSelector` | Selector de espacio reservable |
| `ProfileBookingsSection` | Pestaña **Reservas** del perfil del usuario |

### Páginas

| Página | Ruta | Descripción |
|--------|------|-------------|
| `VenueBookings/Index` | `/reservas` | Calendario de reservas con formulario de creación |
| `VenueBookings/MyBookings` | `/reservas/mis-reservas` | Listado de reservas del usuario |

## Eventos de dominio

| Evento | Descripción |
|--------|-------------|
| `BookingCreated` | Se creó una nueva reserva |
| `BookingConfirmed` | Una reserva fue confirmada |
| `BookingCancelled` | Una reserva fue cancelada |
| `BookingCompleted` | Una reserva fue completada |
| `BookingRejected` | Una reserva fue rechazada |
| `BookingNoShow` | El usuario no se presentó a la reserva |

`BookingReminder` está definido pero no se emite: el recordatorio se envía directamente como notificación.

## Permisos

Los administradores tienen todos los permisos. Los roles por defecto solo se asignan en una instalación nueva; después se gestionan en el editor de roles.

| Permiso | Rol por defecto | Uso |
|---------|-----------------|-----|
| `bookings.view_any` | editor | Ver el listado de reservas en el panel y, desde él, confirmar, rechazar, cancelar o marcar como no presentado cualquier reserva |
| `bookings.view` | editor | Ver el detalle de una reserva en el panel |
| `bookings.create` | editor | Crear reservas desde el panel |
| `bookings.manage` | — | Gestionar espacios y editar o borrar reservas en el panel |
| `bookings.cancel_any` | — | Cancelar reservas de otros usuarios desde la web (`DELETE /reservas/{booking}`) |
| `bookings.settings` | — | Abrir y guardar la configuración del módulo |
| `bookings.cancel_own` | editor | Registrado, sin uso: cualquier usuario identificado puede cancelar sus propias reservas |

En la web pública, cualquier usuario identificado puede reservar dentro de los límites configurados y cancelar sus propias reservas. Cancelar la reserva de otro usuario sin `bookings.cancel_any` responde 403, igual que una reserva que no existe.

## Arquitectura

```
src/modules/venue-bookings/
├── config/
│   ├── module.php             # Activación del módulo
│   └── settings.php           # Valores de una instalación nueva
├── database/
│   ├── migrations/            # Migraciones (espacios, horarios, reservas)
│   └── seeders/               # Seeders de desarrollo
├── lang/
│   └── es/                    # Traducciones en español
│       └── messages.php
├── resources/js/
│   ├── components/            # Componentes Vue
│   │   └── profile/           # Componentes de perfil
│   ├── locales/               # Traducciones i18n (frontend)
│   ├── pages/
│   │   └── VenueBookings/     # Páginas Inertia
│   └── types/                 # Tipos TypeScript
├── routes/
│   └── web.php                # Rutas web
├── src/
│   ├── Application/
│   │   ├── DTOs/              # Data Transfer Objects
│   │   │   └── Response/      # DTOs de respuesta
│   │   └── Services/          # Interfaces de servicios
│   ├── Console/
│   │   └── Commands/          # Comandos Artisan (recordatorios, migración de configuración)
│   ├── Domain/
│   │   ├── Entities/          # Entidades (BookableResource, Booking, OperatingSchedule)
│   │   ├── Enums/             # Enumeraciones (BookingStatus, SchedulingMode, etc.)
│   │   ├── Events/            # Eventos de dominio
│   │   ├── Exceptions/        # Excepciones de dominio
│   │   ├── Repositories/      # Interfaces de repositorios
│   │   └── ValueObjects/      # Objetos de valor
│   ├── Filament/
│   │   ├── Pages/             # Página de configuración
│   │   ├── Resources/         # Recursos CRUD (BookableResource, Booking)
│   │   └── Widgets/           # Widgets del escritorio
│   ├── Http/
│   │   ├── Controllers/       # Controladores
│   │   └── Requests/          # Form Requests
│   ├── Infrastructure/
│   │   ├── Listeners/         # Listeners de eventos (notificaciones)
│   │   ├── Persistence/       # Modelos y repositorios Eloquent
│   │   └── Services/          # Implementaciones de servicios
│   ├── Notifications/         # Notificaciones por email
│   └── Policies/              # Políticas de autorización
├── tests/
│   ├── Unit/                  # Tests unitarios (entidades, enums, VOs)
│   ├── Integration/           # Tests de integración (repositorios, servicios)
│   └── Feature/               # Tests funcionales (comandos, Filament, HTTP)
├── module.json                # Manifiesto del módulo
└── phpunit.xml                # Configuración de tests
```

## Tests

Desde `src/` (o dentro del contenedor `guildforge_app`):

```bash
vendor/bin/phpunit -c modules/venue-bookings/phpunit.xml
vendor/bin/phpunit -c modules/venue-bookings/phpunit.xml --testsuite=Unit
```

`php artisan test` no ejecuta los tests de los módulos.

## Publicar una versión

1. Sube `version` en `module.json`.
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`), compila los assets y publica `venue-bookings-X.Y.Z.zip` con su `.sha256` en la release.

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
