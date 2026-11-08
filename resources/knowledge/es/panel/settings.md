---
id: venue-bookings.panel.settings
title: 'Ajustes de las reservas: aprobación, límites y avisos'
summary: Qué decide cada sección de la configuración de reservas y cómo afecta a las reservas de la web.
category: activities
audience: panel
order: 70
keywords: [ajustes, configuración, configuración de reservas, modo de aprobación, aprobación, confirmación automática, requiere aprobación, límites, máximo de reservas, antelación, días a futuro, completar automáticamente, bloques predefinidos, notificaciones, correos, avisos, recordatorio, asociaciones]
related: [venue-bookings.panel.booking-fields, venue-bookings.panel.schedule, venue-bookings.panel.manage-bookings]
conditions:
  permissions:
    all: ['venuebookings:bookings.settings']
---
La configuración decide cómo funcionan todas las reservas. Ábrela en el menú lateral: **Sistema** → **Configuración** → **Reservas**. La página se llama **Configuración de reservas**. Al terminar, pulsa **Guardar**.

## Aprobación

**Modo de aprobación** (viene en **Requiere aprobación**):

- **Requiere aprobación**: las reservas nuevas quedan **Pendiente** hasta que alguien las confirma o las rechaza.
- **Confirmación automática**: quedan **Confirmada** al momento.

Cambiarlo no toca las reservas que ya existen.

## Límites

Valen para todas las reservas hechas en la web, también las de la administración. Las del panel no los tienen en cuenta.

| Campo | Qué decide |
|---|---|
| **Máximo de reservas activas por usuario** | Cuántas reservas pendientes o confirmadas puede tener cada persona a la vez. Solo cuentan las que aún no han terminado: las futuras y las que están en curso. Viene en 3. |
| **Minutos mínimos de antelación** | Cuánto antes del inicio hay que reservar. Viene en 60. |
| **Días máximos a futuro** | Hasta cuántos días por delante se puede reservar. Viene en 30. |

## Automatización

- **Completar reservas automáticamente** (viene activado): cada hora, las reservas confirmadas pasan a **Completada** cuando han pasado las **Horas tras el fin para completar** (de 0 a 720; vienen 24). Hasta entonces se pueden marcar como **No presentado**.

Las reservas pendientes no cambian solas.

## Bloques horarios predefinidos

Nombres y horas que reutilizas al preparar los horarios, con **Añadir varios días**. Pulsa **Añadir bloque horario** y rellena **Nombre del bloque**, **Hora de apertura** y **Hora de cierre**. Cambiarlos no toca los horarios ya guardados.

## Campos predefinidos y personalizados

Son la plantilla de los espacios que no tienen campos propios. La guía de campos explica cómo se usan.

## Notificaciones

| Interruptor | Quién recibe el correo |
|---|---|
| **Notificar al crear reserva** | Quien tiene **Gestionar reservas** (la administración incluida), con cada reserva nueva. |
| **Avisar de que la reserva está pendiente de aprobación** | La persona, solo si su reserva queda pendiente. |
| **Notificar al confirmar reserva** | La persona, al confirmarse (también con la confirmación automática). |
| **Notificar al cancelar reserva** | La persona, la cancele ella o la organización. |
| **Notificar al rechazar reserva** | La persona, con el motivo. |
| **Notificar al cambiar el espacio, la fecha o el horario de una reserva** | La persona, cuando alguien mueve desde el panel su reserva pendiente o confirmada. Al guardar se puede apagar para ese cambio. |
| **Enviar recordatorio** | La persona, una vez, antes de una reserva confirmada. |

**Horas de antelación del recordatorio** (de 1 a 72; vienen 24) dice cuánto antes del inicio sale el recordatorio. Se revisa cada hora. Todos los interruptores vienen activados.

## Asociaciones

Solo sale si tu web tiene mesas de rol. **Permitir asociar con mesas de juego** y **Permitir asociar con campañas** añaden esos campos al formulario de reserva, cada uno con su **Visibilidad**.

[[action:filament.admin.configuracion.pages.venue-bookings-settings|Abrir la configuración de reservas]]
