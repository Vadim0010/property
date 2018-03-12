<?php

/*
Plugin Name: Dreamvilla Import Data
Description: Получить данные из xml файла при импорте через ссылку
*/

require_once (plugin_dir_path (__FILE__) . 'src/Models/Property.php');
require_once (plugin_dir_path (__FILE__) . 'src/Controller/DreamvillaImportData.php');

new DreamvillaImportData();