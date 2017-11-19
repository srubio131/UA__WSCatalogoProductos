<?php

//****** Configuración
// Configurar constantes servicio Web PrestaShop
define('DEBUG', true);
define('PS_SHOP_PATH', 'http://localhost/Prestashop');
define('PS_WS_AUTH_KEY', 'KUHFE8GRGQ685TI2FLNZWRC493PMRQW5');
require_once('./PSWebServiceLibrary.php');

// Configurar constantes servicio Web Catálogo
define('USER', 'sergio');
define('PASS', '123456789');
define('URL_SERVICIOWEBCATALOGO', 'http://localhost/ServicioWebCatalogo/catalogo/productos?');

$incrementos_precio = array('Lencería Femenina'=>1,
                                'Bodys'=>1.05,
                                'Chemises'=>1.05,
                                'Conjuntos'=>1.05,
                                'Corsets'=>1.05,
                                'Leggins'=>1.05,
                                'Medias'=>1.20,
                                'Picardias'=>1.10,
                                'Tallas Grandes'=>1.05,
                                'Braguitas'=>1.05,
                                'Tangas'=>1.05,
                                'Tangas y Braguitas'=>1.05,
                            'Disfraces'=>1.05,
                                'Hombre'=>1.05,
                                'Mujer'=>1.05,
                            'Complementos'=>1.05,
                                'Enaguas-Tutus'=>1.05,
                                'Guantes'=>1.05,
                                'Tapa Pezones'=>1.05,
                            'Nuevas Sensaciones'=>1.05,
                                'Aceites'=>1.05,
                                'Pinturas'=>1.05,
                                'Velas'=>1.05,
                            'Verano'=>1.50,
                                'Batas'=>1.50,
                                'Bikinis'=>1.50);
//********************************************************************************

$fields = array('prices' => urlencode(json_encode($incrementos_precio)));
$header = array('Content-Type: application/json');
$url = URL_SERVICIOWEBCATALOGO . http_build_query($fields);

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERPWD, USER.':'.PASS);
curl_setopt($curl, CURLOPT_TIMEOUT, 60);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);   // Devuelve el response
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
$curl_response = curl_exec($curl);

if($curl_response === false) {
    echo 'Error : '.curl_error($curl).'<br>';
    exit;
} else {echo 'OK';}

curl_close($curl);

$productos = json_decode($curl_response);

// Actualizar Prestashop con los datos del webservice
///////////////////////////////////////////////////////////////
$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);

// Añadir fabricante
$id_manufacturer = GetManufacturerID("Lencería Finíssima", $webService);
if ($id_manufacturer === false)
    AddManufacturer("Lencería Finíssima", "http://www.lenceriafinissima.com/images/blog/blog.jpg", $webService);

foreach($productos as $producto)
{
    // Añadir categorías al árbol de categorias (Si no están añadidas ya)
    AddCategories($producto->categorias, TRUE, $webService);
    
    // Añadir/Modificar un producto
    $id = AddBasicsProduct($producto, $webService);

    if ($id !== false & is_numeric($id)) // Añadir imágenes al producto
    {
        foreach($producto->imagenes as $imagen)
        {
            if (UploadImage($imagen, $id) === false)
                echo 'Error al subir la imagen ID: $id';
        }
    }
}

/**
 * Añade las características básicas de un producto. Si está descatalogado entonces se modifica con estado = false
 * @param Producto $producto Datos de un producto
 * @param Resource $webService Servicio Web de PrestaShop
 * @return Boolean|String False si error | ID del producto creado/modificado
 */
function AddBasicsProduct($producto, $webService)
{
    $id = GetProductID($producto->referencia, $webService);
    if ($id === false)   // Añadir nuevo producto
    {
        $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/products?schema=blank'));        
        $resources = $xml->children()->children();        
        unset($resources->link_rewrite); 
        
        $resources->active = $producto->estado;
        $resources->reference = $producto->referencia;
        $resources->name->language[0][0] = $producto->nombre;
        $resources->description->language[0][0] = $producto->descripcion;
        $resources->description_short->language[0][0] = $producto->descripcion_corta;
        $resources->price = $producto->precio_con_iva;
        
        foreach($producto->categorias as $categoria)
        {
            foreach($categoria as $cat)
            {
                $id_category = GetCategoryID($cat, $webService);
                $resources->associations->categories->addChild('category')->addChild('id', $id_category); 
            }
        }

        $resources->id_manufacturer = GetManufacturerID($producto->fabricante, $webService);
        
        try {
            $opt = array('resource' => 'products');
            $opt['postXml'] = $xml->asXML();
            $xml = $webService->add($opt);
            $id = (int) $xml->product->id;
        } catch (PrestaShopWebserviceException $ex) {
            echo 'Other error: <br/>'.$ex->getMessage();
            return false; 
        }
    }
    else    // Modificar producto existente
    {
        try {
            $opt = array('resource' => 'products',
                         'id' => $id);
            $xml = $webService->get($opt);
            $resources = $xml->children()->children();
            
            unset($resources->manufacturer_name);
            unset($resources->quantity);
            
            $resources->active = $producto->estado;
            $resources->reference = $producto->referencia;
            $resources->name->language[0][0] = $producto->nombre;
            $resources->description->language[0][0] = $producto->descripcion;
            $resources->description_short->language[0][0] = $producto->descripcion_corta;
            $resources->price = $producto->precio_con_iva;

            foreach($producto->categorias as $categoria)
            {
                foreach($categoria as $cat)
                {
                    $id_category = GetCategoryID($cat, $webService);
                    $resources->associations->categories->addChild('category')->addChild('id', $id_category); 
                }
            }

            $resources->id_manufacturer = GetManufacturerID($producto->fabricante, $webService);            
            
            $opt = array('resource' => 'products');
            $opt['putXml'] = $xml->asXML();
            $opt['id'] = $id;
            $xml = $webService->edit($opt);
        } catch (PrestaShopWebserviceException $ex) {
            echo 'Other error: <br/>'.$ex->getMessage();
            return false; 
        }
    }    
    return $id;
}

