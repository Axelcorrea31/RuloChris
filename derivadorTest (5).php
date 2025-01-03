<?php
ini_set("memory_limit", "512M");
require("includes/config-boost.php"); // ver la dif con config.php de listados
include("includes/lang/" . $pais1->pais_codificado . ".php");

// variables de derivador
$derivador = true;
$tipos = array();
$total_anuncios;
$año_limite = date("Y") - 2;
$json_ld_anuncios = [];

// Obtener la URL actual
$requestUri = $_SERVER['REQUEST_URI'];
// Eliminar "/nuevositio_test/" si existe al inicio de la URL
$nuevaUri = preg_replace('|^/nuevositio_test|', '', $requestUri);

$url_pagina_actual = $dir_baseNoSlash . $nuevaUri;

if (session_status() !== PHP_SESSION_ACTIVE)
  session_start();

$pag = isset($_GET['page']) ? $_GET['page'] : 1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 0;

$autocanonical = $sort == 0 ? true : false; // si se aplico un filtro no se hace autocanon. Solo paginacion sin filtro

$monedas = array(
  "argentina" => "ARS",           // Peso argentino
  "espana" => "EUR",              // Euro
  "chile" => "CLP",               // Peso chileno
  "mexico" => "MXN",              // Peso mexicano
  "peru" => "PEN",                // Nuevo sol peruano
  "uruguay" => "UYU",             // Peso uruguayo
  "ecuador" => "USD",             // Dólar estadounidense
  "usa" => "USD",                 // Dólar estadounidense
  "colombia" => "COP",            // Peso colombiano
  "venezuela" => "VES",           // Bolívar soberano
  "panama" => "PAB",              // Balboa panameña (USD comúnmente usado)
  "costa-rica" => "CRC",          // Colón costarricense
  "paraguay" => "PYG",            // Guaraní paraguayo
  "bolivia" => "BOB",             // Bolívar boliviano
  "republica-dominicana" => "DOP", // Peso dominicano
  "puerto-rico" => "USD",         // Dólar estadounidense
  "guatemala" => "GTQ",           // Quetzal guatemalteco
  "honduras" => "HNL",            // Lempira hondureña
  "el-salvador" => "USD",         // Dólar estadounidense
  "nicaragua" => "NIO",           // Córdoba nicaragüense
  "cuba" => "CUP"                 // Peso cubano
);



function generarArrayLocalidades($idProv)
{
  global $enlace;
  $sql_array_localidad = "Select * From db_localidades Where id_provincia = " . $idProv;
  $rs_array_localidad = mysqli_query($enlace, $sql_array_localidad);
  $array_localidad_inmueble = array();
  // recorro las localidades y las voy agregando a $array_localidad_inmueble mediante id
  while ($localidad_array = mysqli_fetch_object($rs_array_localidad)) {
    $array_localidad_inmueble[$localidad_array->id] = codificar($localidad_array->localidad);
    // agregra un array sin codificar asi para buscar el nombre real no hay q volver a la BD
  }
  mysqli_free_result($rs_array_localidad);
  return $array_localidad_inmueble;
}

function generarArrayProvincias()
{ // con publi != 0
  global $enlace;
  $sql_array_provincia = "Select * From db_provincias";
  $rs_array_provincia = mysqli_query($enlace, $sql_array_provincia);
  $array_provincia_inmueble = array();
  while ($provincia_array = mysqli_fetch_object($rs_array_provincia)) {
    $array_provincia_inmueble[$provincia_array->id] = codificar($provincia_array->provincia);
  }
  mysqli_free_result($rs_array_provincia);
  // $array_provincia_inmueble: [1] -> amazonas,  [2] -> norte-de-santander, ...
  return $array_provincia_inmueble;
}

// busca provincia por id
function buscarProvincia($id_prov)
{
  global $enlace;
  // con el id, busco el nombre bien escrito de la provincia de: valle-del-cauca a Valle Del Cauce
  $sql_provincia = "Select * From db_provincias Where id = " . $id_prov . " Limit 1";
  $rs_provincia = mysqli_query($enlace, $sql_provincia);
  $provincia = mysqli_fetch_object($rs_provincia);
  mysqli_free_result($rs_provincia);
  return $provincia;
}
function getListadoAnuncios($sort, $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search, $pag)
{
  global $enlace, $año_limite;
  $anunciosPorPag = 20;
  $desde = ($pag - 1) * $anunciosPorPag;

  $select = "SELECT * FROM db_buscador ";
  $filtro = "YEAR(fecha) >= '$año_limite' AND externo = 0 AND localidad_inmueble != ''";

  $whereCuerpo = $data_tipo ? " AND tipo_inmueble = '" . $data_tipo . "'" : "";
  $whereCuerpo .= $data_operacion ? " AND tipo_operacion = '" . $data_operacion . "'" : "";
  $whereCuerpo .= $data_provincia ? " AND provincia_inmueble = '" . $data_provincia . "'" : "";
  $whereCuerpo .= $data_localidad ? " AND localidad_inmueble = '" . $data_localidad . "'" : "";
  $whereCuerpo .= $data_search ? " AND MATCH (texto) AGAINST ('" . $data_search . "' IN BOOLEAN MODE)" : "";

  $where = "WHERE " . $filtro . $whereCuerpo;
  $limit = "LIMIT " . $anunciosPorPag . " OFFSET " . $desde;

  if ($sort == 0) {
    // Query de anuncios destacados
    $where1 = "WHERE prioridad IN (2, 3) AND " . $filtro . $whereCuerpo;
    $order = "ORDER BY prioridad DESC, RAND() ";
    $limit1 = "LIMIT 3";
    $queryDestacados = $select . $where1 . $order . $limit1;

    // Query anuncios comunes
    $order = " ORDER BY fecha DESC ";
    $queryComunes = $select . $where . $order . $limit;

    $query = "(" . $queryDestacados . ") UNION ALL (" . $queryComunes . ");";
  } else {
    // defino el orden
    $order = $sort == 1 ? " ORDER BY precio DESC " : ($sort == 2 ? " ORDER BY precio ASC " : ($sort == 3 ? " ORDER BY fecha DESC " : " ORDER BY fecha ASC "));
    $query = $select . $where . $order . $limit; // armo la query
  }

  $result = mysqli_query($enlace, $query); // ejecutamos la consulta
  if (!$result)
    die('Error en la consulta getListadoAnuncios: ' . mysqli_error($enlace) . $query);

  // Recuperar todas las filas en un array
  $aviso_total = [];
  while ($row = mysqli_fetch_array($result)) {
    $aviso_total[] = $row;
  }

  // Liberar el conjunto de resultados
  mysqli_free_result($result);

  return $aviso_total;
}

