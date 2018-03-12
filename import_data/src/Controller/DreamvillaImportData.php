<?php

class DreamvillaImportData
{
    const URL_IMPORT = 'http://api.pafilia.com/feed/datafeed?lang=RU&limit=10';

    /**
     * @var WP_User
     */
    private $user;

    /**
     * @var Property
     */
    private $propertyModel;

    /**
     * @var array
     */
    private $developments = [];

    /**
     * @var array
     */
    private $properties = [];

    /**
     * @var
     */
    private $propertyFields;

    /**
     * @var array
     */
    private $propertyImport = [ 'propertyType', 'propertyStatus', 'propertyCreated', 'propertyUpdated',
        'propertyref', 'propertyname', 'bedrooms', 'bathroomsCommon', 'bathroomsEnSuite', 'WC', 'bathrooms',
        'price', 'storage_room', 'utility_room', 'extra_room', 'gardenArea', 'landscaping', 'disabledEntrance',
        'orientation', 'kitchenType', 'areas', 'developmentRef', 'media', 'description', 'pool', 'features' ];

    /**
     * @var array
     */
    private $parking = [
        'handicapped' => 'Парковка для инвалидов',
        'private' => 'Частная парковка',
        'residents' => 'Парковка для жителей',
        'guest' => 'Парковка для гостей',
        'uncovered residents' => 'Открытая автостоянка'
    ];

