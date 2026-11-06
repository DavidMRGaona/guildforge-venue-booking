---
id: venue-bookings.panel.overview
title: 'Reservas del local: cómo funcionan'
summary: Qué son los espacios y las reservas, por qué estados pasa una reserva, qué hace la web sola y quién puede hacer cada cosa en el panel.
category: activities
audience: panel
order: 10
featured: true
keywords: [reservas, reserva, reservas del local, local, sala, espacio, espacios, turnos, bloques, horario, estados, pendiente, confirmada, rechazada, cancelada, completada, no presentado, aprobación, permisos, cómo funciona, introducción, empezar]
related: [venue-bookings.panel.spaces, venue-bookings.panel.schedule, venue-bookings.panel.manage-bookings, venue-bookings.panel.create-booking, venue-bookings.panel.settings, core.panel.roles-and-permissions]
conditions:
  permissions:
    any: ['venuebookings:bookings.view_any', 'venuebookings:bookings.manage', 'venuebookings:bookings.settings']
---
El módulo de reservas deja que la gente de {{site_name}} reserve los espacios del local desde la web, en la página **Reservas del local**. En el panel preparas los espacios, revisas las reservas y decides las reglas.

## Las piezas

| Pieza | Qué es |
|---|---|
| **Espacio** | Algo que se reserva: una sala, una mesa, una zona. Tiene su estado, su horario y los campos de su formulario de reserva. |
| **Horario** | Los días y horas en que se puede reservar un espacio, repartidos en **Turnos horarios** o en **Bloques horarios**. |
| **Reserva** | Un espacio para una persona, un día y una franja de horas. |

## Estados de una reserva

| Estado | Qué significa | A qué puede pasar |
|---|---|---|
| **Pendiente** | Espera aprobación. Ya ocupa su franja. | **Confirmada**, **Rechazada** o **Cancelada** |
| **Confirmada** | Aceptada. | **Completada**, **Cancelada** o **No presentado** |
| **Completada** | Ya pasó. Es definitivo. | Ninguno |
| **Cancelada** | La canceló la persona o la organización. Es definitivo. | Ninguno |
| **Rechazada** | La organización no la aceptó. Es definitivo. | Ninguno |
| **No presentado** | Estaba confirmada y la persona no vino. Es definitivo. | Ninguno |

Las reservas pendientes y confirmadas son las activas: ocupan su franja, salen en el calendario de la web y cuentan para el límite de reservas de cada persona. Una reserva pendiente no caduca sola: sigue pendiente, y contando para ese límite, hasta que alguien la revisa.

## Lo que hace la web sola

- Según el **Modo de aprobación** de la configuración, las reservas de la web nacen **Confirmada** o **Pendiente**.
- Cada hora, las reservas confirmadas que terminaron hace más de las horas configuradas pasan a **Completada**, si **Completar reservas automáticamente** está activado.
- Cada hora se envía el recordatorio a las reservas confirmadas que empiezan pronto, si está activado.

## Web o panel

En la web, cada persona con cuenta reserva, ve sus reservas y, si tiene permiso, las cancela antes de que empiecen. Las reglas de la configuración (máximo de reservas activas, antelación mínima y días a futuro) valen para todos los que reservan en la web, también la administración. En el panel se crean reservas sin esas reglas, y se confirman, rechazan, cancelan, mueven y borran.

## Quién hace qué

Salvo **Cancelar reservas propias**, que viene concedido al rol base de todas las cuentas, ninguno de estos permisos se concede solo: se dan a cada rol en **Roles**. La administración los tiene todos.

| Permiso | Qué permite |
|---|---|
| **Ver reservas** | Ver la lista de reservas en el panel |
| **Crear reservas** | Crear reservas desde el panel |
| **Gestionar reservas** | Crear y cambiar espacios, editar, mover y borrar reservas, confirmarlas, rechazarlas y marcarlas como no presentadas. Recibe el aviso de cada reserva nueva y, en la web, ve quién hizo cada reserva y las notas internas |
| **Cancelar cualquier reserva** | Cancelar reservas de otras personas, desde el panel o desde la web |
| **Cancelar reservas propias** | Cancelar sus propias reservas en la web |
| **Configuración de reservas** | Cambiar la página de configuración del módulo |

## Dónde está cada cosa

- En el menú lateral, el grupo **Reservas del local** tiene **Espacios**, **Reservas** y **Configuración de reservas**.
- En el escritorio, los widgets **Reservas de hoy**, **Pendientes de aprobación** y **Próximas reservas** resumen lo que viene.
- Para que la gente encuentre la página de reservas, añade **Reservas** o **Mis reservas** al menú del sitio.

[[action:filament.admin.resources.bookings.index|Ver las reservas|if=venuebookings:bookings.view_any]]
[[action:filament.admin.resources.bookable-resources.index|Ver los espacios|if=venuebookings:bookings.manage]]