function generarStructuredData($row, $url_photo, $url, $titulo)
{
  global $pais1, $monedas, $json_ld_anuncios;

  $anuncio = [
    "@context" => "http://schema.org",
    "@type" => "Product",
    "name" => $titulo,
    "image" => isset($url_photo) ? [$url_photo] : [],
    "description" => isset($row["descripcion"]) ? $row["descripcion"] : null,
    "offers" => [
      "@type" => "Offer",
      "url" => $url,
      "priceCurrency" => $row["moneda"] == "dolares" ? "USD" : $monedas[$pais1->pais_codificado],
      "price" => $row["precio"],
      "availability" => "https://schema.org/InStock"
    ],
  ];

  $json_ld_anuncios[] = $anuncio;

}

// genera los tipos del derivador y la cant de anuncios de la categoria actual
function generarDerivador($groupby, $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search)
{
  global $enlace, $tipos, $total_anuncios, $año_limite;

  $select = "SELECT COUNT(b.id) AS cant";
  $select .= $groupby ? ", b." . $groupby . " as nombre" : "";
  $select .= " FROM db_buscador b ";

  $innerjoin = $groupby == "localidad_inmueble" ? "INNER JOIN db_localidades l ON b.localidad_inmueble = l.localidad " : "";

  $filtro = "YEAR(b.fecha) >= '$año_limite' AND b.externo = 0 AND b.localidad_inmueble != ''";
  $whereCuerpo = $data_tipo ? " AND b.tipo_inmueble = '" . $data_tipo . "'" : "";
  $whereCuerpo .= $data_operacion ? " AND b.tipo_operacion = '$data_operacion'" : '';
  $whereCuerpo .= $data_provincia ? " AND b.provincia_inmueble = '$data_provincia'" : '';
  $whereCuerpo .= $data_localidad ? " AND b.localidad_inmueble = '" . $data_localidad . "'" : "";
  $whereCuerpo .= $data_search ? " AND MATCH (b.texto) AGAINST ('" . $data_search . "' IN BOOLEAN MODE)" : "";
  $whereCuerpo .= $groupby ? " GROUP BY b.$groupby" : "";
  $where = "WHERE " . $filtro . $whereCuerpo;

  $query = $select . $innerjoin . $where;
  $result = mysqli_query($enlace, $query);  //ejecutamos query

  while ($data = mysqli_fetch_object($result)) {
    if ($groupby)
      $tipos[] = $data;
    $total_anuncios = $total_anuncios + $data->cant;
  }
  mysqli_free_result($result);
}

function buscarCoincidencia($comparacion)
{
  global $search_string;

  foreach ($comparacion as $indice => $tipo) {
    $pos = stripos($search_string, $tipo);

    if ($pos !== false) {
      // elimino la palabra, ya q sera agregada como filtro en la consulta
      $search_string = substr_replace($search_string, '', $pos, strlen($tipo));
      return $indice;
    }
  }

  return false; // Si no se encuentra ningún tipo de inmueble
}


// detect mobile devices
require_once 'archivos/Mobile-Detect-master/Mobile_Detect.php';
$detect = new Mobile_Detect;
$deviceType = ($detect->isMobile() ? ($detect->isTablet() ? 'tablet' : 'phone') : 'computer');

$titulo_pagina = "Empresas y Profesionales. BienesOnline " . utf8_encode($pais1->pais);
$paginaActual = "Empresas y Profesionales";
$descripcion_pagina = "Directorio de empresas y profesionales relacionados al mercado inmobiliario";
$index_pagina = $sort == 0 ? "index, follow" : "noindex, follow"; // no indexamos url con filtros
$mostrar_buscador = "no";

// conexion MYSQLI
$dbname = $pais1->base_datos;
$enlace = mysqli_connect($dbhost, $usuario, $clave, $dbname);
mysqli_set_charset($enlace, "utf8");

