<?php

class Producto
{
    public $id;
    public $estado;
    public $referencia;
    public $nombre;
    public $categorias;
    public $descripcion_corta;
    public $descripcion;
    public $precio_sin_iva;
    public $precio_con_iva;
    public $ultima_modificacion;
    public $tallas;
    public $colores;
    public $imagenes;
    public $fabricante;
    
    public function __construct()
    {
        $this->fabricante = 'Lencería Finíssima';
    }

}


