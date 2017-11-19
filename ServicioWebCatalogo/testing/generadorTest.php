<?php

/**
 * Tests para probar que se accede a la base de datos, se obtienen los productos y
 * por último se genera el archivo correspondiente para cada tipo. (No se comprueba su contenido)
 */

define("_WS_", 1);
require_once dirname(__FILE__).'/../generador.php';
require_once dirname(__FILE__).'/../helper/Producto.class.php';

class generadorTest extends PHPUnit_Framework_TestCase
{
    private $fecha_a_partir;
    private $incremento_precio;
    private $productos;
    
    protected function setUp()
    {       
        $this->fecha_a_partir = strtotime('2015-02-01');
        $this->incremento_precio = array('Lencería Femenina'=>1, 'Bodys'=>1.05, 'Chemises'=>1.05, 'Conjuntos'=>1.05, 'Corsets'=>1.05, 'Leggins'=>1.05, 'Medias'=>1.20,
                                        'Picardias'=>1.10, 'Tallas Grandes'=>1.05, 'Braguitas'=>1.05, 'Tangas'=>1.05, 'Tangas y Braguitas'=>1.05,
                                        'Disfraces'=>1.05, 'Hombre'=>1.05, 'Mujer'=>1.05, 'Complementos'=>1.05, 'Enaguas-Tutus'=>1.05, 'Guantes'=>1.05,
                                        'Tapa Pezones'=>1.05, 'Nuevas Sensaciones'=>1.05, 'Aceites'=>1.05, 'Pinturas'=>1.05, 'Velas'=>1.05,
                                        'Verano'=>1.50, 'Batas'=>1.50, 'Bikinis'=>1.50, 'Vestidos'=>1.05);
        
        $this->testObtenerProductos();
    }
    
    protected function tearDown(){        
    }
    
    /**
     * i_obtener_productos($fecha_a_partir, $incrementos_precio)
     */
    public function testObtenerProductos()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_obtener_productos");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        $producto_esperado = new Producto();
        $producto_esperado->id = "835";
        $producto_esperado->estado = true;
        $producto_esperado->referencia = "LFNSSM-B-101";
        $producto_esperado->nombre = "Bikini besame";
        $producto_esperado->categorias = array(array("Verano","Bikinis"));
        $producto_esperado->descripcion_corta = "Espectacular bikini con flecos y anillas doradas en los laterales de la braguita.";
        $producto_esperado->descripcion = "<p>Bikini flecos con braguita.  </p>
<p>Sensacional bikini sin aro y tirantes que se enganchan al cuello en forma de V.</p>
<p>Elige el color que más te guste.</p>
<p>79% Naylon.</p>
<p>21% Elastano.</p>";
                
        $producto_esperado->precio_sin_iva = "13.50";
        $producto_esperado->precio_con_iva = "16.34";
        $producto_esperado->ultima_modificacion = "2015-02-16 18:17:30";
        $producto_esperado->tallas = array("S","M","L");
        $producto_esperado->colores = array("Blanco","Rojo","Naranja","Azul Celeste");
        $producto_esperado->imagenes = array("http://www.lenceriafinissima.com/images/stories/virtuemart/product/b-1011.jpg",
                                             "http://www.lenceriafinissima.com/images/stories/virtuemart/product/b-1013.jpg",
                                             "http://www.lenceriafinissima.com/images/stories/virtuemart/product/b-1014.jpg",
                                             "http://www.lenceriafinissima.com/images/stories/virtuemart/product/b-10119.jpg");
        
        $this->productos = $method->invoke($gen, $this->fecha_a_partir, $this->incremento_precio);
        $obtenido = $this->productos[0];
        
        $this->assertEquals($producto_esperado, $obtenido);        
    }
    
    /**
     * generarCSVPrestashop($fecha_a_partir, $incrementos_precio)
     * @depends testObtenerProductos
     */
    public function testgenerarCSVPrestashop()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_generarCSVPrestashop");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        // Nombre del archivo esperado
        $nombre_esperado = 'CSVPrestashop'.date('dmY').'.zip';
        
        // Nombre del archivo generado devuelto por el método
        $path = $method->invoke($gen, $this->productos);
        $nombre = basename($path);
        
        // Comprobar que el archivo exista realmente
        $this->assertTrue(file_exists($path));
        
        // Si los paths coinciden entonces el archivo ha sido generado
        $this->assertEquals($nombre_esperado, $nombre);   
    }
            
    /**
     * generarCSVGenerico($fecha_a_partir, $incrementos_precio)
     * @depends testObtenerProductos
     */
    public function testgenerarCSVGenerico()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_generarCSVGenerico");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        // Nombre del archivo esperado
        $nombre_esperado = 'csv'.date('dmY').'.csv';
        
        // Nombre del archivo generado devuelto por el método
        $path = $method->invoke($gen, $this->productos);
        $nombre = basename($path);
        
        // Comprobar que el archivo exista realmente
        $this->assertTrue(file_exists($path));
        
        // Si los paths coinciden entonces el archivo ha sido generado
        $this->assertEquals($nombre_esperado, $nombre);   
    }
    
    /**
     * generarCSVEkmTienda($incrementos_precio)
     * @depends testObtenerProductos
     */
    public function testgenerarCSVEkmTienda()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_generarCSVEkmTienda");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        // Nombre del archivo esperado
        $nombre_esperado = 'csvekmTienda'.date('dmY').'.csv';
        
        // Nombre del archivo generado devuelto por el método
        $path = $method->invoke($gen, $this->productos);
        $nombre = basename($path);
        
        // Comprobar que el archivo exista realmente
        $this->assertTrue(file_exists($path));
        
        // Si los paths coinciden entonces el archivo ha sido generado
        $this->assertEquals($nombre_esperado, $nombre);   
    }
    
    /**
     * generarXML($fecha_a_partir, $incrementos_precio)
     * @depends testObtenerProductos
     */
    public function testgenerarXML()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_generarXML");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        // Nombre del archivo esperado
        $nombre_esperado = 'productos'.date('dmY').'.xml';
        
        // Nombre del archivo generado devuelto por el método
        $path = $method->invoke($gen, $this->productos);
        $nombre = basename($path);
        
        // Comprobar que el archivo exista realmente
        $this->assertTrue(file_exists($path));
        
        // Si los paths coinciden entonces el archivo ha sido generado
        $this->assertEquals($nombre_esperado, $nombre);   
    }
    
    /**
     * generarJSON($fecha_a_partir, $incrementos_precio)
     * @depends testObtenerProductos
     */
    public function testgenerarJSON()
    {
        $reflection_class = new ReflectionClass("Generador");
        $method = $reflection_class->getMethod("_generarJSON");
	$method->setAccessible(true);
        
        // Se crea un objeto para poder invocar al metodo mediante Reflexion
        $gen = new Generador;
        
        // Nombre del archivo esperado
        $nombre_esperado = 'json'.date('dmY').'.json';
        
        // Nombre del archivo generado devuelto por el método
        $path = $method->invoke($gen, $this->productos);
        $nombre = basename($path);
        
        // Comprobar que el archivo exista realmente
        $this->assertTrue(file_exists($path));
        
        // Si los paths coinciden entonces el archivo ha sido generado
        $this->assertEquals($nombre_esperado, $nombre);   
    }
}