//search string. Buscamos si hay categorias dentro de ella.
if ($_GET['search_string']) {
  $search_string = decodificar($_GET['search_string']); // casa-en-venta -> Casa en venta

  //buscamos si el usuario especifico tipo de inmueble en la busqueda. Si hay coincidencia $idTipo tendra "idInmueble" sino tendra "false"
  $idTipo = buscarCoincidencia($tipo_inmueble) ?: buscarCoincidencia($tipo_inmueble_plural);

  if ($idTipo) { // si se especifico tipo de inmueble
    $_GET['tipoinmueble'] = strtolower($tipo_inmueble_plural[$idTipo]); // lo almaceno en get para reusar el codigo de tipo derivador

    $idOperacion = buscarCoincidencia($tipo_operacion);
    if ($idOperacion) { // si se especifico tipo de operacion
      $_GET['tipooperacion'] = strtolower($tipo_operacion[$idOperacion]);
      $array_provincia_inmueble = generarArrayProvincias();
      $idProv = buscarCoincidencia($array_provincia_inmueble);
      if ($idProv) { // si se especifico provincia
        $_GET['provinciainmueble'] = strtolower($array_provincia_inmueble[$idProv]);
        $array_localidad_inmueble = generarArrayLocalidades($idProv); // generamos array de localidades en base a al prov
        $idLoc = buscarCoincidencia($array_localidad_inmueble);
        if ($idLoc)
          $_GET['localidadinmueble'] = strtolower($array_localidad_inmueble[$idLoc]);
      }
    }
  }
  // Parametro para la query. Casa en venta -> +Casa +en +venta
  $data_search = "+" . preg_replace("/ /", " +", $search_string);
  generarDerivador($groupby, $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search);

  // titulos y metadatos
  $titulo_pagina = decodificar($_GET['search_string']);
  $titulo_h1 = $titulo_pagina . " - " . $total_anuncios . " anuncios";
  $meta_descripcion = $total_anuncios . " anuncios encontrados para la busqueda " . $titulo_pagina;
}

if ($_GET['all']) { // bienesonline.co/inmuebles
  $urlBase = "https://$pais1->pais_codificado.slideprop.com/api/contarInmuebles.php";
        
        
        $tipo_inmueble = isset($data_tipo) ? $data_tipo : '';
        $tipo_operacionApi = isset($_GET['tipo_operacion']) ? $_GET['tipo_operacion'] : '';
        $tipo_ubicaciones = isset($_GET['tipo_ubicaciones']) ? $_GET['tipo_ubicaciones'] : '';
        $all = isset($_GET['all']) ? $_GET['all'] : '';
       
        $fields = [
            'tipo_inmueble' => $tipo_inmueble,
            'tipo_operacion' => $tipo_operacionApi,
            'tipo_ubicaciones' => $tipo_ubicaciones,
            'all' => $all
        ];

        $fields_string = http_build_query($fields);

    
        $curl = curl_init($urlBase);

        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);

       
        $data = curl_exec($curl);

        
        if ($data === false) {
            $response = ["error" => "Error en la petición: " . curl_error($curl)];
        } else {
            
            $response = json_decode($data, true);
        }

       
        curl_close($curl);

  $meta_descripcion = 'Inmuebles en ' . utf8_encode($pais1->pais);
  $tituloBreadcrumb1 = $meta_descripcion;
  $linkBreadcrumb1 = 'inmuebles';
  generarDerivador("tipo_inmueble", $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search);
  $totalInmuebles = 0;
  $totalInmueblesBienesOnline = 0;
        foreach ($tipos as $key) {
          $totalInmueblesBienesOnline += $localidad->cant;
          foreach ($response as $keyResponse) {
             if ($key->nombre == $keyResponse['tipo_inmueble']) {
                $key->cant = $key->cant + $keyResponse['cant'];
                $totalInmuebles += $key->cant;
             }
          }
        }
  $total_anuncios = $totalInmuebles;
  $titulo_h1 = $total_anuncios . " " . 'Inmuebles' . " en " . utf8_encode($pais1->pais);
  $titulo_pagina = $titulo_h1 . " - BienesOnLine";
  $urlInmuebles = 'inmuebles';
}

if ($_GET['tipoinmueble']) {  // bienesonline.co/casas
  $TipoInmueble = $_GET['tipoinmueble'];
  if ($TipoInmueble == "cabanas")
    $TipoInmueble = "cabañas";

  $urlTipo = $_GET['tipoinmueble'];
  // guardamos el ID del tipo de inmueble
  $tipo = array_search($TipoInmueble, array_map('strtolower', $tipo_inmueble_plural));

  if (!$tipo) { // validamos q el tipo de inmueble sea valido
    header("HTTP/1.0 404 Not Found");
    include('404.php');
    die();
  }

  $data_tipo = $tipo_inmueble[$tipo]; // obtengo el tipo_inmueble (singular)
  $tipoPlural = $tipo_inmueble_plural[$tipo];
  $meta_descripcion = $tipo_inmueble_plural[$tipo] . " en " . utf8_encode($pais1->pais);  // casas

  // breadcrumbs
  $tituloBreadcrumb1 = $tipo_inmueble_plural[$tipo];
  $linkBreadcrumb1 = codificar($tipo_inmueble_plural[$tipo]);

  if ($_GET['acc'] == 1) {
    // URL de la API 
        $urlBase = "https://$pais1->pais_codificado.slideprop.com/api/contarInmuebles.php";
        
        
        $tipo_inmueble = isset($data_tipo) ? $data_tipo : '';
        $tipo_operacionApi = isset($_GET['tipo_operacion']) ? $_GET['tipo_operacion'] : '';
        $tipo_ubicaciones = isset($_GET['tipo_ubicaciones']) ? $_GET['tipo_ubicaciones'] : '';

       
        $fields = [
            'tipo_inmueble' => $tipo_inmueble,
            'tipo_operacion' => $tipo_operacionApi,
            'tipo_ubicaciones' => $tipo_ubicaciones
        ];

        $fields_string = http_build_query($fields);

    
        $curl = curl_init($urlBase);

        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);

       
        $data = curl_exec($curl);

        
        if ($data === false) {
            $response = ["error" => "Error en la petición: " . curl_error($curl)];
        } else {
            
            $response = json_decode($data, true);
        }

       
        curl_close($curl);

        // Procesar los resultados
        if (isset($response['error'])) {
            echo "Error: " . $response['error'];
        } elseif (!is_array($response)) {
            echo "Respuesta inválida.";
        } 
    generarDerivador("tipo_operacion", $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search);
      $totalInmuebles = 0;
      $totalInmueblesBienesOnline = 0;
        foreach ($tipos as $key) {
          $totalInmueblesBienesOnline += $localidad->cant;
          foreach ($response as $keyResponse) {
             if ($key->nombre == $keyResponse['tipo_operacion']) {
                $key->cant = $key->cant + $keyResponse['cant'];
                $totalInmuebles += $key->cant;
             }
          }
        }
        //si slide trae anuncios, hacemos la sumatoria
        if ($totalInmuebles>0) {
          $total_anuncios = $totalInmuebles;
        }
    
    $titulo_h1 = $total_anuncios . " " . $tipo_inmueble_plural[$tipo] . " en " . utf8_encode($pais1->pais);
    $titulo_pagina = $titulo_h1 . " - BienesOnLine";

    //para adsense de busqueda
    $query_adsense = $tipo_inmueble_plural[$tipo] . " en " . utf8_encode($pais1->pais);
  }
}