/**
 * Añade las categorias asociadas a un producto al árbol de categorías si no existen
 * @param Array $categories Array de categorias a buscar y añadir en el arból de categorías
 * @param Boolean $es_padre True si la categoria es padre
 * @param Resource $webService Servicio Web de PrestaShop
 * @return Boolean True si tiene éxito. False en caso de error
 */
function AddCategories($categories, $es_padre, $webService)
{
    foreach($categories as $cat)
    {
        if (is_array($cat))
            AddCategories($cat, $es_padre, $webService);
        else
        {        
            $name = trim($cat);
            if ($es_padre === true)
            {
                $id_parent = 2; // Categoría inicio por defecto
                $es_padre = false;
            }
            
            $existe = GetCategoryID($name, $webService);
            if ($existe === false) // No existe
            {
                $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/categories?schema=blank'));        
                $resources = $xml->children()->children();
                
                $resources->active = true;

                $node = dom_import_simplexml($resources->name->language[0][0]);
                $no = $node->ownerDocument;
                $node->appendChild($no->createCDATASection($name));                
                $resources->name->language[0][0] = $name;
                
                $node = dom_import_simplexml($resources->link_rewrite->language[0][0]);
                $no = $node->ownerDocument;
                $node->appendChild($no->createCDATASection($name));
                $resources->link_rewrite->language[0][0] = $name;
                
                $resources->id_parent = $id_parent;
                
                try {
                    $opt = array('resource' => 'categories');
                    $opt['postXml'] = $xml->asXML();
                    $xml = $webService->add($opt);
                    $id_parent = (int) $xml->category->description->id; // Último id_parent añadido
                } catch (PrestaShopWebserviceException $ex) {
                    echo 'Other error: <br/>'.$ex->getMessage();
                    return false; 
                }
            } else {
                $id_parent = $existe; // Último id_parent añadido
            }
        }
    }
    return true;
}

/**
 * Sube una imagen a un servidor
 * @param String $url_from Url de la imagen a subir
 * @param String $id ID del producto al que pertenece la imágen
 * @return Boolean True si se ha subido con éxito. False si no
 */
function UploadImage($url_from, $id)
{
    $result = false;    
    $url_to = PS_SHOP_PATH . '/api/images/products/'.$id;
    
    // Descargar imagen
    $extension = substr($url_from, strrpos($url_from, '.')+1);
    //$tmp_path = sys_get_temp_dir().'\img.'.$extension;
    $tmp_path = 'C:\Users\Fernando\Desktop\img.'.$extension;
    if (copy($url_from, $tmp_path))
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_to);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_PUT, true); // Sobreescribir si ya existe
        curl_setopt($ch, CURLOPT_USERPWD, PS_WS_AUTH_KEY.':');
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => '@'.$tmp_path));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if(curl_exec($ch))
        {
            $result = true;
            curl_close($ch);
        }     
    }
    return $result;
}

function GetCategoryID($name, $webService)
{
    try {
        $opt = array(
            'resource' =>'categories',
            'display' => '[id]',
            'filter[name]' => $name);      
        $xml = $webService->get($opt);        
        $id = (int) $xml->categories->category->id;
        if ($id === 0)
            $id = false;
    }   catch (PrestaShopWebserviceException $e)    {       
            $trace = $e->getTrace();
            $id = false;
    }
    return $id;
}

function GetProductID($reference, $webService)
{
    try {
        $opt = array(
            'resource' =>'products',
            'display'  => '[id]',
            'filter[reference]'  => $reference);      
        $xml = $webService->get($opt);
        $id = (int) $xml->products->product->id;
        if ($id === 0)
            $id = false;
    }   catch (PrestaShopWebserviceException $e)    {       
            $trace = $e->getTrace();
            $id = false;
    }       
    return $id;
}

function GetManufacturerID($name, $webService)
{
    try {
        $opt = array(
            'resource' =>'manufacturers',
            'display'  => '[id]',
            'filter[name]'  => $name);      
        $xml = $webService->get($opt);          
        $id = (int) $xml->manufacturers->manufacturer->id;
        if ($id === 0)
            $id = false;
    }   catch (PrestaShopWebserviceException $e)    {       
            $trace = $e->getTrace();
            $id = false;
    }   
    return $id;
}

/**
 * Añadir Fabricante con logotipo
 * @param String $name Nombre del fabricante
 * @param String $url_image URL de la imagen del logotipo
 * @param Resourse $webService Web Service PrestaShop
 */
function AddManufacturer($name, $url_image, $webService) 
{
    $xml = $webService->get(array('resource' => 'manufacturers?schema=blank'));
    $resources = $xml->children()->children();
    $resources->name = $name;  
    $resources->active = 1; 
    unset($resources -> link_rewrite);
    
    $opt = array(
                'resource' => 'manufacturers',
                'active' => array(),
                'postXml' => $xml->asXML());

    $xml = $webService->add($opt);
    $id = (int) $xml->manufacturers->manufacturer->id;
    UploadImage($url_image, $id);  
}