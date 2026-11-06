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

1. En el menú lateral, abre **Reservas del local** y elige **Espacios**.
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
- **Descripción** y **Capacidad**: son información interna. La web no las muestra ahora mismo y la capacidad no limita el **Número de participantes** de las reservas.

En la lista de espacios, la columna **Reservas** cuenta todas las reservas de cada espacio, también las pasadas.

## Borrar un espacio

Dentro del espacio, pulsa **Borrar** y confirma.

> [!WARNING]
> Al borrar un espacio se borran también su horario y todas sus reservas, pasadas y futuras, sin avisar a nadie. Si solo quieres dejar de ofrecerlo, cámbialo a **Inactivo**.

[[action:filament.admin.resources.bookable-resources.index|Ver los espacios]]
