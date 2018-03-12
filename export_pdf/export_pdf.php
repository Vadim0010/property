<?php

/*
Plugin Name: Dreamvilla Export Data
Description: Экспортировать данные о недвижимости в PDF файл
*/

add_action('admin_menu', 'addAdminMenu');
function addAdminMenu()
{
    add_menu_page('Экспорт в PDF','Property Экспорт',8,'dreamvilla-export-pdf', 'data_export_to_pdf', 'dashicons-upload', '27.3');
}

function data_export_to_pdf()
{
    try {

        $categories = get_terms([
            'taxonomy' => 'property_category',
            'hide_empty' => false,
        ]);

        $locations = get_terms([
            'taxonomy' => 'location',
            'hide_empty' => false,
        ]);


        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce']) ) {
                throw new Exception('Не удалось импортировать данные. Nonce check failed on enable cookie based brute force prevention feature!');
            }

            if ( isset($_POST['update-pdf']) ) {
                generatePdfFileWithListProperties();
            } elseif ( isset($_POST['download-pdf']) ) {
                downloadPdf();
            }
        }

    } catch (Exception $e) {
        echo $e->getMessage();die;
    }

    require_once ( plugin_dir_path(__FILE__ ) . 'includes/index.php');
}

function generatePdfFileWithListProperties()
{
    require_once (plugin_dir_path (__FILE__) . 'lib/html5lib/Parser.php');
    require_once (plugin_dir_path (__FILE__) . 'lib/php-font-lib/src/FontLib/Autoloader.php');
    require_once (plugin_dir_path (__FILE__) . 'lib/php-svg-lib/src/autoload.php');
    require_once (plugin_dir_path (__FILE__) . 'src/Autoloader.php');
    Dompdf\Autoloader::register();

    ini_set('max_execution_time', 3600);
    ini_set('memory_limit', '-1');

    $properties = get_posts([
        'numberposts' => '-1',
        'post_type' => 'property',
        'post_status' => 'publish',
        'tax_query' => [
            [
                'taxonomy' => 'property_category',
                'field' => 'id',
                'terms' => isset($_POST['property_category']) ? $_POST['property_category'] : '-1'
            ],
            [
                'taxonomy' => 'location',
                'field' => 'id',
                'terms' => isset($_POST['property_category']) ? $_POST['location'] : '-1'
            ]
        ]
    ]);

    ob_start();
    require_once ( plugin_dir_path(__FILE__ ) . 'includes/properties-list.php');
    $html = ob_get_clean();

    $domPdf = new \Dompdf\Dompdf();
    $domPdf->setPaper("A4");
    $domPdf->loadHtml($html);
    $domPdf->render();
    $output = $domPdf->output();

    file_put_contents(WP_CONTENT_DIR . '/uploads/property.pdf', $output);

    downloadPdf();
}

function downloadPdf()
{
    $filePath = sprintf('%s/uploads/property.pdf', WP_CONTENT_DIR);

    if (file_exists($filePath)) {

        ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        readfile($filePath);
    }

    exit;
}

function getPropertyData($property)
{
    $data = [];

    $data['title'] = $property->post_title ? $property->post_title : 'Название отсутствует';
    $propertyMeta = get_post_meta($property->ID);

    if ( $propertyMeta && ! is_wp_error($propertyMeta) ) {
        $price = unserialize($propertyMeta['pprice'][0]);
        $data['price'] = $price[0] ? $price[0] : 'Цена не указана';
        $data['property_code'] = $propertyMeta['property_code'][0] ? $propertyMeta['property_code'][0] : 'Код не указан';
        $photo = $propertyMeta['_thumbnail_id'][0] ? get_post($propertyMeta['_thumbnail_id'][0]) : $data['photo'] = false;

        if (  $photo && ! is_wp_error($photo) ) {
            $imageSrc = wp_get_attachment_image_src($photo->ID);
            $data['photo'] = is_wp_error($imageSrc) || ! $imageSrc ? false : ABSPATH . parse_url( $imageSrc[0], PHP_URL_PATH);
        }
    } else {
        $data['price'] = 'Цена не указана';
        $data['property_code'] = 'Код не указан';
        $data['photo'] = false;
    }

    $types = wp_get_post_terms( $property->ID, 'property_category' );
    $data['types'] = is_wp_error($types) || ! $types ? 'Тип не указан' : $types;
    $locations = wp_get_post_terms( $property->ID, 'location' );
    $data['locations'] = is_wp_error($types) || ! $locations ? 'Расположение не указано' : $locations;

    return $data;
}

add_action( 'admin_enqueue_scripts', 'setStyle' );
function setStyle()
{
    wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/css/bootstrap.min.css');
    wp_enqueue_style( 'dreamvilla-import-data', get_template_directory_uri() . '/css/dreamvilla-import-data.css');
}