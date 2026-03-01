# Reservas del local

Sistema de reservas del local para gestionar espacios y horarios, con calendario interactivo, validación de disponibilidad, campos configurables, notificaciones por email y gestión desde el panel de administración.

## Características

- **Recursos reservables**: Gestión de espacios con horarios de operación y modos de programación
- **Calendario interactivo**: Vista de calendario con selección de franjas horarias disponibles
- **Modos de programación**: Franjas horarias (`time_slots`) o bloques predefinidos (`time_blocks`)
- **Modo de aprobación**: Confirmación automática o aprobación manual por administradores
- **Campos configurables**: Campos predefinidos y personalizados con visibilidad configurable
- **Validación de elegibilidad**: Límite de reservas activas, antelación mínima y máximo de días futuros
- **Mis reservas**: Los usuarios pueden ver y gestionar sus propias reservas
- **Integración con módulos**: Asociación con mesas de rol y campañas (si están habilitados)
- **Notificaciones por email**: Creación, confirmación, cancelación, rechazo y recordatorio
- **Panel de administración**: Gestión completa de recursos, reservas y configuración desde Filament

## Requisitos

- PHP >= 8.2
- GuildForge core

## Instalación

1. Copiar el módulo a `src/modules/venue-bookings/`

2. Descubrir y habilitar el módulo:

```bash
php artisan module:discover
php artisan module:enable venue-bookings
```

3. Ejecutar las migraciones:

```bash
php artisan migrate
```

## Configuración

### Configuración global

Valores almacenados en `config/settings.php`:

| Clave | Valor por defecto | Descripción |
|-------|-------------------|-------------|
| `approval_mode` | `require_approval` | Modo de aprobación: `auto_confirm` o `require_approval` |
| `max_active_bookings_per_user` | `19` | Máximo de reservas activas por usuario |
| `min_advance_minutes` | `60` | Antelación mínima en minutos para reservar |
| `max_future_days` | `15` | Máximo de días en el futuro para reservar |
| `time_block_presets` | Mañana, Tarde, Noche | Bloques horarios predefinidos |
| `predefined_fields` | actividad, participantes, teléfono, notas | Campos predefinidos con visibilidad configurable |
| `custom_fields` | `[{ key: 'num-socios', ... }]` | Campos personalizados |
| `notifications` | todas habilitadas, recordatorio 24h | Configuración de notificaciones |
| `associations` | mesas y campañas habilitadas | Integración con otros módulos |

### Bloques horarios predefinidos

| Bloque | Apertura | Cierre |
|--------|----------|--------|
| Mañana | 10:00 | 14:00 |
| Tarde | 14:00 | 20:00 |
| Noche | 20:00 | 10:00 |

## Modos de programación

| Modo | Valor | Descripción |
|------|-------|-------------|
| Franjas horarias | `time_slots` | El usuario elige hora de inicio y fin dentro del horario de operación |
| Bloques predefinidos | `time_blocks` | El usuario selecciona un bloque horario predefinido (Mañana, Tarde, Noche) |

## Estados de reserva

| Estado | Valor | Descripción |
|--------|-------|-------------|
| Pendiente | `pending` | Reserva creada, pendiente de aprobación |
| Confirmada | `confirmed` | Reserva aprobada |
| Completada | `completed` | Reserva finalizada |
| Cancelada | `cancelled` | Cancelada por el usuario o administrador |
| No presentado | `no_show` | El usuario no se presentó |
| Rechazada | `rejected` | Rechazada por administrador |

### Transiciones válidas

- **Pendiente** → Confirmada, Cancelada, Rechazada
- **Confirmada** → Completada, Cancelada, No presentado

Los estados Completada, Cancelada, No presentado y Rechazada son finales.

## Arquitectura

```
src/modules/venue-bookings/
├── config/
│   ├── module.php             # Activación del módulo
│   └── settings.php           # Configuración del módulo
├── database/
│   ├── migrations/            # Migraciones (resources, schedules, bookings)
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
│   │   └── Commands/          # Comandos Artisan (recordatorios)
│   ├── Domain/
│   │   ├── Entities/          # Entidades (BookableResource, Booking, OperatingSchedule)
│   │   ├── Enums/             # Enumeraciones (BookingStatus, SchedulingMode, etc.)
│   │   ├── Events/            # Eventos de dominio
│   │   ├── Exceptions/        # Excepciones de dominio
│   │   ├── Repositories/      # Interfaces de repositorios
│   │   └── ValueObjects/      # Objetos de valor
│   ├── Filament/
│   │   ├── Pages/             # Páginas de configuración
│   │   ├── Resources/         # Recursos CRUD (BookableResource, Booking)
│   │   └── Widgets/           # Widgets del dashboard
│   ├── Http/
│   │   ├── Controllers/       # Controladores
│   │   └── Requests/          # Form Requests
│   ├── Infrastructure/
│   │   ├── Listeners/         # Listeners de eventos (notificaciones)
│   │   ├── Persistence/       # Repositorios Eloquent
│   │   └── Services/          # Implementaciones de servicios
│   ├── Notifications/         # Notificaciones por email
│   └── Policies/              # Políticas de autorización
├── tests/
│   ├── Feature/               # Tests funcionales (Filament, HTTP)
│   ├── Integration/           # Tests de integración (repositorios, servicios)
│   └── Unit/                  # Tests unitarios (entidades, enums, VOs)
├── module.json                # Manifiesto del módulo
└── phpunit.xml                # Configuración de tests
```

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
| `ResourceSelector` | Selector de recurso reservable |
| `ProfileBookingsSection` | Sección de reservas en el perfil del usuario |

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
| `BookingReminder` | Se envió un recordatorio de reserva |

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `bookings.view_any` | Ver listado de reservas |
| `bookings.view` | Ver detalle de reserva |
| `bookings.create` | Crear reservas |
| `bookings.cancel_own` | Cancelar reservas propias |
| `bookings.cancel_any` | Cancelar cualquier reserva |
| `bookings.manage` | Gestionar reservas (confirmar, rechazar, completar) |
| `bookings.settings` | Gestionar configuración del módulo |

## Tests

Ejecutar los tests del módulo:

```bash
# Desde el directorio del módulo
cd src/modules/venue-bookings
../../../vendor/bin/phpunit

# O desde el directorio raíz
php artisan test --filter=VenueBooking
```

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
