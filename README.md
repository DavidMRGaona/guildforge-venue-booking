# Reservas del local

Sistema de reservas del local para gestionar espacios y horarios, con calendario interactivo, validación de disponibilidad, campos configurables, notificaciones por email y gestión desde el panel de administración.

## Características

- **Espacios reservables**: espacios con horario de apertura y modo de programación propios
- **Calendario interactivo**: vista de calendario con las franjas disponibles y las reservas existentes
- **Modos de programación**: franjas horarias (`time_slots`) o bloques predefinidos (`time_blocks`)
- **Modo de aprobación**: confirmación automática o aprobación manual en el panel
- **Campos configurables**: campos predefinidos y personalizados con visibilidad configurable
- **Reglas de reserva en el servidor**: límite de reservas activas, antelación mínima y máximo de días futuros se comprueban al reservar, para cualquier cuenta (también administradores); la franja debe estar hecha de los turnos del horario del espacio (o ser un bloque entero)
- **Mis reservas**: cada usuario ve sus reservas en `/reservas/mis-reservas` y en la pestaña **Reservas** de su perfil, y las cancela si tiene el permiso `bookings.cancel_own`
- **Cierre a la hora de inicio**: desde la web no se puede reservar un día pasado ni una franja que ya ha empezado, ni cancelar una reserva empezada. El panel no tiene ese límite
- **Completado automático**: las reservas confirmadas pasan a completadas cuando ha pasado su hora de fin más un margen configurable
- **Integración con módulos**: asociación de la reserva con una mesa de rol o una campaña (configurable en el panel)
- **Notificaciones por email**: creación, confirmación, cancelación, rechazo y recordatorio antes del inicio
- **Panel de administración**: espacios, reservas, configuración y dos widgets para el escritorio

## Requisitos

- GuildForge core `^3.10` (da el rol `member` a toda cuenta registrada y dice a la interfaz si la cuenta debe verificar su email)
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
| `auto_complete_enabled` | `true` | Completar automáticamente las reservas confirmadas ya terminadas |
| `auto_complete_after_hours` | `24` | Horas tras el fin de la reserva para completarla (0–720) |
| `time_block_presets` | ninguno | Bloques horarios predefinidos |
| `predefined_fields` | actividad, participantes, teléfono y notas, todos desactivados | Campos predefinidos con visibilidad configurable |
| `custom_fields` | ninguno | Campos personalizados |
| `notifications` | todas activadas, recordatorio 24 horas antes | Avisos por email y recordatorio |
| `associations` | desactivadas | Asociación con mesas de rol y campañas |

Las fechas y horas de las reservas se interpretan en la zona horaria de la aplicación (`app.timezone`). En esa misma zona se decide cuándo empieza una reserva y cuál es el día de hoy para el calendario y para rechazar días pasados.

Si una instalación antigua aún guarda la configuración en `config/settings.php`, `php artisan venue-bookings:migrate-settings` la copia a la tabla `settings` (no hace nada si ya existe).

### Bloques horarios predefinidos

Los bloques se definen en la configuración, cada uno con nombre, apertura y cierre (por ejemplo, Mañana 10:00-14:00, Tarde 14:00-20:00). Un bloque puede terminar al día siguiente (Noche 20:00-02:00). Solo se usan en los espacios con el modo `time_blocks`.

### Recordatorios

Con **Enviar recordatorio** activado, cada reserva confirmada recibe un único email de recordatorio entre las horas de antelación configuradas (**Horas de antelación del recordatorio**, 24 por defecto) y su hora de inicio. Las reservas que ya han empezado no se recuerdan. Si se cambia la fecha o la hora de inicio de una reserva ya recordada, recibe un recordatorio nuevo.

El módulo programa `venue-bookings:send-reminders` cada hora (sin ejecuciones solapadas), así que basta con que el planificador de Laravel esté en marcha (`php artisan schedule:work`, o `php artisan schedule:run` cada minuto). `php artisan schedule:list` muestra la tarea.

