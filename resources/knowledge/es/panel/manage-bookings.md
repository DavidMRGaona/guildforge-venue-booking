---
id: venue-bookings.panel.manage-bookings
title: Revisar y gestionar las reservas
summary: Aprueba o rechaza las reservas pendientes, cancela, marca a quien no vino, mueve una reserva a otra hora o espacio y bórrala.
category: activities
audience: panel
order: 50
keywords: [reservas, gestionar reservas, aprobar reserva, confirmar reserva, rechazar reserva, pendientes, pendientes de aprobación, cancelar reserva, no presentado, no vino, mover reserva, cambiar hora, cambiar espacio, reserva modificada, avisar del cambio, completar reserva, borrar reserva, notas del administrador, motivo de rechazo, motivo de cancelación, filtrar reservas]
related: [venue-bookings.panel.create-booking, venue-bookings.panel.overview, venue-bookings.panel.settings]
conditions:
  permissions:
    all: ['venuebookings:bookings.view_any']
---
Las reservas están en **Reservas del local** → **Reservas**. Lo que puedes hacer en cada una depende de tus permisos.

## Encontrar una reserva

- Las pestañas **Todas**, **Pendientes**, **Confirmadas** y **Canceladas** (que incluye las rechazadas y las caducadas) separan las reservas por estado. **Pendientes** lleva el número de reservas por revisar.
- Con los filtros eliges el **Espacio**, el **Estado** y un intervalo de fechas (**Desde** y **Hasta**).
- Puedes buscar por espacio o por usuario.

## Las acciones de cada fila

Todas están en el menú **⋯** del final de la fila. Solo salen las que valen para esa reserva y las que tu permiso permite. Según el ancho de la ventana, la lista oculta columnas como **Inicio**, **Fin** y **Usuario**; ábrela con **Editar** para verlas.

| Acción | Cuándo sale | Qué hace | Permiso |
|---|---|---|---|
| **Confirmar** | Reserva **Pendiente** | La pasa a **Confirmada**. | **Gestionar reservas** |
| **Rechazar** | Reserva **Pendiente** | La rechaza. Pide el **Motivo de rechazo**. | **Gestionar reservas** |
| **Cancelar** | Reserva pendiente o confirmada | La cancela y libera su franja. Pide el **Motivo de cancelación**. | **Cancelar cualquier reserva** |
| **No presentado** | Reserva **Confirmada** que ya ha empezado | Marca que la persona no vino. | **Gestionar reservas** |
| **Editar** | Siempre | Abre la reserva. | **Gestionar reservas** |

Cada acción pide confirmación. Según la configuración, la persona recibe el correo «Reserva confirmada», «Reserva rechazada» o «Reserva cancelada», con el motivo. **No presentado** no envía correo.

Desde el panel puedes cancelar una reserva aunque ya haya empezado.

## Editar una reserva

1. Abre el menú **⋯** de la fila y pulsa **Editar**.
2. Cambia lo que necesites y pulsa **Guardar cambios**.

Puedes cambiar:

- El **Espacio**, la **Fecha**, la **Hora de inicio** y la **Hora de fin**, para moverla. La nueva franja debe coincidir con los turnos del espacio y no solaparse con otra reserva. No puedes poner un inicio que ya haya pasado; si mantienes el inicio, una reserva en curso sí puede alargarse o cambiar de espacio. Las reservas ya terminadas (completadas, canceladas, rechazadas, no presentadas o caducadas) se mueven sin comprobaciones, para corregir el registro.
- El **Estado**, solo a los que permite el estado actual. **Caducada** no se elige a mano: la pone la web sola. Es la única forma de marcar a mano una reserva confirmada como **Completada**. El **Motivo de cancelación** se usa como motivo si la cancelas o la rechazas desde aquí.
- Las **Notas del administrador**: solo las ve quien tiene **Gestionar reservas**, también en el calendario de la web.
- El evento, los datos del formulario y, si tu web tiene mesas de rol, la mesa o campaña asociada.

Si mueves una reserva pendiente o confirmada, a la persona le llega el correo «Reserva modificada» con el espacio, la fecha y el horario nuevos y los de antes, si **Notificar al cambiar el espacio, la fecha o el horario de una reserva** está activado en la configuración. Para no enviarlo en un guardado concreto (por ejemplo, al corregir un error al teclear), apaga **Avisar del cambio por correo** antes de pulsar **Guardar cambios**. Mover una reserva ya terminada no envía nada.

## Borrar una reserva

Dentro de la reserva, pulsa **Borrar** y confirma. Desaparece del todo, sin correo. Para dejar constancia, mejor cancélala o recházala.

## Si algo no sale bien

- **«La franja se solapa con otra reserva del espacio.»**: elige otra hora o mueve antes la otra reserva.
- **«La franja no coincide con los turnos del espacio.»**: las horas deben empezar y acabar en los turnos del horario (un bloque entero, en un espacio por bloques).
- **«La franja tiene menos turnos consecutivos de los que exige el espacio.»** o **«… más turnos consecutivos de los que permite el espacio.»**: ajusta las horas al mínimo y al máximo del espacio.
- **«Esa franja ya ha empezado.»**: el nuevo inicio ya ha pasado. Elige uno posterior o deja el que tenía.
- **«Este espacio no admite reservas en este momento.»**: el espacio de destino no está **Activo**.
- **«Transición de estado no permitida»**: ese cambio de estado no es posible desde el estado actual.
- **«Ahora mismo se está procesando otra reserva. Inténtalo de nuevo en unos segundos.»**: repite en un momento.

[[action:filament.admin.resources.bookings.index|Ver las reservas]]