if ($_GET['tipooperacion']) { // bienesonline.co/casas/venta
  $TipoOperacion = decodificar($_GET['tipooperacion']);
  // arma la url /venta
  $urlOperacion = "/" . $_GET['tipooperacion'];
  // $tipo_operacion: ["venta" - 1, "arriendo"- 2, "alojamiento" -3]
  // obtengo la id de la operacion buscada
  $operacion = array_search(strtolower($TipoOperacion), array_map('strtolower', $tipo_operacion));

  if (!$operacion) {// validacion q el tipo de operacion sea valida
    header("HTTP/1.0 404 Not Found");
    include('404.php');
    die();
  }

  $data_operacion = $tipo_operacion[$operacion]; // idem tipo op "Venta"

  $meta_descripcion .= " en " . strtolower($tipo_operacion[$operacion]);

  //breadcrumb
  $tituloBreadcrumb2 = $tituloBreadcrumb1 . " en " . strtolower($data_operacion);
  $linkBreadcrumb2 = codificar($tipo_inmueble_plural[$tipo]) . "/" . codificar($data_operacion);

  if ($_GET['acc'] == 2) { // si es hasta tipo operacion 
    $urlBase = "https://$pais1->pais_codificado.slideprop.com/api/contarInmuebles.php";
        
        
        $tipo_inmueble = isset($data_tipo) ? $data_tipo : '';
        $tipo_operacionApi = isset($TipoOperacion) ? $TipoOperacion : '';

        $fields = [
            'tipo_inmueble' => $tipo_inmueble,
            'tipo_operacion' => $tipo_operacionApi,

        ];

        $fields_string = http_build_query($fields);

    
        $curl = curl_init($urlBase);

        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);

       
        $data = curl_exec($curl);

        
        if ($data === false) {
            $response = ["error" => "Error en la petición: " . curl_error($curl)];
        } else {
            
            $response = json_decode($data, true);
        }

       
        curl_close($curl);

        // Procesar los resultados
        if (isset($response['error'])) {
            echo "Error: " . $response['error'];
        } elseif (!is_array($response)) {
            echo "Respuesta inválida.";
        } 
    generarDerivador("provincia_inmueble", $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search);
    $totalInmuebles = 0;
    $totalInmueblesBienesOnline = 0;
          foreach ($tipos as $provincia) {
            $totalInmueblesBienesOnline += $localidad->cant;
            foreach ($response as $keyResponse) {
              if ($provincia->nombre == $keyResponse['provincia_inmueble']) {
                 $provincia->cant = $provincia->cant + $keyResponse['cant'];
                 $totalInmuebles += $provincia->cant;
              }
            }
          } 
          //si slide trae anuncios, hacemos la sumatoria
          if ($totalInmuebles>0) {
            $total_anuncios = $totalInmuebles;
          }
    
    $titulo_h1 = $total_anuncios . " " . $tipo_inmueble_plural[$tipo] . " en " . strtolower($tipo_operacion[$operacion]) . " en " . utf8_encode($pais1->pais);
    $titulo_pagina = $titulo_h1 . " - BienesOnLine";

    //para adsense de busqueda
    $query_adsense = $tipo_inmueble_plural[$tipo] . " en " . strtolower($tipo_operacion[$operacion]) . " en " . utf8_encode($pais1->pais);
  }
}