Al actualizar desde una versión anterior a la 2.3.0, la primera ejecución envía un recordatorio a todas las reservas confirmadas que empiecen dentro de la ventana configurada.

### Completado automático

Con **Completar reservas automáticamente** activado, `venue-bookings:complete-past` pasa a **Completada** cada reserva confirmada cuyo fin (fecha y hora de fin en la zona del local; al día siguiente si cruza la medianoche) más **Horas tras el fin para completar** ya ha pasado. Las reservas pendientes, rechazadas, canceladas o no presentadas no se tocan, y hasta completarse el personal puede marcarlas como **No presentado**. No se envía ningún email.

El módulo lo programa cada hora (sin ejecuciones solapadas), junto a los recordatorios. Cada reserva se procesa por separado: si alguien la cambió mientras tanto se salta, y un fallo queda en el log sin detener al resto. La primera ejecución tras actualizar completa de una vez todas las reservas confirmadas antiguas.

### Franjas y reglas al reservar

Al crear una reserva desde la web, el servidor exige que la franja esté hecha de turnos consecutivos que la página ofrece ese día (en el modo `time_blocks`, un bloque entero) y aplica el límite de reservas activas, la antelación mínima y los días máximos a futuro. La lista de turnos marca como no disponibles los que empiezan antes de la antelación mínima (cualquier día) y los de días más allá del máximo.

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
| Reservas | Listado, creación, edición y borrado de reservas; confirmar, rechazar, cancelar o marcar como no presentado (cada acción exige su permiso, ver [Permisos](#permisos)) |
| Configuración de reservas | Configuración del módulo (permiso `bookings.settings`) |

Widgets del escritorio (se activan y ordenan en la configuración del escritorio):

- `TodayBookingsWidget`: reservas de hoy y reservas pendientes de aprobación
- `UpcomingBookingsWidget`: próximas reservas

Los dos widgets solo se muestran a quien puede ver el listado de reservas (`bookings.view_any`).

## Comandos Artisan

| Comando | Descripción |
|---------|-------------|
| `venue-bookings:send-reminders` | Envía los recordatorios pendientes (programado cada hora por el módulo) |
| `venue-bookings:complete-past` | Completa las reservas confirmadas ya terminadas (programado cada hora por el módulo) |
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
| `DELETE` | `/reservas/{booking}` | Cancelar una reserva (auth; propia con `bookings.cancel_own` o cualquiera con `bookings.cancel_any`) |

## Componentes Vue

### Componentes

| Componente | Descripción |
|------------|-------------|
| `BookingCalendar` | Calendario interactivo con vista de reservas; empieza en el día de hoy del local (`today`) y no ofrece días anteriores |
| `BookingCard` | Tarjeta resumen de una reserva; muestra **Cancelar reserva** solo con `canCancel` |
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

Los administradores tienen todos los permisos. La columna **Rol sugerido** indica a quién suele tener sentido dárselos en el editor de roles; el único que el módulo concede por su cuenta es `bookings.cancel_own` al rol `member` (ver abajo).

| Permiso | Rol sugerido | Uso |
|---------|--------------|-----|
| `bookings.view_any` | editor | Ver el listado de reservas en el panel y los widgets del escritorio |
| `bookings.view` | editor | Ver el detalle de una reserva en el panel |
| `bookings.create` | editor | Crear reservas desde el panel |
| `bookings.manage` | editor que gestione reservas | Confirmar, rechazar y marcar como no presentado desde el listado; editar (incluido el cambio de estado) y borrar reservas; gestionar espacios |
| `bookings.cancel_any` | editor que gestione reservas | Cancelar cualquier reserva: desde el listado del panel, pasando una reserva a «Cancelada» al editarla (también exige `bookings.manage`) y desde la web (`DELETE /reservas/{booking}`) |
| `bookings.cancel_own` | member (concedido al actualizar) | Cancelar las propias reservas desde la web |
| `bookings.settings` | — | Abrir y guardar la configuración del módulo |

Las acciones del panel se comprueban también en el servidor: si se fuerzan sin el permiso, la reserva no cambia. Para que un editor siga gestionando reservas hay que darle `bookings.manage` y `bookings.cancel_any`; `bookings.view_any` solo deja verlas.

En la web pública, cualquier usuario identificado puede reservar dentro de los límites configurados (no se exige `bookings.create`). Para cancelar una reserva hay que ser su dueño y tener `bookings.cancel_own`, o tener `bookings.cancel_any`. Las páginas solo muestran el botón **Cancelar reserva** cuando el servidor lo permitiría. Si no se cumple, la cancelación responde 403, igual que una reserva que no existe o un identificador mal formado. Una reserva solo se puede cancelar desde la web hasta su hora de inicio, también con `bookings.cancel_any`: después, la cancelación vuelve con «La reserva ya ha empezado y no se puede cancelar.» y las páginas dejan de mostrar el botón (`is_cancellable` es falso). Quien no puede cancelarla sigue recibiendo el 403. Desde el panel se cancela a cualquier hora.

### Concesión de `bookings.cancel_own` al rol `member`

La migración `2026_10_14_200001_grant_cancel_own_to_member_role` concede `bookings.cancel_own` al rol `member`, que el core da a toda cuenta registrada, para que los usuarios sigan pudiendo cancelar sus reservas. Crea antes la fila del permiso si `permissions:sync` aún no la ha creado (con los mismos campos; `permissions:sync` la reutiliza por su clave). Si no existe el rol `member`, crea igualmente la fila del permiso y no concede nada; desde el core 3.8 el rol `member` existe siempre tras migrar. Se puede ejecutar varias veces y no se deshace al revertirla.

La concesión se aplica una sola vez, cuando corre la migración. Si un administrador quita `bookings.cancel_own` a `member` en el editor de roles, los usuarios que no tengan otro rol con él dejan de poder cancelar, y las actualizaciones posteriores del módulo no se lo devuelven; solo una reinstalación que borre los datos del módulo vuelve a ejecutarla.

## Verificación de email

Cuando el core exige verificar el email (*Configuración del sitio → Autenticación*), sus reglas cierran a la cuenta que aún debe hacerlo las rutas con `auth` del módulo: la elegibilidad, crear una reserva, cancelarla y `/reservas/mis-reservas`. El calendario (`/reservas`) y el perfil siguen abiertos, y la interfaz lo anticipa con `useVerificationGate()` del core en vez de dejar que el servidor rechace lo enviado:

- **Calendario**: no consulta la elegibilidad y el botón **Reservar** aparece desactivado, con el motivo («Para reservar, verifica tu email.») asociado por `aria-describedby` y el enlace «Ver cómo» a la página de verificación.
- **Formulario de reserva**: no se envía ni muestra los campos; muestra el mismo motivo con el botón **Reservar** desactivado. Una elegibilidad que llega vacía (su endpoint falló) nunca deja el formulario activo a esa cuenta.
- **Cancelar reserva** (pestaña **Reservas** del perfil): el botón aparece desactivado con «Para cancelar tu reserva, verifica tu email.» y el mismo enlace. `/reservas/mis-reservas` no llega a mostrarse: el servidor lleva al aviso.
- **Cambio inesperado** (por ejemplo, la verificación se activó con la página ya abierta): si la elegibilidad responde con el 403 de verificación, el formulario queda desactivado y la página va al aviso; una reserva o cancelación enviada de todos modos acaba en el aviso, sin mensaje de éxito.

Los invitados, las cuentas verificadas, las creadas antes de que se empezara a exigir la verificación y todas cuando el ajuste está apagado reservan y cancelan exactamente igual que antes.

## Arquitectura

```
src/modules/venue-bookings/
├── config/
│   ├── module.php             # Activación del módulo
│   └── settings.php           # Valores de una instalación nueva
├── database/
│   ├── migrations/            # Migraciones (espacios, horarios, reservas, permiso cancel_own de member)
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
│   ├── Integration/           # Tests de integración (repositorios, servicios, migraciones de datos)
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
