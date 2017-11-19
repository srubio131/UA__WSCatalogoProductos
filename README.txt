# Servicio Web para sincronización catálogos entre tiendas virtuales
# Autor: Sergio Rubio
# Email: s.rubio131@gmail.com

Los pasos a seguir para la correcta INSTALACIÓN del servicio Web de sincronización de catálogo son los siguientes:

1. Ejecutar el script .sql de instalación (Crea la tabla de registros y un índice).
2. Copiar el directorio "ServicioWebCatalogo" en el servidor Web de la tienda online.
   Opcionalmente se puede cambiar el nombre por otro que se quiere mostrar en la URI de consulta.
3. Configurar "display_error=off" en el archivo php.ini (evitar que se muestren errores por pantalla, si se    ejecuta desde un navegador Web).
4. Se aconseja añadir una tarea en el servidor (cron) para borrar los archivos generados de un día. Si no se quiere almacenar los archivos generados para cada día.

Los pasos a seguir para la correcta DESINSTALACIÓN del servicio Web de sincronización de catálogo son los siguientes:

1. Ejecutar el script .sql de desinstalación (Borra la tabla e índice asociado).
2. Eliminar el directorio "ServicioWebCatalogo" del servidor de la tienda virtual