if ($_GET['provinciainmueble']) {
  $array_provincia_inmueble = $array_provincia_inmueble ?: generarArrayProvincias();
  // $array_provincia_inmueble = generarArrayProvincias();
  $ProvinciaInmueble = $_GET['provinciainmueble']; // extraemos la provincia seleccionada por el usuario
  $urlProvincia = "/" . $_GET['provinciainmueble'];
  $id_prov = array_search($ProvinciaInmueble, $array_provincia_inmueble); // obtengo la id de la provincia buscada

  if (!$id_prov) {// validacion q la provincia sea valida
    header("HTTP/1.0 404 Not Found");
    include('404.php');
    die();
  }

  $provincia = buscarProvincia($id_prov);
  $data_provincia = $provincia->provincia; // Antioquia
  $array_localidad_inmueble = $array_localidad_inmueble ?: generarArrayLocalidades($id_prov);
  print_r($array_localidad_inmueble);
  //breadcrumbs
  $tituloBreadcrumb3 = $data_provincia;
  $linkBreadcrumb3 = $linkBreadcrumb2 . $urlProvincia;

  if ($_GET['acc'] == 3) {
    $urlBase = "https://$pais1->pais_codificado.slideprop.com/api/contarInmuebles.php";
        
        
        $tipo_inmueble = isset($data_tipo) ? $data_tipo : '';
        $tipo_operacionApi = isset($TipoOperacion) ? $TipoOperacion : '';
        $provincia_inmueble = isset($ProvinciaInmueble) ? $ProvinciaInmueble : '';

        $fields = [
            'tipo_inmueble' => $tipo_inmueble,
            'tipo_operacion' => $tipo_operacionApi,
            'provincia_inmueble' => $provincia_inmueble
        ];

        $fields_string = http_build_query($fields);

    
        $curl = curl_init($urlBase);

        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);

       
        $data = curl_exec($curl);

        
        if ($data === false) {
            $response = ["error" => "Error en la petición: " . curl_error($curl)];
        } else {
            
            $response = json_decode($data, true);
        }

       
        curl_close($curl);

        // Procesar los resultados
        if (isset($response['error'])) {
            echo "Error: " . $response['error'];
        } elseif (!is_array($response)) {
            echo "Respuesta inválida.";
        } 
    generarDerivador("localidad_inmueble", $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_searcha);
    $totalInmuebles = 0;
    $totalInmueblesBienesOnline = 0;
    foreach ($tipos as $localidad) {
      $totalInmueblesBienesOnline += $localidad->cant;
      foreach ($response as $keyResponse) {
        if ($localidad->nombre == $keyResponse['localidad_inmueble']) {
            $localidad->cant = $localidad->cant + $keyResponse['cant'];
            $totalInmuebles += $localidad->cant;
        }
      }
    }
    //si slide trae anuncios, hacemos la sumatoria
    if ($totalInmuebles>0) {
      $total_anuncios = $totalInmuebles;
    }
    // 3234 Casas en venta en Amazonas, Colombia
    $titulo_h1 = $total_anuncios . " " . $tipo_inmueble_plural[$tipo] . " en " . strtolower($tipo_operacion[$operacion]) . " en " . $data_provincia . ", " . utf8_encode($pais1->pais); // listo
    $titulo_pagina = $titulo_h1 . " - BienesOnLine";

    $meta_descripcion .= " en " . $data_provincia;

    # ver--------
    //para adsense de busqueda
    $query_adsense = $tipo_inmueble_plural[$tipo] . " en " . strtolower($tipo_operacion[$operacion]) . " en " . utf8_encode($pais1->pais); // @VER ESTO
  }

}


if ($_GET['localidadinmueble']) {

  $LocalidadInmueble = $_GET['localidadinmueble'];
  $urlLocalidad = "/" . $_GET['localidadinmueble'];
  $array_localidad_inmueble = $array_localidad_inmueble ?: generarArrayLocalidades($id_prov);

  $id_loc = array_search($LocalidadInmueble, $array_localidad_inmueble);
  // trae el nombre real de la localidad
  $sql_localidad = "Select * From db_localidades Where id = " . mysqli_real_escape_string($enlace, $id_loc);
  $rs_localidad = mysqli_query($enlace, $sql_localidad);
  $localidad = mysqli_fetch_object($rs_localidad);

  //validar si prov y loc son correctos
  if (!$localidad || $localidad->id_provincia != $provincia->id) {
    header("HTTP/1.0 404 Not Found");
    include('404.php');
    die();
  }
  $urlBase = "https://$pais1->pais_codificado.slideprop.com/api/contarInmuebles.php";
        
        
        $tipo_inmueble = isset($data_tipo) ? $data_tipo : '';
        $tipo_operacionApi = isset($TipoOperacion) ? $TipoOperacion : '';
        $provincia_inmueble = isset($ProvinciaInmueble) ? $ProvinciaInmueble : '';
        $localidad_inmuebleapi = isset($LocalidadInmueble) ? $LocalidadInmueble : '';
        $fields = [
            'tipo_inmueble' => $tipo_inmueble,
            'tipo_operacion' => $tipo_operacionApi,
            'provincia_inmueble' => $provincia_inmueble,
            'localidad_inmueble' => $localidad_inmuebleapi
        ];

        $fields_string = http_build_query($fields);

    
        $curl = curl_init($urlBase);

        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);

       
        $data = curl_exec($curl);

        
        if ($data === false) {
            $response = ["error" => "Error en la petición: " . curl_error($curl)];
        } else {
            
            $response = json_decode($data, true);
        }

        curl_close($curl);

        // Procesar los resultados
        if (isset($response['error'])) {
            echo "Error: " . $response['error'];
        } elseif (!is_array($response)) {
            echo "Respuesta inválida.";
        } 
        $data_localidad = $localidad->localidad;
        if (!$_GET['search_string']) {
          generarDerivador($groupby, $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search);

        }
        
        $totalInmueblesBienesOnline = $total_anuncios;
        foreach ($response as $keyResponse) {
          {
            $totalInmuebles+=$keyResponse['cant'];
            
          } 
          
        }
  $total_anuncios = $total_anuncios+$totalInmuebles;

  
  $titulo_h1 = $total_anuncios . " " . $tipo_inmueble_plural[$tipo] . " en " . strtolower($tipo_operacion[$operacion]) . " en " . $data_localidad . ", " . $data_provincia; // listo
  $titulo_pagina = $titulo_h1 . " - BienesOnLine";
  $meta_descripcion .= " en " . $data_localidad . ", " . $data_provincia;

  //breadcrumbs 
  $tituloBreadcrumb4 = $data_localidad;
  $linkBreadcrumb4 = $linkBreadcrumb3 . $urlLocalidad;

  
}




$adicional_h2 = " - Página 1";
if ($_GET['page']) {
  $titulo_pagina .= " - Página " . $_GET['page'];
  $meta_descripcion .= " - Página " . $_GET['page'];
  $adicional_h2 = " - Página " . $_GET['page'];
}


//@@@@@@@ QUERYS SP
// LLAMAMOS a SP getListadoAnuncios
$aviso_total = getListadoAnuncios($sort, $data_tipo, $data_operacion, $data_provincia, $data_localidad, $data_search, $pag);

