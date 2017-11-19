
<form method="get" action="catalogo/productos">
    <input type="hidden" name="type" value="json" />
    <input type="hidden" name="prices" value=
        "<?php
            $incrementos_precio = array('Lencería Femenina'=>1, 'Bodys'=>1.05, 'Chemises'=>1.05, 'Conjuntos'=>1.05, 'Corsets'=>1.05, 'Leggins'=>1.05, 'Medias'=>1.20,
                            'Picardias'=>1.10, 'Tallas Grandes'=>1.05, 'Braguitas'=>1.05, 'Tangas'=>1.05, 'Tangas y Braguitas'=>1.05,
                            'Disfraces'=>1.05, 'Hombre'=>1.05, 'Mujer'=>1.05, 'Complementos'=>1.05, 'Enaguas-Tutus'=>1.05, 'Guantes'=>1.05,
                            'Tapa Pezones'=>1.05, 'Nuevas Sensaciones'=>1.05, 'Aceites'=>1.05, 'Pinturas'=>1.05, 'Velas'=>1.05,
                            'Verano'=>1.50, 'Batas'=>1.50, 'Bikinis'=>1.50, 'Vestidos'=>1.05);
            print urlencode(json_encode($incrementos_precio));
        ?>" />
    <input type="submit" value="Enviar formulario" />
</form>

<!-- NO BORRAR!!! Para que no se pueda listar los directorios -->
<!-- Copiar -->