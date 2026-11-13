---
id: venue-bookings.panel.create-booking
title: Crear una reserva desde el panel
summary: Reserva un espacio en nombre de alguien, sin los límites que se aplican en la web.
category: activities
audience: panel
order: 60
keywords: [crear reserva, nueva reserva, reservar por alguien, reservar en nombre de, reserva manual, reserva del panel, bloquear sala, reservar para un evento, sin límites, propuesta de evento, petición de reserva, reservar espacio al aprobar]
related: [venue-bookings.panel.manage-bookings, venue-bookings.panel.schedule, venue-bookings.panel.overview]
conditions:
  permissions:
    all: ['venuebookings:bookings.create']
---
Desde el panel puedes reservar un espacio para cualquier persona con cuenta, por ejemplo para una actividad de la organización.

## Paso a paso

1. En el menú lateral, abre **Actividades** → **Reservas** y pulsa **Crear reserva**.
2. Elige el **Espacio** y el **Usuario** a cuyo nombre va la reserva.
3. Pon la **Fecha** (el día en que empieza), la **Hora de inicio** y la **Hora de fin**, en formato `HH:MM`.
4. Si quieres, elige el **Evento** y, si tu web tiene mesas de rol, la mesa o campaña.
5. Rellena los campos del espacio que salgan, en **Campos predefinidos**.
6. Añade **Notas del administrador** si hace falta.
7. Pulsa **Crear**.

## Qué cambia respecto a la web

- No cuentan las reglas de la configuración: ni el **Máximo de reservas activas por usuario**, ni los **Minutos mínimos de antelación**, ni los **Días máximos a futuro**.
- Sí cuentan el horario del espacio y las demás reservas: la franja debe coincidir con sus turnos (o ser un bloque entero), respetar el mínimo y el máximo de turnos seguidos, no solaparse con otra reserva y no haber empezado ya. El espacio debe estar **Activo**.
- El **Estado** no se elige: la reserva nace **Confirmada** o **Pendiente** según el **Modo de aprobación**, como en la web. Si queda pendiente, apruébala después con **Confirmar**.
- Se envían los mismos correos que en la web, a la persona elegida y a quien gestiona las reservas.

## Reservas que pide una propuesta de evento

Quien propone un evento desde la web puede marcar **¿Necesitas reservar un espacio?** y elegir el espacio. Al aprobar la propuesta se crea la reserva sola:

- A nombre de quien propuso el evento y enlazada al evento.
- **Confirmada**, aunque el **Modo de aprobación** pida aprobar las reservas: aprobar el evento ya es la revisión.
- Para el día y las horas del evento, en la zona horaria del local. La nota que escribió queda en **Notas del administrador**.
- Con las mismas comprobaciones que aquí: si el espacio está ocupado o la franja no encaja con sus turnos, la propuesta no se aprueba y el aviso dice por qué.
- Solo para eventos de un día. Si el evento dura más, desmarca la petición y haz las reservas aquí.

## Si algo no sale bien

Los mensajes son los mismos que al mover una reserva: los explica la guía «Revisar y gestionar las reservas». Salen junto al campo que hay que cambiar.

[[action:filament.admin.resources.bookings.create|Crear una reserva]]