foreach ($aviso_total as $key => $row) { // ver si lo puedo quitar
  // para filtro
  $aux_provincia[$key] = $row['provincia_inmueble'];
  $aux_operacion[$key] = $row['tipo_operacion'];
  $aux_habitaciones[$key] = $row['habitaciones'];
  $aux_tipo_inmueble[$key] = $row['tipo_inmueble'];
}

$num_total_interno = count($aviso_total) - 1; // num de anuncios mostrados =< 20
$num_total_registros = $total_anuncios; // total de anuncios en la categoria actual

$cantidad_total_paginas = ceil($num_total_registros / 20);


$descripcion_pagina = $total_anuncios . ' ' . $meta_descripcion;

// include('header-new2.php');
include('headerHome.php');
?>

<section class="dos-columnas">
  <div class="container">
    <?php if ($paginaActual or $linkBreadcrumb1) { ?>
      <nav aria-label="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
        <ol class="breadcrumb">
          <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo $dir_base; ?>" itemprop="item">
              <span itemprop="name">BienesOnline</span>
            </a>
            <meta itemprop="position" content="1" />
          </li>

          <?php if (isset($linkBreadcrumb1)) { ?>
            <li class="breadcrumb-item <?= !isset($linkBreadcrumb2) ? 'active' : '' ?>" itemprop="itemListElement" itemscope
              itemtype="https://schema.org/ListItem">
              <a <?php if (isset($linkBreadcrumb2)) { ?> href="<?php echo $dir_base . $linkBreadcrumb1; ?>" <?php } ?>
                itemprop="item">
                <span itemprop="name"><?php echo $tituloBreadcrumb1; ?></span>
              </a>
              <meta itemprop="position" content="2" />
            </li>
          <?php } ?>

          <?php if (isset($linkBreadcrumb2)) { ?>
            <li class="breadcrumb-item <?= !isset($linkBreadcrumb3) ? 'active' : '' ?>" itemprop="itemListElement" itemscope
              itemtype="https://schema.org/ListItem">
              <a <?php if (isset($linkBreadcrumb3)) { ?> href="<?php echo $dir_base . $linkBreadcrumb2; ?>" <?php } ?>
                itemprop="item">
                <span itemprop="name"><?php echo $tituloBreadcrumb2; ?></span>
              </a>
              <meta itemprop="position" content="3" />
            </li>
          <?php } ?>

          <?php if (isset($linkBreadcrumb3)) { ?>
            <li class="breadcrumb-item <?= !isset($linkBreadcrumb4) ? 'active' : '' ?>" itemprop="itemListElement" itemscope
              itemtype="https://schema.org/ListItem">
              <a <?php if (isset($linkBreadcrumb4)) { ?> href="<?php echo $dir_base . $linkBreadcrumb3; ?>" <?php } ?>
                itemprop="item">
                <span itemprop="name"><?php echo $tituloBreadcrumb3; ?></span>
              </a>
              <meta itemprop="position" content="4" />
            </li>
          <?php } ?>

          <?php if (isset($linkBreadcrumb4)) { ?>
            <li class="breadcrumb-item active" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
              <span itemprop="name"><?php echo $tituloBreadcrumb4; ?></span>
              <meta itemprop="position" content="5" />
            </li>
          <?php } ?>
        </ol>
      </nav>
    <?php } ?>
    <div class="row mt-3 mb-4">
      <div class="col-lg-12">
        <h1 class="mb-3"><?php echo $titulo_h1; ?></h1>

        <?php if (isset($_GET['all'])) { ?>
          <h3><strong>Por tipo:</strong></h3>
          <div class="row m-1 lista-subcategorias">
            <ul class="list-inline">
              <?php
              foreach ($tipos as $data) {
                // cargamos la categoria
                $search_string = $data->nombre;
                //buscamos tipo de inmueble en la busqueda. Si hay coincidencia $idTipo tendra "idInmueble" sino tendra "false"
                $idTipo = buscarCoincidencia($tipo_inmueble) ?: buscarCoincidencia($tipo_inmueble_plural);
                $url = codificar($tipo_inmueble_plural[$idTipo]); ?>
                <li class="list-item link-filtro"><a href="<?php echo $url; ?>"><?php echo $data->nombre; ?></a>
                  (<?php echo $data->cant; ?>)</li>
              <?php } ?>
            </ul>
          </div>
        <?php } ?>

        <?php if ($_GET['acc'] == 1) { ?>
          <h3><strong>Por operación:</strong></h3>
          <div class="row m-1 lista-subcategorias">
            <ul class="list-inline">
              <?php
              foreach ($tipos as $data) {
                $url = codificar($TipoInmueble) . "/" . codificar($data->nombre); ?>
                <li class="list-item link-filtro"><a
                    href="<?php echo $url; ?>"><?php echo $tipo_inmueble_plural[$tipo] . " en " . $data->nombre; ?></a>
                  (<?php echo $data->cant; ?>)</li>
              <?php } ?>
            </ul>
          </div>
        <?php } ?>

        <?php if ($_GET['acc'] == 2) { ?>
          <h3><strong>Por ubicación:</strong></h3>
          <div class="row m-1 lista-subcategorias">
            <ul class="list-inline">
              <?php
              foreach ($tipos as $provincia) {
                $url_provincia = $TipoInmueble . $urlOperacion . "/" . codificar($provincia->nombre);
                $titulo_enlace = $tipo_inmueble_plural[$tipo] . " en " . $tipo_operacion[$operacion] . " en " . $provincia->nombre;
                ?>
                <li class="list-item link-filtro">
                  <a href="<?php echo $url_provincia; ?>" title="<?php echo $titulo_enlace; ?>">
                    <?php echo $provincia->nombre; ?>
                  </a> (<?php echo $provincia->cant; ?>)
                </li>
              <?php } ?>
            </ul>
          </div><!--/.-->
        <?php } ?>

        <?php if ($_GET['acc'] == 3) { ?>
          <h3><strong>Por ubicación:</strong></h3>
          <div class="row m-1 lista-subcategorias">
            <ul class="list-inline">
              <?php
              foreach ($tipos as $localidad) {
                $url_localidad = $TipoInmueble . $urlOperacion . $urlProvincia . "/" . codificar($localidad->nombre);
                $titulo_enlace = $tipo_inmueble_plural[$tipo] . " en " . $tipo_operacion[$operacion] . " en " . $localidad->nombre;
                ?>
                <li class="list-item link-filtro">
                  <a href="<?php echo $url_localidad; ?>" title="<?php echo $titulo_enlace; ?>">
                    <?php echo $localidad->nombre; ?>
                  </a> (<?php echo $localidad->cant; ?>)
                </li>
              <?php } ?>
            </ul>
          </div>
        <?php } ?>
      </div>
    </div>
  </div><!-- container -->
