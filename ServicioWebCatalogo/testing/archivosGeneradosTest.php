<?php

/**
 * Tests para comprobar que la información volcada en los archivos generados tienen el formato correcto
 */

class archivosGeneradosTest extends PHPUnit_Framework_TestCase
{
    private $_path;
    private $_gen;
    
    protected function setUp()
    {    
        $this->_gen = dirname(__FILE__).'/../gen/';
    }
    
    protected function tearDown() {
        
    }
    
    public function testAllAreUTF8()
    {
        // CSV Prestashop
        $path_csvPrestashop = $this->_gen.'CSVPrestashop'.date('dmY').'/'.'productos'.date('dmY').'.csv'; 
        $charset = mb_detect_encoding(file_get_contents($path_csvPrestashop), 'UTF-8', true);        
        $this->assertEquals('UTF-8', $charset);
        
        // CSV Genérico
        $path_csv = $this->_gen.'csv'.date('dmY').'.csv';
        $charset = mb_detect_encoding(file_get_contents($path_csv), 'UTF-8', true);        
        $this->assertEquals('UTF-8', $charset);
        
        // CSV ekmTienda
        $path_csvekmtienda = $this->_gen.'csvekmTienda'.date('dmY').'.csv';
        $charset = mb_detect_encoding(file_get_contents($path_csvekmtienda), 'UTF-8', true);        
        $this->assertEquals('UTF-8', $charset);
        
        // XML
        $path_xml = $this->_gen.'productos'.date('dmY').'.xml'; 
        $charset = mb_detect_encoding(file_get_contents($path_xml), 'UTF-8', true);        
        $this->assertEquals('UTF-8', $charset);
                
        // JSON
        $path_json = $this->_gen.'json'.date('dmY').'.json'; 
        $charset = mb_detect_encoding(file_get_contents($path_json), 'UTF-8', true);        
        $this->assertEquals('UTF-8', $charset);
    }
    
    public function testAllCabecerasCSV()
    {
        $path_csvPrestashop = $this->_gen.'CSVPrestashop'.date('dmY').'/'.'productos'.date('dmY').'.csv'; 
        $path_csv = $this->_gen.'csv'.date('dmY').'.csv';
        $path_csvEkmTienda= $this->_gen.'csvekmTienda'.date('dmY').'.csv';
        
        // Leer CSV PrestaShop generado
        $fp = fopen($path_csvPrestashop, "r");
        if ($fp)
        {
            $cabecera = fgets($fp);
            $cabecera_esperada = 'Activo;Referencia;Nombre;Categoría;Descripción corta;Descripción;Precio sin IVA;' .
                                 'Precio con IVA;ID impuesto;URL imágenes;Fabricante;Eliminar imágenes existentes;Fecha última modificación';
            
            fclose($fp);
            
            $this->assertEquals($cabecera_esperada.PHP_EOL, $cabecera, 'Cabecera CSV PrestaShop');
        } else {
            // Si no se abre el fichero fuerzas un error
            $this->assertTrue(false); 
        }
        
        // Leer CSV generado
        $fp = fopen($path_csv, "r");
        if ($fp)
        {
            $cabecera = fgets($fp);
            $cabecera_esperada = 'Estado;Referencia;Nombre;Categoría;Descripción corta;Descripción;Tallas;Colores;' .
                                 'Precio sin IVA;Precio con IVA;URL imágenes;Fabricante;Fecha última modificación';
            fclose($fp);
            
            $this->assertEquals($cabecera_esperada.PHP_EOL, $cabecera, 'Cabecera CSV Genérico');
        } else {
            // Si no se abre el fichero fuerzas un error
            $this->assertTrue(false); 
        }
        
        // Leer CSV ekmTienda generado
        $fp = fopen($path_csvEkmTienda, "r");
        if ($fp)
        {
            $cabecera = fgets($fp);
            $cabecera_esperada = 'Estado;Referencia;Nombre;Categoría;Descripción corta;Descripción;Tallas;Colores;'
                    . 'Precio sin IVA;Precio con IVA;URL imágenes;Fabricante;Fecha última modificación';
            
            $cabecera_esperada = 'Action;CategoryPath;ID;Name;Code;Description;ShortDescription;Brand;Price;RRP;Image1;Image2;'
                                . 'Image3;Image4;Image5;MetaTitle;MetaDescription;MetaKeywords;Stock;Weight;TaxRateID;Condition;'
                                . 'GTIN;MPN;SpecialOffer;OrderLocation;OrderNote;Hidden;Image1Address;Image2Address;WebAddress;'
                                . 'VariantNames;VariantItem1;VariantItem2;VariantItem3;VariantItem4;VariantItem5;VariantDefault;'
                                . 'Image3Address;Image4Address;Image5Address;CategoryManagement;RelatedProducts;OptionName;'
                                . 'OptionSize;OptionType;OptionValidation;OptionItemName;OptionItemPriceExtra;OptionItemOrder';
            
            fclose($fp);
            
            $this->assertEquals($cabecera_esperada.PHP_EOL, $cabecera, 'Cabecera CSV ekmTienda');
        } else {
            // Si no se abre el fichero fuerzas un error
            $this->assertTrue(false); 
        }
    }
    
    public function testestructuraXML()
    {
        $path_xml = $this->_gen.'productos'.date('dmY').'.xml';
        $path_xsd = 'resources/productos.xsd';
        
        $xml = new DOMDocument(); 
        $xml->load($path_xml);

        // Verificar que el XML tenga la estructura del Schema
        $this->assertTrue($xml->schemaValidate($path_xsd));       
    }
}