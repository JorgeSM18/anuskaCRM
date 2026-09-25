# Protección de datos (RGPD) — Anuska Complementos CRM

Nota práctica sobre el tratamiento de datos personales en el CRM. No es asesoramiento
legal; si la tienda tiene dudas serias, que consulte con un asesor.

## Qué datos personales hay

- **Contactos de proveedores** (`Contact`): nombre, cargo, teléfono y email de personas
  que trabajan en las marcas/proveedores. Son datos personales de terceros.
- **Usuarios del CRM** (`User`): email y nombre de los empleados que usan la aplicación.

No se guardan datos de clientes finales de la tienda, ni categorías especiales de datos.

## Base legal

El tratamiento de los datos de los contactos comerciales se apoya en el **interés
legítimo** de mantener la relación comercial (art. 6.1.f RGPD): son personas de contacto
en empresas proveedoras, tratadas en un contexto profesional.

## Dónde viven los datos

- En la **base de datos** del servidor (PostgreSQL), en la propia infraestructura de la
  tienda o su proveedor de hosting.
- Los **documentos** subidos (facturas, etc.) en la carpeta `storage/` del servidor.
- **No se envían a terceros** ni a servicios externos. La aplicación es privada y sin
  registro público.

## Derechos y cómo atenderlos

- **Acceso / rectificación:** los datos de un contacto se editan desde su proveedor en el CRM.
- **Supresión:** un contacto se puede **borrar** desde el CRM. Un proveedor se **desactiva**
  (no se borra) para conservar el histórico; si hiciera falta borrarlo, al eliminarlo se
  eliminan en cascada sus contactos, pedidos, facturas, comunicaciones, citas y documentos.

## Medidas de seguridad aplicadas

- Acceso solo con **usuario y contraseña** (contraseñas cifradas con el algoritmo por
  defecto de Symfony). Sin registro público.
- Roles: usuario normal y administrador; solo el administrador gestiona usuarios.
- Los **documentos** no son accesibles por URL directa: requieren sesión iniciada.
- Recomendado: **HTTPS** en producción y **copias de seguridad** cifradas (ver `deploy.md`).

## Conservación

Los datos se conservan mientras dure la relación comercial. Revisar periódicamente y
borrar los contactos de proveedores con los que ya no se trabaja.
