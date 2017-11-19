# Crear tabla para almacenar registro de generaci�n de cat�logo por cada usuario en una fecha concreta
CREATE TABLE eya0t_registro_servicioWeb (
     username varchar(150),
     fultimaconsul date,
     email varchar(100) not null,
     numarchgen tinyint(4) unsigned not null,
     PRIMARY KEY (username, fultimaconsul));
  
# Crear �ndice por usuario y ultima fecha de consulta
CREATE INDEX idx_username_fultimaconsul ON eya0t_registro_servicioWeb (username, fultimaconsul);