    /**
     * @var array
     */
    private $errors = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action( 'admin_enqueue_scripts', [$this, 'setStyle'] );
        add_action( 'admin_enqueue_scripts', [$this, 'setScript'] );
    }

    /**
     * Import page
     */
    public function indexAction()
    {
        try {

            $this->user = wp_get_current_user();
            $this->propertyModel = new Property();

            if (! $this->user) {
                throw new Exception('Только зарегистрированные пользователи могут импортировать данные');
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce']) ) {
                    throw new Exception('Не удалось импортировать данные. Nonce check failed on enable cookie based brute force prevention feature!');
                }

                ini_set('max_execution_time', 3600);
                ini_set('memory_limit', '-1');

                $data = new SimpleXMLElement(static::URL_IMPORT, LIBXML_NOCDATA, true);
                $properties = $data->properties;
                $developments = $data->developments;

                if ( $developments->children() ) {
                    foreach ($developments->children() as $development) {
                        $this->setDevelopments($development->attributes()->id, $development);
                    }
                } else {
                    array_push($this->errors, 'Список значений "Development" пуст');
                }

                if ( $properties->children() ) {
                    foreach ($properties->children() as $property) {
                        $this->addNewProperty($property);
                        $this->checkPropertyAvailability($property);
                    }
                } else {
                    array_push($this->errors, 'Список значений "Property" пуст');
                }
            }

        } catch (Exception $e) {
            array_push(
                $this->errors,
                sprintf('Не удалось выполнить импорт: %s', $e->getMessage())
            );
        }

        $this->getViews($this->errors);
    }

    /**
     * Add a new "Property" to the database or update an existing one
     *
     * @param $property
     */
    public function addNewProperty($property)
    {
        $action = 'edit';
        $propertyFields = $this->getPropertyFields($property);
        $post_id = $this->propertyModel->findProperty($propertyFields);

        if ( !$post_id && ($_POST['dreamvilla-import-data-check-onlyEdit'] || (! $_POST['dreamvilla-import-data-check-update'] && $_POST['dreamvilla-import-data-check-image'])) ) {
            return;
        }

        /** Get the development for the current Property */
        $propertyDevelopment = $this->getDevelopmentForProperty($propertyFields);

        if ( $post_id ) {
            if ( ! $_POST['dreamvilla-import-data-check-update'] && ! $_POST['dreamvilla-import-data-check-image'] && ! $_POST['dreamvilla-import-data-check-onlyEdit'] ) {
                return;
            } elseif ( $_POST['dreamvilla-import-data-check-image'] ) {
                $this->removeMediaGallery($post_id, $propertyFields);

                if (! $_POST['dreamvilla-import-data-check-update'] && ! $_POST['dreamvilla-import-data-check-onlyEdit']) {
                    $this->updateMediaGallery($propertyFields, $propertyDevelopment, $post_id);
                    $this->setProperties($action, $post_id);
                    return;
                }
            }
        }

        $post_data = [
            'post_content' => ( ! empty( $propertyFields['description'] ) ) ? sanitize_text_field( trim( $propertyFields['description'] ) ) : '',
            'post_title'    => ( ! empty( $propertyFields['propertyname'] ) ) ? sanitize_text_field( trim( $propertyFields['propertyname'] ) ) : '',
        ];

        if ( ! $post_id ) {
            $action = 'new';
            $post_data['post_author'] = $this->user->ID;
            $post_data['post_type'] = 'property';

            if ( $_POST['dreamvilla-import-data-publish'] == 'publish') {
                $post_data['post_status'] = 'publish';
            } else {
                $post_data['post_status'] = 'draft';
            }

            $post_id = wp_insert_post( $post_data, true );
        } else {
            $post_data['ID'] = $post_id;
            $post_id = wp_update_post( $post_data, true );
        }

        if ( is_wp_error($post_id)  ) {
            array_push($this->errors, sprintf(
                    'Не удалось добавить property (%s). %s',
                    $property->attributes()->id,
                    $post_id->get_error_message()
                )
            );
            return;
        }

        $this->setProperties($action, $post_id);

        /** Add Property Type */
        $this->addPropertyTermsForNewProperty('property_category', $propertyFields['propertyType'], $propertyFields, $post_id);
        /** Add Property Status */
        $this->addPropertyTermsForNewProperty('property_status', $propertyFields['propertyStatus'], $propertyFields, $post_id);

        /** Add Property Location */
        if ( $propertyDevelopment ) {
            $this->addPropertyTermsForNewProperty('location', $propertyDevelopment->city, $propertyFields, $post_id);
        }

        /** Add Property Features */
//        $this->addPropertyTermsForNewProperty($propertyFields['features'], $propertyFields, $post_id);

        /** Add Property other fields */
        $this->addFieldsForNewProperty($propertyFields, $propertyDevelopment, $post_id, $action);
    }

    /**
     * Add terms to the Property
     *
     * @param $taxonomy
     * @param $field
     * @param $propertyFields
     * @param $post_id
     */
    public function addPropertyTermsForNewProperty($taxonomy, $field, $propertyFields, $post_id)
    {
        $propertyTerms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if ( is_wp_error($propertyTerms)  ) {
            array_push($this->errors, sprintf(
                "Не удалось найти список %s для (%s) %s. %s",
                    $taxonomy,
                    $propertyFields['propertyref'],
                    $propertyFields['propertyname'],
                    $propertyTerms->get_error_message()
                )
            );
            return;
        }

        if ( ! empty( $field) ) {

            $propertyTerms = array_filter($propertyTerms, function($term) use ($field) {
                return mb_strtolower($term->name) == mb_strtolower($field);
            });

            if ( ! count($propertyTerms)) {
                $propertyTerms[] = wp_insert_term( $field, $taxonomy);

                if ( is_wp_error($propertyTerms[0])  ) {
                    foreach ($propertyTerms as $term) {
                        array_push($this->errors, sprintf(
                                'Не удалось добавить %s - "%s", для (%s) %s. %s',
                                $taxonomy,
                                $field,
                                $propertyFields['propertyref'],
                                $propertyFields['propertyname'],
                                $term->get_error_message()
                            )
                        );
                    }
                    return;
                }
            }

            foreach ($propertyTerms as $term) {
                $termError = wp_set_object_terms($post_id, $term->term_id, $taxonomy);

                if ( is_wp_error($termError)  ) {
                    array_push($this->errors, sprintf(
                            'Не удалось добавить %s - "%s", для (%s) %s',
                            $taxonomy,
                            $field,
                            $propertyFields['propertyref'],
                            $propertyFields['propertyname']
                        )
                    );
                }
            }
        }
    }

    /**
     * Add Property other fields
     *
     * @param $propertyFields
     * @param $propertyDevelopment
     * @param $post_id
     */
    public function addFieldsForNewProperty($propertyFields, $propertyDevelopment, $post_id, $action)
    {
        if ( ! wp_check_post_lock( $post_id ) ) {
            wp_set_post_lock( $post_id );
            update_post_meta( $post_id, '_edit_last', $this->user->ID );
        }

        update_post_meta( $post_id, 'dreamvilla_topbar_show', 1 );
        update_post_meta( $post_id, 'dreamvilla_header_show', 1 );
        update_post_meta( $post_id, 'dreamvilla_footer_show', 1 );

        if ( $propertyFields['propertyref'] ) {
            update_post_meta( $post_id, 'property_code', sanitize_text_field($propertyFields['propertyref']) );
        }

        update_post_meta( $post_id, 'slide_template', RevSliderFunctions::getPostVariable('slide_template', 'default') );

        $proom = [];
        $countBedroom = (int) $propertyFields['bedrooms'];
        if ( (int) $propertyFields['bedrooms'] > 0 ) {

            for ($i = 1; $i <= (int) $countBedroom; ++$i) {
                $proom[] = ['proomsize' => 'По запросу', 'proomtype' => 'Bedroom' ];
            }

            update_post_meta( $post_id, 'propertytotalroom', $countBedroom );
        } else {
            update_post_meta( $post_id, 'propertytotalroom', '' );
        }
        update_post_meta( $post_id, 'propertyroom', $proom );

        $pbathroom = [];
        $countBathroom = (int) $propertyFields['bathrooms'];
        if ( (int) $propertyFields['bathrooms'] > 0 ) {
            update_post_meta( $post_id, 'propertytotalbathroom', $countBathroom );
        } else {
            update_post_meta( $post_id, 'propertytotalbathroom', '' );
        }
        update_post_meta( $post_id, 'propertybathroom', $pbathroom );


        $pkitchen = [];
        update_post_meta( $post_id, 'propertykitchen', $pkitchen );

        $swimmingpool = [];
        update_post_meta( $post_id, 'propertyswimmingpool', $swimmingpool );

        $pgym = [];
        update_post_meta( $post_id, 'propertygym', $pgym );


        $essentialInfarmation = [];
        if( $propertyDevelopment && $propertyDevelopment->deliveryDate ){
            $date = new DateTime();
            $deliveryDate = strtotime($propertyDevelopment->deliveryDate);

            $essentialInfarmation[] = [
                'essentialtitle' => 'Стадия строительства',
                'essentialvalue' => $deliveryDate <= $date->getTimestamp() ? 'Завершено' : 'Не закончено'
            ];
        }
        if ( (float) $propertyFields['areas']->totalCoveredArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Крытая площадь',
                'essentialvalue' => (float) $propertyFields['areas']->totalCoveredArea . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->BalconyCoveredArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Крытая терраса',
                'essentialvalue' => (float) $propertyFields['areas']->BalconyCoveredArea  . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->balconyUncoveredArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Открытые терассы',
                'essentialvalue' => (float) $propertyFields['areas']->balconyUncoveredArea  . ' м²'
            ];
        }
        if ( $countBedroom ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Спальни',
                'essentialvalue' => $countBedroom
            ];
        }
        if ( (float) $propertyFields['areas']->communalArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Общественный площадь',
                'essentialvalue' => (float) $propertyFields['areas']->communalArea  . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->plotArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Площадь участка',
                'essentialvalue' => (float) $propertyFields['areas']->plotArea  . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->guestHouseArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Площадь гостевого дома',
                'essentialvalue' => (float) $propertyFields['areas']->guestHouseArea  . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->patioArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Площадь внутреннего дворика',
                'essentialvalue' => (float) $propertyFields['areas']->patioArea  . ' м²'
            ];
        }
        if ( (float) $propertyFields['areas']->roofGardenArea > 0 ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Сад на крыше',
                'essentialvalue' => (float) $propertyFields['areas']->roofGardenArea  . ' м²'
            ];
        }
        if ( in_array( mb_strtolower((string) $propertyFields['storage_room']), ['yes', 'да', 'есть']) ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Складское помещение',
                'essentialvalue' => 'Есть'
            ];
        }
        if ( in_array( mb_strtolower((string) $propertyFields['utility_room']), ['yes', 'да', 'есть']) ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Подсобное помещение',
                'essentialvalue' => 'Есть'
            ];
        }
        if ( in_array( mb_strtolower((string) $propertyFields['extra_room']), ['yes', 'да', 'есть']) ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Дополнительная комната',
                'essentialvalue' => 'Есть'
            ];
        }
        if ( in_array( mb_strtolower((string) $propertyFields['gardenArea']), ['yes', 'да', 'есть']) ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Сад',
                'essentialvalue' => 'Есть'
            ];
            $this->addPropertyTermsForNewProperty('features', 'Сад', $propertyFields, $post_id);
        }
        if ( in_array( mb_strtolower((string) $propertyFields['landscaping']), ['yes', 'да', 'есть']) ) {
            $essentialInfarmation[] = [
                'essentialtitle' => 'Ландшафтный дизайн',
                'essentialvalue' => 'Есть'
            ];
        }
        if ( $propertyFields['pool'] || ($propertyDevelopment && $propertyDevelopment->pool) ) {
            $pool = [];

            if ( $propertyFields['pool'] ) {
                $pool[] = (string) $propertyFields['pool']->attributes()->type == 'private' && (int) $propertyFields['pool']->attributes()->count > 0 ? 'Личный' : null;
            }

            if ( $propertyDevelopment && $propertyDevelopment->pool ) {
                $pool[] = (string) $propertyDevelopment->pool->attributes()->type == 'communal' && (int) $propertyDevelopment->pool->attributes()->count > 0 ? 'Общий' : null;
            }

            if ( count($pool) ) {
                $essentialInfarmation[] = [
                    'essentialtitle' => 'Бассейн',
                    'essentialvalue' => implode(', ', $pool)
                ];
                $this->addPropertyTermsForNewProperty('features', 'Бассейн', $propertyFields, $post_id);
            }
        }

        if ( $propertyDevelopment && $propertyDevelopment->parking ) {

            $parking = explode(',', (string) $propertyDevelopment->parking);
            $essentialvalue = [];

            foreach ($parking as $key) {
                $parkingType = mb_strtolower(trim($key));

                if (array_key_exists($parkingType, $this->parking)) {
                    $essentialvalue[] = $this->parking[$parkingType];
                }
            }

            $essentialInfarmation[] = [
                'essentialtitle' => 'Гараж/паркинг',
                'essentialvalue' => count($essentialvalue) ? implode(', ', $essentialvalue) : 'Есть'
            ];
            $this->addPropertyTermsForNewProperty('features', 'Гараж/Паркинг', $propertyFields, $post_id);
        }
        update_post_meta( $post_id, 'essentialinformation', $essentialInfarmation );

        $pamenitiesarray = [];
        update_post_meta( $post_id, 'propertyamenities', $pamenitiesarray );

        $pinteriorarray = [];
        update_post_meta( $post_id, 'pinteriorarray', $pinteriorarray );

        $pexteriorarray = [];
        update_post_meta( $post_id, 'pexteriorarray', $pexteriorarray );

        /**
         * Save images to gallery
         * @var  $pgallery
         */
        if ( $action == 'new' || ( $action == 'edit' && $_POST['dreamvilla-import-data-check-image'] ) ) {
            $this->updateMediaGallery($propertyFields, $propertyDevelopment, $post_id);
        }

        $propertyfloorsarray = [];
        update_post_meta( $post_id, 'propertyfloors', $propertyfloorsarray );

        $price = '';
        $pprice = [];
        if ( $propertyFields['price'] ) {
            $price = filter_var( $propertyFields['price'], FILTER_SANITIZE_NUMBER_INT);
            $pprice[] = sprintf('от € %s', number_format( $price, 0, ',', '.' ));
        }

        update_post_meta( $post_id, 'price', $price );
        update_post_meta( $post_id, 'propertybannerimage', '' );
        update_post_meta( $post_id, 'pstatus', 'sale' );
        update_post_meta( $post_id, 'pprice', $pprice );

        $psbuilduparea = [];
        if ( $propertyFields['areas'] && (float) $propertyFields['areas']->buildingArea > 0 ) {
            $psbuilduparea[] = sprintf('от %d м²', (int) $propertyFields['areas']->buildingArea);
        }

        update_post_meta( $post_id, 'psbuilduparea', $psbuilduparea );
        update_post_meta( $post_id, 'pbuiltupyear', '' );
        update_post_meta( $post_id, 'pavailablefrom', '' );
        update_post_meta( $post_id, 'pfacing', '' );
        update_post_meta( $post_id, 'pnooffloor', '' );
        update_post_meta( $post_id, 'pnoofparking', '');
        update_post_meta( $post_id, 'pnoofgarage', '' );
        update_post_meta( $post_id, 'pwaterflow', '' );
        update_post_meta( $post_id, 'pwstoragecapacity', '' );
        update_post_meta( $post_id, 'pnoofaircondition', '' );
        update_post_meta( $post_id, 'pparking', '' );
        update_post_meta( $post_id, 'pfencing', '' );
        update_post_meta( $post_id, 'psolar', '' );
        update_post_meta( $post_id, 'pgarden', '' );
        update_post_meta( $post_id, 'psecurity', '');
        update_post_meta( $post_id, 'pcctv', '' );
        update_post_meta( $post_id, 'pfireexting', '' );
        update_post_meta( $post_id, 'pchildrenplayground', '' );
        update_post_meta( $post_id, 'pphobenumber', '' );
        update_post_meta( $post_id, 'pemailid', '' );
        update_post_meta( $post_id, 'ptime', '' );
        update_post_meta( $post_id, 'pheating', '');
        update_post_meta( $post_id, 'pbasement', '');
        update_post_meta( $post_id, 'pbasementtype', '');
        update_post_meta( $post_id, 'pexterior', '');
        update_post_meta( $post_id, 'proof', '');
        update_post_meta( $post_id, 'pconstruction', '');
        update_post_meta( $post_id, 'pfoundation', '');
        update_post_meta( $post_id, 'pfruntexposure', '');
        update_post_meta( $post_id, 'pfrontagemeter', '');
        update_post_meta( $post_id, 'pflooring', '');
        update_post_meta( $post_id, 'pgoodsincluded', '');
        update_post_meta( $post_id, 'pfetured', 'yes');
        update_post_meta( $post_id, 'padvertisement', '');
        update_post_meta( $post_id, 'pvideoheight', '');
        update_post_meta( $post_id, 'pvideowidth', '');
        update_post_meta( $post_id, 'pvideourl', '');

        $streetviewlat = '';
        $streetviewlng = '';
        if ( $propertyDevelopment->coordinates ) {
            $coordinates = explode(',', (string) $propertyDevelopment->coordinates);
            if ( count($coordinates) ) {
                $streetviewlat = trim($coordinates[0]);
                $streetviewlng = trim($coordinates[1]);
            }
        }
        update_post_meta( $post_id, 'streetviewlat', $streetviewlat);
        update_post_meta( $post_id, 'streetviewlng', $streetviewlng);

        update_post_meta( $post_id, 'paddress', '' );
        update_post_meta( $post_id, 'ppincode', '' );

        $pcountry = $propertyDevelopment->country ? (string) $propertyDevelopment->country : '';
        update_post_meta( $post_id, 'pcountry', $pcountry );

        $pstate = $propertyDevelopment->province ? (string) $propertyDevelopment->province : '';
        update_post_meta( $post_id, 'pstate', $pstate );

        $pcity = $propertyDevelopment->city ? (string) $propertyDevelopment->city : '';
        update_post_meta( $post_id, 'pcity', $pcity );

        update_post_meta( $post_id, 'platlon', [$streetviewlat, $streetviewlng] );
        update_post_meta( $post_id, 'pvideoplaceholder', '');
        update_post_meta( $post_id, 'pdocumentsstatus', ['pdocumentsstatus' => '', 'pdocumentstitle' => '' ] );
        update_post_meta( $post_id, 'pdocuments', '' );
        update_post_meta( $post_id, 'google_near_by_place', '' );
        update_post_meta( $post_id, 'google_near_by_custom_place', '' );
        update_post_meta( $post_id, 'pagent', '' );
        update_post_meta( $post_id, 'psubproperty', '' );
    }

    /**
     * @param $property
     * @return array
     */
    public function getPropertyFields($property)
    {
        $this->propertyFields = [];

        $this->propertyFields['propertyType'] = $property->propertyType;
        $this->propertyFields['propertyStatus'] = $property->propertyStatus;
        $this->propertyFields['propertyCreated'] = $property->propertyCreated;
        $this->propertyFields['propertyUpdated'] = $property->propertyUpdated;
        $this->propertyFields['propertyref'] = $property->propertyref;
        $this->propertyFields['propertyname'] = $property->propertyname;
        $this->propertyFields['bedrooms'] = $property->bedrooms;
        $this->propertyFields['bathroomsCommon'] = $property->bathroomsCommon;
        $this->propertyFields['bathroomsEnSuite'] = $property->bathroomsEnSuite;
        $this->propertyFields['WC'] = $property->WC;
        $this->propertyFields['bathrooms'] = $property->bathrooms;
        $this->propertyFields['price'] = $property->price;
        $this->propertyFields['storage_room'] = $property->storage_room;
        $this->propertyFields['utility_room'] = $property->utility_room;
        $this->propertyFields['extra_room'] = $property->extra_room;
        $this->propertyFields['gardenArea'] = $property->gardenArea;
        $this->propertyFields['landscaping'] = $property->landscaping;
        $this->propertyFields['disabledEntrance'] = $property->disabledEntrance;
        $this->propertyFields['orientation'] = $property->orientation;
        $this->propertyFields['kitchenType'] = $property->kitchenType;
        $this->propertyFields['areas'] = $property->areas;
        $this->propertyFields['developmentRef'] = $property->developmentRef;
        $this->propertyFields['media'] = $property->media;
        $this->propertyFields['description'] = $property->description;
        $this->propertyFields['pool'] = $property->pool;
        $this->propertyFields['features'] = $property->features;

        return $this->propertyFields;
    }

    /**
     * Add section to the admin menu
     */
    public function addAdminMenu()
    {
        add_menu_page('Импорт','Property Импорт',8,'dreamvilla-import-xml', [ $this, 'indexAction'], 'dashicons-download', '27.2');
    }

    /**
     * Set css style
     */
    public function setStyle()
    {
        wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/css/bootstrap.min.css');
        wp_enqueue_style( 'dreamvilla-import-data', get_template_directory_uri() . '/css/dreamvilla-import-data.css');
    }

    /**
     * Set javascript
     */
    public function setScript()
    {
        wp_enqueue_script( 'dreamvilla_import_xml', get_template_directory_uri() . '/js/dreamvilla_import_xml.js', ['jquery'], false, true);
    }

    /**
     * Get templates
     *
     * @param $xmlErrors
     */
    public function getViews($xmlErrors)
    {
        $user_ID = $this->user->ID;

        require_once ( WP_PLUGIN_DIR . '/dreamvilla_import_data/src/views/index.php' );
    }

    /**
     * @param $action
     * @param $properties
     */
    public function setProperties($action, $properties)
    {
        $this->properties[$action][] = $properties;
    }

    /**
     * @param $key
     * @param $development
     */
    public function setDevelopments($key, $development)
    {
        $this->developments[ (string) $key ] = $development;
    }

    /**
     * Check for a new field
     *
     * @param $property
     */
    public function checkPropertyAvailability($property)
    {
        foreach ($property as $value) {
            if (! in_array($value->getName(), $this->propertyImport)) {
                array_push($this->errors, sprintf(
                    'У "property" (%s) "%s" найдено новое значение "%s"',
                    $property->propertyref,
                    $property->propertyname,
                    $value->getName()
                ));
            }
        }
    }

    /**
     * Get the development for the current Property
     *
     * @param $property
     * @return bool|mixed
     */
    public function getDevelopmentForProperty($property)
    {
        $developmentRef = (string) $property['developmentRef'];

        if ( $developmentRef && array_key_exists($developmentRef, $this->developments) ) {
            return $this->developments[$developmentRef];
        }

        return false;
    }

    /**
     * Update images to gallery
     *
     * @param $property
     * @param $development
     * @param $post_id
     */
    public function updateMediaGallery($property, $development, $post_id)
    {
        $pgallery = $this->saveMediaGallery($property, $development, $post_id);
        update_post_meta( $post_id, 'propertygallery', $pgallery );
    }

    /**
     * Save images to gallery
     *
     * @param $property
     * @param $development
     * @param $post_id
     * @return array
     */
    public function saveMediaGallery($property, $development, $post_id)
    {
        $media = [];
        $mediaLinks = [];

        $mediaLinks = $this->getPhotos($mediaLinks, $property['media']);
        $mediaLinks = $this->getPhotos($mediaLinks, $development->media);

        if ( $mediaLinks ) {
            foreach ($mediaLinks as $mediaLink) {
                $name = (string) $mediaLink->mediaFriendlyName ? (string) $mediaLink->mediaFriendlyName : 'image';

                $links = [];

                foreach ($mediaLink->link as $link) {
                    $links[ (string) $link->attributes()->type ] = (string) $link;
                }

                if ( array_key_exists('largephoto-link', $links) ) {
                    $imageLink = $links['largephoto-link'];
                } elseif ( array_key_exists('mediumphoto-link', $links) ) {
                    $imageLink = $links['mediumphoto-link'];
                } elseif ( array_key_exists('smallphoto-link', $links) ) {
                    $imageLink = $links['smallphoto-link'];
                } else {
                    $imageLink = array_pop($links);
                }

                array_push(
                    $media,
                    ['pgallery' => $this->mediaHandleUpload($property, $imageLink, $post_id, $name)]
                );
            }
        }

        $thumbnail = $this->propertyModel->getFirstMediaImage($post_id);

        if ( $thumbnail ) {
            update_post_meta( $post_id, '_thumbnail_id', $thumbnail );
        }

        return $media;
    }

    /**
     * Get all images from Property
     *
     * @param array $mediaLinks
     * @param $photos
     * @return array
     */
    public function getPhotos( $mediaLinks = [], $photos )
    {
        if ( $photos && $photos->children() ) {
            foreach ( $photos->children() as $mediagroup) {
                if ($mediagroup->attributes()->type == 'photos') {
                    foreach ($mediagroup->children() as $mediaitem) {
                        array_push($mediaLinks, $mediaitem);
                    }
                }
            }
        }

        return $mediaLinks;
    }

    /**
     * Handle image when uploading
     *
     * @param $property
     * @param $link
     * @param $post_id
     * @param $fileName
     * @param array $overrides
     * @return mixed
     */
    public function mediaHandleUpload($property, $link, $post_id, $fileName, $overrides = ['test_form' => false, 'test_size' => true, 'action' => 'wp_handle_save'] )
    {
        $tempFile = download_url($link);
        parse_str( parse_url($link, PHP_URL_QUERY), $parseLink);

        if ( is_wp_error( $tempFile ) ) {
            array_push($this->errors, sprintf(
                    'Не удалось загрузить изображение по сслыке %s для property (%s) %s. %s',
                    $link,
                    $property['propertyref'],
                    $property['propertyname'],
                    $tempFile->get_error_message()
                )
            );

            return null;
        }

        $image = [
            'name' => $parseLink['img'],
            'type' => mime_content_type($tempFile),
            'tmp_name' => $tempFile,
            'error' => 0,
            'size' => filesize($tempFile),
        ];

        $time = current_time('mysql');

        if ( $post = get_post($post_id) ) {
            if ( substr( $post->post_date, 0, 4 ) > 0 )
                $time = $post->post_date;
        }

        $file = wp_handle_upload($image, $overrides, $time);

        if ( isset($file['error']) ) {
            array_push($this->errors, sprintf(
                    'Не удалось загрузить изображение по сслыке %s для property (%s) %s. %s',
                    $link,
                    $property['propertyref'],
                    $property['propertyname'],
                    $file['error']
                )
            );
            return null;
        }

        $ext  = pathinfo( $image['name'], PATHINFO_EXTENSION );
        $name = wp_basename( $fileName, ".$ext" );

        $url = $file['url'];
        $type = $file['type'];
        $file = $file['file'];
        $title = sanitize_text_field( $name );
        $content = '';
        $excerpt = '';

        if ( 0 === strpos( $type, 'image/' ) && $image_meta = @wp_read_image_metadata( $file ) ) {
            if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
                $title = $image_meta['title'];
            }

            if ( trim( $image_meta['caption'] ) ) {
                $excerpt = $image_meta['caption'];
            }
        }

        // Construct the attachment array
        $attachment = array_merge( array(
            'post_mime_type' => $type,
            'guid' => $url,
            'post_parent' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
        ));

        // This should never be set as it would then overwrite an existing attachment.
        unset( $attachment['ID'] );

        // Save the data
        $id = wp_insert_attachment($attachment, $file, $post_id);
        if ( !is_wp_error($id) ) {
            wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
        }

        return $attachment['guid'];
    }

    /**
     * Delete images when Property is updated
     *
     * @param $post_id
     * @param $propertyFields
     */
    public function removeMediaGallery($post_id, $propertyFields)
    {
        $media = $this->propertyModel->getMediaGalerry($post_id);

        if ($media) {
            foreach ($media as $photo) {

                if ( !wp_delete_attachment($photo->id, true) ) {
                    array_push(
                        $this->errors,
                        sprintf('При обновлении property (%s) %s, не удалось удалить файл (%s) %s',
                            $propertyFields['propertyref'],
                            $propertyFields['propertyname'],
                            $photo->post_title
                        )
                    );
                }
            }
        }
    }
}