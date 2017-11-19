<?php

// No está permitido acceder al archivo directamente.
defined("_WS_") or die;

/**
 * Consideraciones
 * -------------------|
 * - Si se cambia el Array de categorias en _obtenerCategorias(), habría que cambiarlo tambien en el CSV de categorias _exportarCategorias()
 * - Las categorias del producto deben estar completas. Ej. Lenceria Femenina - Corsets - Corsets bajopecho (Lo mismo pero sin Lenceria Femenina no sirve).
 * - Los productos tienen que tener una categoria asociada para que se exporten y el incremento de precio sea correcto.
 * - La fecha a partir la obtenemos de la base de datos (tabla: eya0t_registro_servicio_web). Tiene que estar previamente creada en la db
 */

// Número máximo de veces que está permitido que se genere el catálogo en un día
const MAX_VECES_GENERADO_HOY = 3;

require_once 'helper/Producto.class.php';
require_once 'config/conf.class.php';
require_once 'helper/logger.php';
require_once 'helper/db.class.php';
require_once 'lib/Utils.php';

/**
* Comparador entre referencias
* @param String $a Referencia primera
* @param String $b Referencia segunda
* @return Int Un número N. Siendo N, N < 0 si $a<$b; N == 0 si $a==$b; N mayor a 0 si $a mayor que $b
*/
function compararPorReferencia($a, $b)
{
   return strcasecmp($a->referencia, $b->referencia);
}

///////////////////////////////////////////////////////////////////////////////
//                              GENERADOR                                    //
///////////////////////////////////////////////////////////////////////////////
class Generador
{
    // Instancia de configuración
    private $_conf;
    
    // Instancia del logger
    private $_log;
    
    // Ruta del archivo generado
    private $_path;
    
    // Conexión a la base de datos
    private $_db;
    