</section>

<!--  LISTADOS CODIGO -->
<main id="content">
  <section class="pt-8 pb-11 bg-gray-01">
    <div class="container">
      <div class="row justify-content-center">
        <?php if ($deviceType == 'phone') { ?>
          <!-- Sidebar -->
          <div class="col-lg-4 order-2 order-lg-1 primary-sidebar sidebar-sticky" id="sidebar">
            <div class="primary-sidebar-inner">
              <!--16-April-2024 Adsance Code Start-->

              <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-1348792180260118"
                data-ad-slot="1070129731" data-ad-format="auto" data-full-width-responsive="true"></ins>
              <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
              </script>

            </div>
          </div>
          <!-- Search form End -->
        <?php } ?>

        <!-- google ads code start -->
        <script type="text/javascript" charset="utf-8">

          var pageOptions = {
            "pubId": "partner-pub-1348792180260118", // Make sure this is the correct client ID!
            "styleId": "8876960769",
            "query": "<?php echo $query_adsense; ?>" // Make sure the correct query is placed here!
          };

          var adblock1 = {
            "container": "afscontainer1", "maxTop": 2
          };

          var adblock2 = {
            "container": "afscontainer2", "number": 4
          };

          var adblock3 = {
            "container": "afscontainer3", "number": 3
          };


          _googCsa('ads', pageOptions, adblock1, adblock2, adblock3);
        </script>
        <!-- google ads code end -->

        <!-- listing ads content start -->
        <div class="col-lg-12 mb-8 mb-lg-0 order-1 order-lg-2" id="show_data_searching">
          <div class="row align-items-sm-center mb-4">
            <div class="col-md-6">
              <span class="fs-16 text-dark mb-0">Listado de <span
                  class="text-primary"><?php echo $num_total_registros ?></span>
                <?php echo ($tipoPlural . ' ' . $adicional_h2) ?></span>
            </div>

            <!-- Sortby, grid and listview icon section -->
            <div class="col-md-6 mt-4 mt-md-0">
              <div class="d-flex justify-content-md-end align-items-center">
                <div class="input-group border rounded input-group-lg w-auto bg-white mr-3">
                  <label class="input-group-text bg-transparent border-0 text-uppercase letter-spacing-093 pr-1 pl-3"
                    for="inputGroupSelect01"><i class="fas fa-align-left fs-16 pr-2"></i>Ordenar por:</label>
                  <select class="form-control border-0 bg-transparent shadow-none p-0 selectpicker sortby"
                    data-style="bg-transparent border-0 font-weight-600 btn-lg pl-0 pr-3" id="inputGroupSelect01"
                    name="sortby" style="display: block !important">
                    <option>Select</option>
                    <option value="1" <?php if (isset($sort) && $sort == 1) {
                      echo 'selected';
                    } ?>>Precio (de mayor a
                      menor)</option>
                    <option value="2" <?php if (isset($sort) && $sort == 2) {
                      echo "selected";
                    } ?>>Precio (de menor a
                      mayor)</option>
                    <option value="3" <?php if (isset($sort) && $sort == 3) {
                      echo "selected";
                    } ?>>Fecha (más recientes)
                    </option>
                    <option value="4" <?php if (isset($sort) && $sort == 4) {
                      echo "selected";
                    } ?>>Fecha (más antiguos)
                    </option>
                  </select>
                </div>
                <div class="d-none d-md-block ads_page_view" id="ads_page_view" style="width: 6em;">
                  <a data-view="list-view" class="fs-sm-18 text-dark" href="#">
                    <i class="fas fa-list"></i>
                  </a>
                  <!-- podria quitar el href y funciona igual, ya q el jquery toma el evento de cuando se modifica data-view -->
                  <!--
                  <a data-view="grid-view" class="fs-sm-18 text-dark opacity-2 ml-5" href="listados-grid.php">
                    <i class="fa fa-th-large"></i>
                  </a>
                  -->
                </div>
              </div>
            </div>
            <!-- End Sortby, grid and listview icon section -->
          </div>

          <!-- banner ads code -->
          <ins class="adsbygoogle" style="display:block; overflow: hidden !important;" data-ad-format="fluid"
            data-ad-layout-key="-dp+r+3l-oy+vk" data-ad-client="ca-pub-1348792180260118"
            data-ad-slot="4648414108"></ins>
          <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
          </script>
          <!-- End of banner ads code -->

          <!-- displaying list item -->
          <?php $ads_page_display_type = isset($_SESSION["ads_page_display_type"]) ? $_SESSION["ads_page_display_type"] : '';
          $conn = $enlace;
          if ($ads_page_display_type != "grid-view") {
            include "listados-listview.php";
          } else {
            include "listados-gridview.php";
          } ?>
          
          <?php
          echo $totalInmueblesBienesOnline;
          $total_paginas = ceil($num_total_registros / 20);
          $pagina = $_GET['page'] ? $_GET['page'] : 1;
          if ($contador <20) {
            $totalPaginasBienesOnline = ceil($totalInmueblesBienesOnline/20);
            if ($contador >0) {
              $min = 0;
              //$max = 20 - 1 - $contador;
              $max = 20 - $contador;
            }else
            {
              $primeraCantidadSlide = ($totalPaginasBienesOnline*20)-$totalInmueblesBienesOnline;
             
               //$max = $primeraCantidadSlide + (($pagina - $totalPaginasBienesOnline)*20);
               $max = 20;
               $min =  (($pagina-1)*20) - $totalInmueblesBienesOnline;
            }
            include "listados-listview-slide.php";
        }
          ?>
          <!-- paginacion -->
          <?php
          $total_paginas = ceil($num_total_registros / 20);  //cantidad de páginas a mostrar
          $pagina = $_GET['page'] ? $_GET['page'] : 1; //asignamos la pagina actual
          
          if ($total_paginas > 1) { // si hay mas de 1 pagina mostramos la paginacion
            ?>

            <nav class="pt-6">
              <ul class="pagination justify-content-center rounded-active mb-0">
                <?php

                $urlbase = $urlInmuebles . $urlTipo . $urlOperacion . $urlProvincia . $urlLocalidad;
                $previous_page = $pagina - 3;
                $next_page = $pagina + 1;


                if ($previous_page > 0) { // si existe pagina previa. Agregamos boton para ir a la 1ra pagina
                  $url_paginacion = !isset($_GET['search_string']) ? $urlbase : "buscar-" . $_GET['search_string'] . ".php";
                  if (isset($sort) && $sort != 0)
                    $url_paginacion .= "?sort=" . $sort;

                  ?>
                  <li class="page-item"><a class="page-link" href="<?php echo $url_paginacion; ?>" rel="pervious">
                      <i class="far fa-angle-double-left"></i></a></li>
                  <?php
                }

                for ($i = max(1, $pagina - 2); $i <= min($pagina + 2, $total_paginas); $i++) {
                  if (isset($_GET['search_string']))
                    $url_paginacion = $pagina == $i ? "#" : ($i == 1 ? "buscar-" . $_GET['search_string'] . ".php" : "buscar-" . $_GET['search_string'] . ".php" . "?page=" . $i);
                  else
                    $url_paginacion = $pagina == $i ? "#" : ($i == 1 ? $urlbase : $urlbase . "?page=" . $i);

                  // si tiene orden, lo agregamos
                  $url_paginacion .= isset($sort) && $sort != 0 ? ($i == 1 ? "?sort=" . $sort : "&sort=" . $sort) : "";
                  ?>

                  <li class="page-item <?php if ($pagina == $i)
                    echo "active" ?>">
                      <a class="page-link" <?php if ($url_paginacion != "#")
                    echo 'href="' . $url_paginacion . '"' ?>>
                      <?php echo $i ?>
                    </a>
                  </li>
                  <?
                  if ($i == $next_page)
                    $siguiente = $url_paginacion;
                } ?>

                <li class="page-item"><a class="page-link" href="<?php echo $siguiente; ?>" rel="next">
                    <i class="far fa-angle-double-right"></i></a></li>
              </ul>
            </nav>
          <?php } ?>
          <!-- Pagination code end -->
        </div>
        <!-- listing ads content End -->
      </div>
    </div>
  </section>


  <div id="ad-container" style="text-align: center;">
    <?php if ($deviceType == 'computer') { ?>
      <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-1348792180260118" data-ad-slot="8565476372"
        data-ad-format="auto" data-full-width-responsive="true"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    <?php } ?>
  </div>
