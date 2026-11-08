---
id: venue-bookings.panel.schedule
title: Definir el horario de un espacio
summary: Elige entre turnos horarios y bloques horarios, añade los días de apertura y usa los bloques predefinidos para ir más rápido.
category: activities
audience: panel
order: 30
keywords: [horario, horarios, turnos, turno, bloques, bloque horario, turnos horarios, bloques horarios, duración del turno, turnos consecutivos, mínimo de turnos, máximo de turnos, días de apertura, hora de apertura, hora de cierre, medianoche, abrir de noche, añadir varios días, mañana, tarde]
related: [venue-bookings.panel.spaces, venue-bookings.panel.settings, venue-bookings.panel.overview]
conditions:
  permissions:
    all: ['venuebookings:bookings.manage']
---
El horario decide qué días y a qué horas se puede reservar un espacio. Está en la pestaña **Horario** del espacio. Al terminar, pulsa **Guardar cambios** (o **Crear**, si el espacio es nuevo).

## Elegir el modo de programación

En **Modo de programación**, elige:

| Modo | Cómo se reserva |
|---|---|
| **Turnos horarios** | Cada día abierto se divide en turnos de la misma duración. La gente elige uno o varios turnos seguidos. |
| **Bloques horarios** | Cada día tiene uno o varios bloques con nombre (por ejemplo, «Mañana» y «Tarde»). La gente elige un bloque entero. |

## Turnos horarios

1. Pon la **Duración del turno (minutos)**, entre 5 y 480.
2. Pon los **Turnos consecutivos mínimos** y **máximos** que puede ocupar una reserva. Con un mínimo de 2 y turnos de una hora, nadie puede reservar menos de dos horas.
3. En **Horarios por día**, pulsa **Añadir día** y rellena el **Día de la semana**, la **Hora de apertura** y la **Hora de cierre** (formato `HH:MM`). Deja **Habilitado** activado.
4. Repite con cada día que abra el espacio.

En este modo cuenta una franja por día de la semana: si añades dos filas para el mismo día, solo vale la primera. Si la duración no divide justo las horas de apertura, el último turno acaba después del cierre.

## Bloques horarios

1. En **Horarios por día**, pulsa **Añadir día**.
2. Elige el **Día de la semana**, escribe el **Nombre del bloque** y pon su **Hora de apertura** y **Hora de cierre**.
3. Añade una fila por cada bloque: un mismo día puede tener varios.

## Añadir varios días a la vez

1. Pulsa **Añadir varios días**, junto a **Horarios por día**.
2. Si en la configuración hay bloques predefinidos, elige uno en **Bloque horario**: rellena las horas y el nombre.
3. Marca los días en **Día de la semana**, pon las horas y confirma.

Se añade una fila por cada día marcado. Los bloques predefinidos se preparan en **Sistema** → **Configuración** → **Reservas** → **Bloques horarios predefinidos**.

## Abrir hasta pasada la medianoche

Si la **Hora de cierre** es anterior a la de apertura (por ejemplo, de 22:00 a 02:00), el espacio abre hasta el día siguiente. En la web, los turnos de después de las doce llevan **+1 día** y la reserva queda guardada en el día en que empieza.

## Cerrar un día

Desactiva **Habilitado** en su fila, o bórrala. Ese día deja de ofrecer turnos. Para cerrar el espacio entero, cambia su **Estado**.

> [!IMPORTANT]
> Cambiar el horario no mueve ni cancela las reservas que ya existen. Si una deja de encajar, muévela o cancélala desde **Reservas**.

[[action:filament.admin.resources.bookable-resources.index|Ver los espacios]]