    /**
     * Constructor. Inicializa variables.
     */
    public function __construct()
    {        
        $this->_conf = Conf::getInstance();  
        
        $this->_log = new Logger($this->_conf->getLogPath());
        $this->_path = $this->_conf->getGenPath();        
        $this->_db = Db::getInstance();
        
        if ($this->_db === null)
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): No se pudo conectar a la Base de datos', 'Error');
    }
    
    /**
     * Procesa la generación del archivo de respuesta
     * @param String $tipo Tipo del archivo a generar
     * @param Array $incremento_precio Array con las categorías y el incremento de precio de los productos
     * @return Mixed False si en caso de error. String con el path del archivo generado si se ha generado con éxito 
     */
    public function startGen($tipo, $incremento_precio, $usuario)
    {
        $path = false;
        $correct_types = array('csv_generico', 'csv_prestashop', 'csv_ekmtienda', 'application/xml', 'application/json');        
        
        if (!empty($tipo) & in_array($tipo, $correct_types))
        {        
            $numVecesGenerado = $this->_obtenerNumVecesGenerado($usuario['username']);
            if ($numVecesGenerado < MAX_VECES_GENERADO_HOY)
            {            
                // Obtener la fecha a partir de la cual obtener los productos
                $fecha_a_partir = $this->_obtenerFechaUltimaConsulta($usuario['username']);

                // Obtener los productos de la base de datos
                $productos = $this->_obtener_productos($fecha_a_partir, $incremento_precio);   
                
                if (!empty($productos))
                {
                    switch ($tipo)
                    {
                        case 'csv_generico':
                            $this->_path = $this->_generarCSVGenerico($productos);
                            break;
                        case 'csv_prestashop':
                            $this->_path = $this->_generarCSVPrestashop($productos);
                            break;
                        case 'csv_ekmtienda':
                            $this->_path = $this->_generarCSVEkmTienda($productos);
                            break;
                        case 'application/xml':
                            $this->_path = $this->_generarXML($productos);
                            break;
                        case 'application/json':
                            $this->_path = $this->_generarJSON($productos);
                            break;
                    }
                    $path = $this->_path;                    
                    if ($path !== false) {                        
                        $this->_grabarRegistroConsulta($usuario); // Registrar la consulta al servicio Web solo si se ha generado el archivo
                    }
                } else {
                    $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): No hay productos a partir de la fecha seleccionada','Error'); 
                }
            } else {
                $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Se ha superado el número máximo de intentos hoy para el usuario: '.$usuario['username'].' desde la IP: '.Utils::obtener_ip(),'Error'); 
            }            
        } else {
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Tipo de datos inválido: $TIPO= "'.$tipo.'"','Error');  
        }
        
        return $path;
    }
    
    /**
     * Obtiene la última fecha de consulta para un usuario
     * @param String $username Usuario que lanza la consulta de generación del catálogo
     * @return Date Fecha de última consulta de un usuario
     */
    private function _obtenerFechaUltimaConsulta($username)
    {
        $fecha_ultima_consulta = strtotime('2013-01-01');// Si es la primera vez que el usuario genera el catálogo
        
        $result = $this->_db->query("SELECT fultimaconsul FROM eya0t_registro_servicioWeb WHERE username='".
                                    $username."' ORDER BY fultimaconsul DESC LIMIT 1");
        if ($result === false) {
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado la obtención de la última fecha de consulta a la base de datos','Critical');
        } else {
            if (mysqli_num_rows($result) > 0) {
                $rs = $this->_db->getAssocRow($result);
                $fecha_ultima_consulta = strtotime($rs['fultimaconsul']);
            } else {
                $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): No hay registros en eya0t_registro_servicioWeb para obtener la última fecha','Error');
            }            
            mysqli_free_result($result);
        }
        
        return $fecha_ultima_consulta;
    }
    
    /**
     * Obtener la cantidad de intentos de hoy para un usuario concreto
     * @param Strin $username Usuario que realiza la consulta
     * @return Int Numero de veces generado
     */
    private function _obtenerNumVecesGenerado($username)
    {      
        $numVecesGenerado = 0;
        $result = $this->_db->query("SELECT numarchgen FROM eya0t_registro_servicioWeb WHERE username='".
                                    $username."' and fultimaconsul='".date('Y-m-d')."' LIMIT 1");
        if ($result === false) {
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado la obtención de la cantidad de intentos de hoy','Critical');
        } else {
            if (mysqli_num_rows($result) > 0) {
                $rs = $this->_db->getAssocRow($result);
                $numVecesGenerado = $rs['numarchgen'];
            } else {
                $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): No hay registros en eya0t_registro_servicioWeb para obtener la cantidad de intentos de hoy','Warning');
                $numVecesGenerado = MAX_VECES_GENERADO_HOY;
            }
            mysqli_free_result($result);
        }
        
        return $numVecesGenerado;
    }
    
    /**
     * Graba en la base de datos un registro informando de la petición de consulta por parte de un usuario
     * @param Array $usuario Datos de usuario
     * @return boolean True si se ha grabado con éxito. False en caso contrario.
     */
    private function _grabarRegistroConsulta($usuario)
    {        
        $result = $this->_db->query("INSERT INTO eya0t_registro_servicioWeb (username, fultimaconsul, email, numarchgen) ".
                                    "VALUES ('".$usuario['username']."','".date('Y-m-d')."','".$usuario['email']."',1) ".
                                    "ON DUPLICATE KEY UPDATE numarchgen=numarchgen+1");
        if ($result !== true) {
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado la grabación del registro de consulta en la base de datos','Critical');
        }        
        return $result;
    }
    
    /**
     * Dada una lista de categorias de un producto, obtiene los nombres de las categorías a las que pertenece con su
     * correspondencia padre - hija
     * @param Array $hash_categorias Array con todas las categorías de la tienda
     * @param Array $hash_relaciones Array con las relaciones entre categorías (padres e hijas)
     * @param Array $ids_categorias Array con todas las categorías a las que pertenece un producto
     * @return Array Array de arrays con todas las categorias de un producto
     */
    private function _relaciona_categorias($hash_categorias, $hash_relaciones, $ids_categorias)
    {
        $categorias = array();

        if (count($ids_categorias) > 0)
        {
            // Quedarme solo con los id que son hojas (los ultimos)
            for ($i=0; $i < count($ids_categorias) ; $i++)
            {
                // NO es categoria hoja (no hay category_parent_id con ese id). Tampoco es categoria "Corsets" otro nivel mÃÂ¡s (tipos de corsets especiales)
                $id_categoria = $ids_categorias[$i];
                if (in_array($id_categoria, $hash_relaciones) && $id_categoria!=3)
                {
                    unset($ids_categorias[$i]);
                }
            }

            // (LenceriaFemenina-Corsets). Para cuando no tienen subcategoria asociada
            if (count($ids_categorias) > 1 & in_array(3, $ids_categorias))
                array_shift($ids_categorias);

            // Para cada hoja busco sus padres y los añado a categorias
            foreach ($ids_categorias as $id_categoria)
            {             
                $aux = array();
                while ($id_categoria != 0)
                {
                    array_push($aux, $hash_categorias[intval($id_categoria)]);
                    $id_categoria = $hash_relaciones[intval($id_categoria)];
                }
                array_push($categorias, array_reverse($aux));
            }
        }    

        return $categorias;
    }

    /**
     * Recupera de la base de datos todos los productos a partir de una fecha, con una categoría de la lista y con el
     * incremento en el precio
     * @param Int $fecha_a_partir
     * @param Array $incrementos_precio Array asociativo con el incremento de precio por cada categoria. array(['Verano']=>0.20, ['Corset']=>0.10)
     * @return Array Array con todos los productos
     **/
    private function _obtener_productos($fecha_a_partir, $incrementos_precio)
    {
        $productos = array();

        // virtuemart_product_id, product_sku, descripciones, nombre y precio
        $query1 = 'select p1.virtuemart_product_id, p1.product_sku, p1.published, p2.product_s_desc, p2.product_desc, p2.product_name, p3.product_price, p1.modified_on' . PHP_EOL .
                  'from eya0t_virtuemart_products p1, eya0t_virtuemart_products_es_es p2, eya0t_virtuemart_product_prices p3' . PHP_EOL .
                  'where p1.virtuemart_product_id=p2.virtuemart_product_id and p1.virtuemart_product_id=p3.virtuemart_product_id' . PHP_EOL .
                  'order by p1.virtuemart_product_id';

        // tallas y colores
        $query2 = 'select p1.virtuemart_product_id, p1.virtuemart_custom_id, p1.custom_value' . PHP_EOL .
                  'from eya0t_virtuemart_product_customfields p1, eya0t_virtuemart_products p2' . PHP_EOL .
                  'where p1.virtuemart_product_id=p2.virtuemart_product_id' . PHP_EOL .
                  'order by virtuemart_product_id';

        // urls de imagenes
        $query3 = 'select p1.virtuemart_product_id, p2.file_url' . PHP_EOL .
                  'from eya0t_virtuemart_product_medias p1, eya0t_virtuemart_medias p2' . PHP_EOL .
                  'where p1.virtuemart_media_id=p2.virtuemart_media_id';

        // categorias de los productos
        $query4 = 'select DISTINCT p1.virtuemart_product_id, p1.virtuemart_category_id' . PHP_EOL .
                  'from eya0t_virtuemart_product_categories p1' . PHP_EOL .
                  'order by p1.virtuemart_product_id';

        // id y nombres de categorias
        $query5 = 'select virtuemart_category_id, category_name from eya0t_virtuemart_categories_es_es' . PHP_EOL .
                 'order by virtuemart_category_id';

        // relaciones entre categorias
        $query6 = 'select category_parent_id, category_child_id from eya0t_virtuemart_category_categories';

        $result1 = $this->_db->query($query1);
        $result2 = $this->_db->query($query2);
        $result3 = $this->_db->query($query3);
        $result4 = $this->_db->query($query4);
        $result5 = $this->_db->query($query5);
        $result6 = $this->_db->query($query6);

        // Comprobar que se han realizado con éxito las consultas
        if ($result1 === false | $result2 === false | $result3 === false | $result4 === false | $result5 === false | $result6 === false)
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado la consulta a la base de datos','Critical');

        $rs2 = $this->_db->getAssocRow($result2);
        $rs3 = $this->_db->getAssocRow($result3);
        $rs4 = $this->_db->getAssocRow($result4);

        // Crear hashes de categorias
        // -------------------------------------------------
        $hash_categorias = array();
        $hash_relaciones = array();

        while ($rs5 = $this->_db->getAssocRow($result5))
            $hash_categorias[$rs5['virtuemart_category_id']] = $rs5['category_name'];

        while ($rs6 = $this->_db->getAssocRow($result6))
            $hash_relaciones[$rs6['category_child_id']] = $rs6['category_parent_id'];

        // Liberar resultados
        mysqli_free_result($result5);
        mysqli_free_result($result6);
        // -------------------------------------------------

        // Se considera actualización si la fecha es superior al 1/1/2015
        $es_actualizacion = true;
        if ($fecha_a_partir < strtotime('2015-01-01'))
            $es_actualizacion = false;
            
        while ($rs1 = $this->_db->getAssocRow($result1))
        {
            $fecha_ult_modificacion = strtotime($rs1['modified_on']);
            $id = $rs1['virtuemart_product_id'];
            $precio_sin_iva = $rs1['product_price'];
            $estado = filter_var($rs1['published'], FILTER_VALIDATE_BOOLEAN);

            // Categorias
            $ids_categorias = array();
            while ($rs4 & $id == $rs4['virtuemart_product_id'])
            {
                array_push($ids_categorias, $rs4['virtuemart_category_id']);
                $rs4 = $this->_db->getAssocRow($result4);
            }                        
            $categorias = $this->_relaciona_categorias($hash_categorias, $hash_relaciones, $ids_categorias);

            if(($es_actualizacion === false & $estado === true | $es_actualizacion === true) & $fecha_ult_modificacion > $fecha_a_partir
                & $precio_sin_iva != 0 & Utils::hay_elementos_en_array($categorias, array_keys($incrementos_precio)) === true)
            { 
                $producto = new Producto();
                $producto->id = $id;
                $producto->referencia = 'LFNSSM-' . mb_strtoupper($rs1['product_sku']);
                $producto->estado = $estado;
                $producto->descripcion_corta = $rs1['product_s_desc'];
                $producto->categorias = $categorias;

                // DescripciÃ³n
                $descripcion = $rs1['product_desc'];
                $primera_posicion = strpos($descripcion, 'images/');
                if ($primera_posicion !== false)
                {
                    $descripcion = substr($descripcion, 0, $primera_posicion)
                                       . "http://www.lenceriafinissima.com/"
                                       . substr($descripcion, $primera_posicion);
                }
                $producto->descripcion = $descripcion;
                $producto->nombre = $rs1['product_name'];

                // Calcular precio con incremento
                $incremento = 1;            
                if (!empty($categorias)) 
                {
                    foreach ($producto->categorias[0] as $categoria)
                    {
                        if (array_key_exists($categoria, $incrementos_precio) === true)
                        {
                            $incremento = $incrementos_precio[$categoria];
                            break;
                        }
                    }
                    $producto->precio_sin_iva = number_format($incremento*$precio_sin_iva, 2);
                }
                else
                {
                    $producto->precio_sin_iva = number_format($precio_sin_iva, 2);
                    $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): El producto [ID='.$producto->id.' | REFERENCIA='.$producto->nombre.'] no tiene categorÃ­as asociadas.','Error');
                }
                $producto->precio_con_iva = number_format($producto->precio_sin_iva*1.21, 2);

                // Tallas y colores
                $colores = array();
                $tallas = array();
                while ($rs2 & $producto->id == $rs2['virtuemart_product_id'])
                {
                    // Es color
                    if ($rs2['virtuemart_custom_id'] == 15)
                        array_push($colores, $rs2['custom_value']);
                    // Es talla
                    else if ($rs2['virtuemart_custom_id'] == 14)
                        array_push($tallas, $rs2['custom_value']);

                    $rs2 = $this->_db->getAssocRow($result2);
                }
                $producto->colores = $colores;
                $producto->tallas = $tallas;

                // Imagenes
                $imagenes = array();
                while ($rs3 & $producto->id == $rs3['virtuemart_product_id'])
                {
                    array_push($imagenes, 'http://www.lenceriafinissima.com/' . $rs3['file_url']);
                    $rs3 = $this->_db->getAssocRow($result3);
                }
                $producto->imagenes = $imagenes; 
                $producto->ultima_modificacion = date("Y-m-d H:i:s", $fecha_ult_modificacion);        
                array_push($productos, $producto);
            }
            else
            {
                // Avanzar el puntero de resultados de categorias hasta el siguiente producto
                while ($rs4 & $id == $rs4['virtuemart_product_id'])
                    $rs4 = $this->_db->getAssocRow($result4);
                // Avanzar el puntero de resultados de tallas y colores hasta el siguiente producto
                while ($rs2 & $id == $rs2['virtuemart_product_id'])
                   $rs2 = $this->_db->getAssocRow($result2);
                // Avanzar el puntero de resultados de imagenes hasta el siguiente producto
                while ($rs3 & $id == $rs3['virtuemart_product_id'])
                    $rs3 = $this->_db->getAssocRow($result3);
            }
        }

        // Ordenar el arraylist de productos por su 'product_sku'
        usort($productos, "compararPorReferencia");

        // Liberar resultados
        mysqli_free_result($result1);
        mysqli_free_result($result2);
        mysqli_free_result($result3);
        mysqli_free_result($result4);

        return $productos;
    }

    /**
     * Crea un archivo CSV con las categorías de la tienda.
     * @param String $path Ruta del archivo generado
     * @return Boolean True si se ha generado el archivo correctamente y False si no.
     */
    private function _exportarCategorias($path)
    {
        $output = fopen($path.'/categorias.csv', "w+b");    
        $separador = ';';
        $finlinea = PHP_EOL;

        fwrite($output, 'Nombre'.$separador.'Categoría Padre'.$finlinea);

        fwrite($output, 'Bodys'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Braguitas'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Chemises'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Conjuntos'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Corsets'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Corsets BajoPecho'.$separador.'Corsets'.$finlinea);
        fwrite($output, 'Corsets Cuero'.$separador.'Corsets'.$finlinea);
        fwrite($output, 'Corsets Tallas Grandes'.$separador.'Corsets'.$finlinea);
        fwrite($output, 'Corsets Vestido'.$separador.'Corsets'.$finlinea);
        fwrite($output, 'Leggins'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Medias'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Picardias'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Tallas Grandes'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Tangas'.$separador.'Lencería Femenina'.$finlinea);
        fwrite($output, 'Hombre'.$separador.'Disfraces'.$finlinea);
        fwrite($output, 'Mujer'.$separador.'Disfraces'.$finlinea);    
        fwrite($output, 'Enaguas-Tutus'.$separador.'Complementos'.$finlinea);
        fwrite($output, 'Guantes'.$separador.'Complementos'.$finlinea);
        fwrite($output, 'Tapa Pezones'.$separador.'Complementos'.$finlinea);
        fwrite($output, 'Bodys'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Chemises'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Conjuntos'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Corsets'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Guantes'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Picardias'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Tangas y Braguitas'.$separador.'Bodas'.$finlinea);
        fwrite($output, 'Aceites'.$separador.'Nuevas Sensaciones'.$finlinea);
        fwrite($output, 'Pinturas'.$separador.'Nuevas Sensaciones'.$finlinea);
        fwrite($output, 'Velas'.$separador.'Nuevas Sensaciones'.$finlinea);
        fwrite($output, 'Batas'.$separador.'Verano'.$finlinea);
        fwrite($output, 'Bikinis'.$separador.'Verano'.$finlinea);
        fwrite($output, 'Vestidos'.$separador.'Verano'.$finlinea);

        return fclose($output);    
    }

    /**
     * Genera las combinaciones para (colores y tallas) de un producto y las imprime en el CSV de combinaciones.
     * @param String $producto Producto a combinar
     * @param String $separador Separador entre atributos
     * @param String $finlinea Fin de línea del CSV
     * @param Resource $output_combinaciones Archivo CSV de combinaciones
     */
    private function _exportarCombinaciones($producto, $separador, $finlinea, $output_combinaciones)
    {
        $numTallas = count($producto->tallas);
        $numColores = count($producto->colores);
        $indice_color = 0;

        if ($numTallas>0 && $numColores>0)
        {
            for ($indice_talla=0; $indice_talla<$numTallas; $indice_talla++)
            {
                $linea = $producto->referencia . $separador . 'Talla:select,Color:select' . $separador .
                         $producto->tallas[$indice_talla] .','. $producto->colores[$indice_color] . $finlinea;

                fwrite($output_combinaciones, $linea);

                if ($numColores > 1)
                {
                    $indice_color++;
                    $numColores--;
                    $indice_talla--;
                }
                else
                {
                    $indice_color = 0;
                    $numColores = count($producto->colores);
                }
            }
        }
        else if ($numTallas > 0)
        {
            for ($indice_talla=0; $indice_talla<$numTallas; $indice_talla++)
            {
                $linea = $producto->referencia . $separador . 'Talla:select' . $separador . $producto->tallas[$indice_talla] . $finlinea;            
                fwrite($output_combinaciones, $linea);
            }
        }
    }

    /**
     * Genera un .zip con CSVs (categoría, combinaciones y productos)
     * @param Array $productos Productos recuperados de la base de datos
     * @return boolean|string False si ocurre un error. Ruta del archivo generado si se ha generado con éxito.
     */
    private function _generarCSVPrestashop($productos)
    {
        $fecha_actual = date('dmY');
        $nombre_directorio = '/CSVPrestashop'.$fecha_actual;
        $path_zip = $this->_path.$nombre_directorio.'.zip';
        $path = $this->_path.$nombre_directorio;
        if(!is_dir($path))
        {
            $carpeta_creada = mkdir($path, 0444);   // Crear la carpeta para almacenar todos los archivos
            if ($carpeta_creada === false)
                $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al crear la carpeta de CSV Prestashop','Critical');
        }


        $output_combinaciones = fopen($path.'/combinaciones'.$fecha_actual.'.csv', "w+b");
        $output_productos = fopen($path.'/productos'.$fecha_actual.'.csv', "w+b");    
        $separador = ';';
        $finlinea = PHP_EOL;

        fwrite($output_combinaciones, 'Referencia'.$separador.'Atributo'.$separador.'Valor'.$finlinea);
        fwrite($output_productos, 'Activo'.$separador.'Referencia'.$separador.'Nombre'.$separador.'Categoría'.$separador.
                                  'Descripción corta'.$separador.'Descripción'.$separador.'Precio sin IVA'.$separador.
                                  'Precio con IVA'.$separador.'ID impuesto'.$separador.'URL imágenes'.$separador.'Fabricante'.$separador.
                                  'Eliminar imágenes existentes'.$separador.'Fecha última modificación'.$finlinea);

        foreach ($productos as $p)
        {
            if ($estado = $p->estado === true)
                $this->_exportarCombinaciones($p, $separador, $finlinea, $output_combinaciones);
            else
                $estado=0;

            // Crea string con todas las categorias
            $categorias = '';
            foreach ($p->categorias as $cat)
                $categorias .= Utils::imprimirArrayConDelimitador($cat, ',') . ',';
            $categorias = trim($categorias, ',');

            fwrite($output_productos, $estado . $separador . $p->referencia . $separador . $p->nombre . $separador . $categorias .
                                      $separador . '"'.str_replace("\n", '<br>', $p->descripcion_corta).'"' . $separador .
                                      '"'.str_replace('"', '""', $p->descripcion).'"' . $separador . $p->precio_sin_iva . $separador .
                                      $p->precio_con_iva . $separador . '53' . $separador .
                                      Utils::imprimirArrayConDelimitador($p->imagenes, ',') . $separador . $p->fabricante . $separador . '1' . $separador .
                                      $p->ultima_modificacion . $finlinea);        
        }

        if ($this->_exportarCategorias($path) && fclose($output_combinaciones) && fclose($output_productos))
        {
            // Comprimir directorio
            $zip = new ZipArchive();
            if($zip->open($path_zip,ZIPARCHIVE::CREATE)===true)
            {
                Utils::comprimir_directorio($path, $zip);
                //$zip->addFile('tutorial.pdf');
                $zip->close();

                return $path_zip;
            }
        }
        else {
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al cerrar el archivo CSV Prestashop','Critical');
        }
        
        return false;
    }

    /**
     * Genera CSV generico con todos los datos de cada producto.
     * @param Array $productos Productos recuperados de la base de datos
     * @return boolean|string False si ocurre un error. Ruta del archivo generado si se ha generado con éxito.
     */
    private function _generarCSVGenerico($productos)
    { 
        $nombre_archivo = 'csv'.date('dmY').'.csv';
        $path = $this->_path.'/'.$nombre_archivo;
        $output = fopen($path, "w+");
        $separador = ';';
        $finlinea = PHP_EOL;
        $cabeceras = 'Estado'.$separador.'Referencia'.$separador.'Nombre'.$separador.'Categoría'.$separador.
                     'Descripción corta'.$separador.'Descripción'.$separador.'Tallas'.$separador.'Colores'.$separador.
                     'Precio sin IVA'.$separador.'Precio con IVA'.$separador.'URL imágenes'.$separador.'Fabricante'.$separador.
                     'Fecha última modificación'.$finlinea;

        fwrite($output, $cabeceras);

        foreach ($productos as $p)
        {
            if ($p->estado === true)
                $estado = 'Activo';
            else
                $estado = 'Descatalogado';

            // Crea string con todas las categorias
            $categorias = '';
            foreach ($p->categorias as $cat)
                $categorias .= Utils::imprimirArrayConDelimitador($cat, ',') . ',';
            $categorias = trim($categorias, ',');

            $linea = $estado . $separador . $p->referencia . $separador . $p->nombre . $separador . $categorias . $separador .
                    '"'.str_replace("\n", '<br>', $p->descripcion_corta).'"' . $separador . '"'.str_replace('"', '""', $p->descripcion).'"' . $separador .
                    Utils::imprimirArrayConDelimitador($p->tallas, ',') . $separador .
                    Utils::imprimirArrayConDelimitador($p->colores, ',') . $separador . $p->precio_sin_iva. $separador .
                    $p->precio_con_iva . $separador . Utils::imprimirArrayConDelimitador($p->imagenes, ',') . $separador .
                    $p->fabricante . $separador . $p->ultima_modificacion . $finlinea;

            fwrite($output, $linea);
        }

        if(fclose($output))
            return $path;
        else
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al cerrar el archivo CSV GenÃ©rico','Critical');

        return false;
    }

    /**
     * Imprime una linea del CSV para ekmTienda
     * @param Array $p Producto para imprimir. Cada vez que se llama a la funcion se elimina una talla o color.
     * @param String $separador Separador usado entre atributos
     * @param type $finlinea Fin de línea usado para cada registro
     * @param type $es_product True si es el producto (No tiene variantes, es decir, colores o tallas)
     * @param type $es_primer_product_variant True si es una variante.
     */
    private function _imprime_linea_csvekmtienda($p, $separador, $finlinea, $es_product, $es_primer_product_variant)
    {
        // Action
        /*if ($p->estado === true)
        {
            if (strtotime($p->ultima_modificacion) >= $fecha_a_partir)
                $Action = 'Add Product';
            else
                $Action = 'Edit Product';
        }
        else
            $Action = 'Delete Product';*/

        if ($es_product === true)
        {
            $Action = 'Product';
            $VariantDefault = '';
            $VariantNames = '';
            $VariantItem1 = '';
        } else {
            $Action = 'Product Variant';
            if ($es_primer_product_variant === true)
                $VariantDefault = 'Yes';
            else
                $VariantDefault = 'No';

            if (!empty($p->tallas))
            {
                $VariantNames = 'Talla';
                $VariantItem1 = array_shift($p->tallas);// Elimina la talla
            } else if (!empty($p->colores)) {
                $VariantNames = 'Color';
                $VariantItem1 = array_shift($p->colores);// Elimina el color
            }  
        }

        // Images
        $imagenes = array();
        for ($i=0 ; $i<5 ; $i++)
        {
            if ($i < count($p->imagenes))
                array_push($imagenes, $p->imagenes[$i]);
            else
                array_push($imagenes, '');
        }

        $CategoryPath = 'Home > ' . Utils::imprimirArrayConDelimitador($p->categorias[0], ' > ');
        $ID = $p->id;
        $Name = $p->nombre;
        $Code = $p->referencia;
        $Description = '"'.str_replace('"', '""', $p->descripcion).'"';
        $ShortDescription = '"'.str_replace("\n", '<br>', $p->descripcion_corta).'"';
        $Brand = $p->fabricante;
        $Price = $p->precio_sin_iva;
        $RRP = $p->precio_con_iva;

        if(!empty($imagenes[0])){
            $Image1 = 'Image Assigned';
            $Image1Address = $imagenes[0];
        } else {
            $Image1 = 'No Image Assigned';
            $Image1Address = '';
        }

        if(!empty($imagenes[1])){
            $Image2 = 'Image Assigned';
            $Image2Address = $imagenes[1];
        } else {
            $Image2 = 'No Image Assigned';
            $Image2Address = '';
        }

        if(!empty($imagenes[2])){
            $Image3 = 'Image Assigned';
            $Image3Address = $imagenes[2];
        } else {
            $Image3 = 'No Image Assigned';
            $Image3Address = '';
        }

        if(!empty($imagenes[3])){
            $Image4 = 'Image Assigned';
            $Image4Address = $imagenes[3];
        } else {
            $Image4 = 'No Image Assigned';
            $Image4Address = '';
        }

        if(!empty($imagenes[4])){
            $Image5 = 'Image Assigned';
            $Image5Address = $imagenes[4];
        } else {
            $Image5 = 'No Image Assigned';
            $Image5Address = '';
        }

        $MetaTitle = '';
        $MetaDescription = '';
        $MetaKeywords = '';
        $Stock = 999;
        $Weight = 0;
        $TaxRateID = 1;
        $Condition = '';
        $GTIN = '';
        $MPN = '';
        $SpecialOffer = 'No';
        $OrderLocation = '';
        $OrderNote = '';
        $Hidden = 'No';
        $WebAddress = '';
        $VariantItem2 = '';
        $VariantItem3 = '';
        $VariantItem4 = '';
        $VariantItem5 = '';
        $CategoryManagement = '';
        $RelatedProducts = '';
        $OptionName = '';
        $OptionSize = '';
        $OptionType = '';
        $OptionValidation = '';
        $OptionItemName = '';
        $OptionItemPriceExtra = '';
        $OptionItemOrder = '';

        return $Action. $separador .$CategoryPath. $separador .$ID. $separador .$Name. $separador .$Code. $separador .$Description.
               $separador .$ShortDescription. $separador .$Brand. $separador .$Price. $separador .$RRP. $separador .$Image1.
               $separador .$Image2. $separador .$Image3. $separador .$Image4. $separador .$Image5. $separador .$MetaTitle.
               $separador .$MetaDescription. $separador .$MetaKeywords. $separador .$Stock. $separador .$Weight. $separador .$TaxRateID.
               $separador .$Condition. $separador .$GTIN. $separador .$MPN. $separador .$SpecialOffer. $separador .$OrderLocation.
               $separador .$OrderNote. $separador .$Hidden. $separador .$Image1Address. $separador .$Image2Address.
               $separador .$WebAddress. $separador .$VariantNames. $separador .$VariantItem1. $separador .$VariantItem2.
               $separador .$VariantItem3. $separador .$VariantItem4. $separador .$VariantItem5. $separador .$VariantDefault.
               $separador .$Image3Address. $separador .$Image4Address. $separador .$Image5Address. $separador .$CategoryManagement.
               $separador .$RelatedProducts. $separador .$OptionName. $separador .$OptionSize. $separador .$OptionType.
               $separador .$OptionValidation. $separador .$OptionItemName. $separador .$OptionItemPriceExtra. $separador .$OptionItemOrder. $finlinea;
    }

    /**
     * Genera CSV para 'ekm Tienda'.
     * @param Array $productos Productos recuperados de la base de datos
     * @return boolean|string False si ocurre un error. Ruta del archivo generado si se ha generado con éxito.
     */
    private function _generarCSVEkmTienda($productos)
    {
        $nombre_archivo = 'csvekmTienda'.date('dmY').'.csv';
        $path = $this->_path.'/'.$nombre_archivo;
        $output = fopen($path, "w+");
        $separador = ';';
        $finlinea = PHP_EOL;

        $cabeceras = 'Action'.$separador.'CategoryPath'.$separador.'ID'.$separador.'Name'.$separador.'Code'.$separador.
                     'Description'.$separador.'ShortDescription'.$separador.'Brand'.$separador.'Price'.$separador.
                     'RRP'.$separador.'Image1'.$separador.'Image2'.$separador.'Image3'.$separador.'Image4'.$separador.
                     'Image5'.$separador.'MetaTitle'.$separador.'MetaDescription'.$separador.'MetaKeywords'.$separador.
                     'Stock'.$separador.'Weight'.$separador.'TaxRateID'.$separador.'Condition'.$separador.'GTIN'.$separador.
                     'MPN'.$separador.'SpecialOffer'.$separador.'OrderLocation'.$separador.'OrderNote'.$separador.
                     'Hidden'.$separador.'Image1Address'.$separador.'Image2Address'.$separador.'WebAddress'.$separador.
                     'VariantNames'.$separador.'VariantItem1'.$separador.'VariantItem2'.$separador.'VariantItem3'.$separador.
                     'VariantItem4'.$separador.'VariantItem5'.$separador.'VariantDefault'.$separador.'Image3Address'.$separador.
                     'Image4Address'.$separador.'Image5Address'.$separador.'CategoryManagement'.$separador.'RelatedProducts'.$separador.
                     'OptionName'.$separador.'OptionSize'.$separador.'OptionType'.$separador.'OptionValidation'.$separador.
                     'OptionItemName'.$separador.'OptionItemPriceExtra'.$separador.'OptionItemOrder'.$finlinea;
        fwrite($output, $cabeceras);

        foreach ($productos as $p)
        {
            for ($i=0; !empty($p->tallas) ||  !empty($p->colores); $i++)
                fwrite($output, $this->_imprime_linea_csvekmtienda($p, $separador, $finlinea, $i==0 ? true : false, $i==1 ? true : false));
        }

        if(fclose($output))
            return $path;
        else
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al cerrar el archivo CSV EkmTienda','Critical');
  
        return false;
    }

    /**
     * Crea un nodo a partir de un array con información
     * @param DocDocument $xml Documento XML
     * @param Boolean $conCDATA True si necesita CDATA y False si no necesita
     * @param String $nomNodo Nombre del nodo
     * @param String $nomSubNodos Nombre de los subnodos
     * @param Array $array Array a transformar en nodo
     * @return Element Nodo compuesto por todos los elementos del array
     */
    private function _crearNodoConArray($xml, $conCDATA, $nomNodo, $nomSubNodos, $array)
    {
        $nodo = $xml->createElement($nomNodo);

        foreach ($array as $a)
        {
            if ($conCDATA == true)
            {
                $subNodo = $xml->createElement($nomSubNodos);
                $subNodo->appendChild($xml->createCDATASection($a));
            }
            else
                $subNodo = $xml->createElement($nomSubNodos, $a);

            $nodo->appendChild($subNodo);
        }

        return $nodo;
    }

    /**
     * Genera XML genérico con los productos
     * @param Array $array_productos Productos recuperados de la base de datos
     * @return boolean|string False si ocurre un error. Ruta del archivo generado si se ha generado con éxito.
     */
    private function _generarXML($array_productos)
    {
        $nombre_archivo = 'productos'.date('dmY').'.xml';
        $path = $this->_path.'/'.$nombre_archivo;
        $xml = new DomDocument("1.0","UTF-8");

        $raiz = $xml->createElement("catalog");
        $xml->appendChild($raiz);

        $productos = $xml->createElement("products");
        $raiz->appendChild($productos);

        foreach($array_productos as $p)
        {
            $producto = $xml->createElement("product");
            $productos->appendChild($producto);

            if ($p->estado === true)
                $raiz->setAttributeNode(new DOMAttr("status", "active"));
            else
                $raiz->setAttributeNode(new DOMAttr("status", "discontinued"));

            $id = $xml->createElement("id", $p->id);
            $referencia = $xml->createElement("reference", $p->referencia);

            $nombre = $xml->createElement("name");
            $nombre->appendChild($xml->createCDATASection($p->nombre));

            $categorias = $xml->createElement("categories");        
            foreach ($p->categorias as $c)
            {
                $categoria = $xml->createElement("category");
                $categoria->appendChild($xml->createCDATASection(Utils::imprimirArrayConDelimitador($c, '>')));
                $categorias->appendChild($categoria);
            }

            $descripcion_corta = $xml->createElement("short_description");
            $descripcion_corta->appendChild($xml->createCDATASection($p->descripcion_corta));

            $descripcion = $xml->createElement("description");
            $descripcion->appendChild($xml->createCDATASection($p->descripcion));

            $tallas = $this->_crearNodoConArray($xml, true, "sizes", "size", $p->tallas);
            $colores = $this->_crearNodoConArray($xml, true, "colors", "color", $p->colores);

            $precio = $xml->createElement("price", $p->precio_sin_iva);
            $precio_iva = $xml->createElement("price_with_tax", $p->precio_con_iva);

            $fabricante = $xml->createElement("manufacturer");
            $fabricante->appendChild($xml->createCDATASection($p->fabricante));

            $imagenes = $this->_crearNodoConArray($xml, false, "photos", "photo", $p->imagenes);
            $ultima_actualizacion = $xml->createElement("updated", $p->ultima_modificacion);

            $producto->appendChild($id);
            $producto->appendChild($referencia);
            $producto->appendChild($nombre);
            $producto->appendChild($categorias);
            $producto->appendChild($descripcion_corta);
            $producto->appendChild($descripcion);
            $producto->appendChild($tallas);
            $producto->appendChild($colores);
            $producto->appendChild($precio);
            $producto->appendChild($precio_iva);
            $producto->appendChild($fabricante);
            $producto->appendChild($imagenes);
            $producto->appendChild($ultima_actualizacion);
        }

        $xml->formatOut = true; 
        $xml->saveXML();

        if($xml->save($path))
            return $path;
        else
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al cerrar el archivo XML','Critical');
        
        return false;
    }

    /**
     * Genera un archivo json donde se almacenan los productos
     * @param Array $productos Productos recuperados de la base de datos
     * @return boolean|string False si ocurre un error. Ruta del archivo generado si se ha generado con éxito.
     */
    private function _generarJSON($productos)
    {
        $path = $this->_path.'/json'.date('dmY').'.json';
        $output = fopen($path, "w+");
        fwrite($output, json_encode($productos));

        if(fclose($output))
            return $path;
        else
            $this->_log->addLineLog('['.__FUNCTION__.']('.__LINE__.'): Ha fallado al cerrar el archivo JSON','Critical');
        
        return false;
    }
}