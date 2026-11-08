---
id: venue-bookings.panel.spaces
title: Crear y configurar un espacio
summary: Da de alta un espacio reservable, decide si admite reservas y en qué orden sale en la web, y bórralo cuando ya no exista.
category: activities
audience: panel
order: 20
keywords: [espacio, espacios, crear espacio, nuevo espacio, sala, salas, mesa del local, zona, espacio reservable, activo, inactivo, en mantenimiento, cerrar sala, orden, capacidad, descripción, borrar espacio]
related: [venue-bookings.panel.schedule, venue-bookings.panel.booking-fields, venue-bookings.panel.overview]
conditions:
  permissions:
    all: ['venuebookings:bookings.manage']
---
Un espacio es lo que la gente reserva: una sala, una mesa o una zona del local. Cada uno tiene tres pestañas: **Información básica**, **Horario** y **Campos de reserva**.

## Crear un espacio

1. En el menú lateral, abre **Actividades** → **Reservas**; **Espacios** aparece debajo de **Reservas**.
2. Pulsa **Crear espacio**.
3. En **Información básica**, escribe el **Nombre**. Es lo que la gente ve en la web.
4. Elige el **Estado** (lo explica la sección siguiente).
5. Si quieres, rellena **Descripción**, **Capacidad** y **Orden**.
6. En la pestaña **Horario**, define cuándo se puede reservar. Lo explica la guía «Definir el horario de un espacio».
7. En la pestaña **Campos de reserva**, elige qué datos se piden al reservar. Lo explica la guía de campos.
8. Pulsa **Crear**.

Sin horario, el espacio sale en la web pero no ofrece ningún turno.

## Estado

| Estado | Qué pasa |
|---|---|
| **Activo** | Sale en la página de reservas y admite reservas nuevas. |
| **Inactivo** | No sale en la web ni admite reservas nuevas. |
| **En mantenimiento** | Igual que **Inactivo**: úsalo para un cierre temporal. |

Cerrar un espacio no toca sus reservas: siguen como estaban. Revísalas en **Reservas** filtrando por **Espacio**, y cancélalas si hace falta.

## Los demás campos

- **Orden**: los números más bajos salen primero en la web. Con dos a cuatro espacios activos, la web los muestra como pestañas; con cinco o más, como una lista.
- **Descripción**: sale en la página de reservas, encima del calendario, cuando se elige el espacio. Los saltos de línea se respetan.
- **Capacidad**: cuántas personas caben como máximo en una reserva. Sale en la página de reservas («Capacidad: hasta … personas») y, si el espacio pregunta el **Número de participantes**, no se acepta un número mayor, ni en la web ni al crear o editar una reserva en el panel. Si cambias la capacidad, las reservas que ya pasaban de ella no cambian, pero para guardarlas en el panel hay que bajar su **Número de participantes**. Déjala vacía si no quieres límite.

En la lista de espacios, la columna **Reservas** cuenta todas las reservas de cada espacio, también las pasadas.

## Borrar un espacio

Dentro del espacio, pulsa **Borrar**. La ventana te dice cuántas reservas se borrarán (y cuántas de ellas son futuras) y te pide marcar la casilla de confirmación antes de poder confirmar.

> [!WARNING]
> Al borrar un espacio se borran también su horario y todas sus reservas, pasadas y futuras, sin avisar a nadie, y no se puede deshacer. Si solo quieres dejar de ofrecerlo, cámbialo a **Inactivo**.

[[action:filament.admin.resources.bookable-resources.index|Ver los espacios]]