</main>
<? $json_ld_anuncios = json_encode($json_ld_anuncios, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
<!-- jsonld -->
<script type="application/ld+json">
  <?php echo $json_ld_anuncios; ?>
</script>
<?php
include('footer-new1.php');
?>

<script>
  $(document).ready(function () {
    // Check if window.history.replaceState is available
    if (window.history.replaceState)
      // This will prevent resubmission on refresh
      window.history.replaceState(null, null, window.location.href);


    $("#clear_btn").click(function () {
      // Redirect to listados.php
      window.location.href = 'listados.php';
    });
  });


  var selectSort = document.getElementById("inputGroupSelect01");

  selectSort.addEventListener("change", function () {
    // Get the selected option's value (sortValue)
    var sortValue = selectSort.value;

    // Get the current URL
    var currentUrl = window.location.href;

    // Create a URL object
    var url = new URL(currentUrl);

    // Remove the 'page' query parameter if it exists
    url.searchParams.delete('page');

    if (sortValue == "Select") url.searchParams.delete('sort');
    else url.searchParams.set('sort', sortValue);

    // Replace the URL with the updated one and trigger a page reload
    window.location.href = url.toString();
  });

</script>
<script>
  $(document).ready(function () {
    $('#ads_page_view a').click(function (e) {
      e.preventDefault();
      //alert("testing");
      $(this).removeClass('opacity-2');
      $(this).siblings().addClass('opacity-2');
      var action = 'ads_page_display_type';
      var type = $(this).data('view');
      $.ajax({
        url: "action.php",
        method: "POST",
        dataType: "json",
        data: {
          action: action,
          type: type
        },
        success: function (data) {
          //console.log(data);
          location.reload();
        },
        error: function (xhr, status, error) {
          console.log("AJAX Error:", status, error);
        }
      });

    });
  });
</script>
<!-- Bootstrap core JavaScript -->
<script src="dis-boost/vendor/jquery/jquery.min.js"></script>
<script src="dis-boost/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
mysqli_close($enlace);
?>