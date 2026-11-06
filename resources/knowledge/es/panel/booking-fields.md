---
id: venue-bookings.panel.booking-fields
title: Elegir los campos del formulario de reserva
summary: Decide qué datos se piden al reservar cada espacio, si son obligatorios y quién los ve, y añade campos propios.
category: activities
audience: panel
order: 40
keywords: [campos, campos de reserva, formulario de reserva, nombre de la actividad, número de participantes, teléfono de contacto, notas, campos personalizados, campo propio, obligatorio, visibilidad, público, usuarios registrados, con permiso, solo administradores, selección, opciones, plantilla por defecto]
related: [venue-bookings.panel.spaces, venue-bookings.panel.settings]
conditions:
  permissions:
    any: ['venuebookings:bookings.manage', 'venuebookings:bookings.settings']
---
Cada espacio decide qué se pregunta al reservarlo. Los campos están en la pestaña **Campos de reserva** del espacio, que tiene dos secciones: **Campos predefinidos** y **Campos personalizados**.

## Campos predefinidos

Son cuatro: **Nombre de la actividad**, **Número de participantes**, **Teléfono de contacto** y **Notas**. Para usar uno:

1. Activa su interruptor.
2. Si debe rellenarse siempre, activa **Obligatorio**.
3. Elige su **Visibilidad** (la explica la sección siguiente).

## Visibilidad

La **Visibilidad** decide quién rellena el campo en la web y quién ve su valor al pulsar una reserva en el calendario:

| Visibilidad | Quién |
|---|---|
| **Público** | Todo el mundo, también quien mira el calendario sin cuenta. |
| **Usuarios registrados** | Quien ha iniciado sesión. |
| **Con permiso** | Quien tiene **Gestionar reservas**. |
| **Solo administradores** | Solo la administración. |

Quien no ve un campo tampoco lo rellena: un campo **Con permiso** no sale en el formulario de una persona sin ese permiso. La propia persona siempre ve sus datos en **Mis reservas**.

> [!IMPORTANT]
> Lo que se escribe en un campo **Público** lo puede leer cualquiera en el calendario. Usa **Usuarios registrados** o más para teléfonos y otros datos personales.

## Campos personalizados

1. En **Campos personalizados**, pulsa **Añadir campo**.
2. Escribe la **Clave** (un nombre interno sin espacios, distinto en cada campo) y la **Etiqueta** que verá la gente.
3. Elige el **Tipo**: **Texto**, **Texto largo**, **Número**, **Selección** o **Interruptor**.
4. Para **Selección**, escribe las **Opciones**, una por línea.
5. Marca **Obligatorio** si hace falta y elige la **Visibilidad**.

Guarda el espacio para aplicar los cambios. Las reservas que ya existen conservan lo que se rellenó.

## La plantilla de la configuración

En **Configuración de reservas** están las secciones **Campos predefinidos (plantilla por defecto)** y **Campos personalizados (plantilla por defecto)**. Se usan en los espacios que no tienen campos propios guardados. Un espacio creado desde el panel guarda siempre los suyos (al crearlo empiezan desactivados), así que la plantilla no se copia en él: configura los campos en cada espacio.

## Mesas y campañas

Si tu web tiene mesas de rol, **Configuración de reservas** → **Asociaciones** permite añadir al formulario los campos **Mesa de juego** y **Campaña**, cada uno con su visibilidad. En la web, cada persona elige entre las mesas y campañas publicadas que ha creado.

[[action:filament.admin.resources.bookable-resources.index|Ver los espacios|if=venuebookings:bookings.manage]]
