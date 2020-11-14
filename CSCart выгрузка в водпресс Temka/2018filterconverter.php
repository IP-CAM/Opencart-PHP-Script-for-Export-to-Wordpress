<?php
header('Content-Type: text/html; charset=utf-8');
ini_set("safe_mode", 0);
ini_set("error_reporting", E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
/*

/**
 *
 * ПЕРЕНОС ТОВАРОВ ИЗ cs-cart  в wordpress
 * ПЕЕРЕНОС КЛИЕНТОВ  ИЗ cs-cart  в wordpress
 * ЯЗЫК ТОКА РУССКИЙ
 * анализируем структуру базы cs cart - где хранятся товары описание цены и атрибуты/опции
 * cscart_product_prices
 * cscart_product_descriptions
 * cscart_products
 * cscart_products_categories * категории
 *
 * cscart_product_options_descriptions
 * cscart_product_options - 932 опции
 * cscart_product_option_variants - с ценами
 * cscart_product_option_variants_descriptions - название на языках
 *
 * cscart_product_popularity - может быть
 * cscart_images_links
 * cscart_images
 * cscart_users - пароли после переноса в вордпресс будут восстанавливать через почту
 * cscart_user_profiles
 *
 *
 * 0. Для импорта клиентов. См плагин на Мамашарит  Инструменты / Import User
 * Но нет пока куда их импортировать для Вукомерс
 * Списки CSV клиентов и подписчиков уже выгрузил см. в папке (про рассылку заикнемся в конце)
 *
 * 1. Выгружаем файл типа csv из CS-CART (с запятыми и двойными кавычками)
 * 2. Грузить в ВП будем через стандартный скрипт ВП / Инструменты / Импорт / Импорт товаров Woocommerce
 * http://wp.temka.zt.ua/wp-admin/edit.php?post_type=product&page=product_importer
 * - Пример файла для импорта в ВП взял тут https://docs.woocommerce.com/document/product-csv-importer-exporter/dummy-data/
 * - Модифицированный CSV xlsx см в папке ЗАГРУЗКА
 * - Примечание: при импорте на сервере заказчика сбоит CURL PHP (не грузит картинки), придется базу выгружать у нас на сервере
 * - В процессе тестов - модифицировал его через Excell и обратно конвертировал в CSV через
 * https://www.zamzar.com/ т.к. в Экселе свой формат CSV который
 * - Вариации РАЗМЕРЫ (те что влияют на цены) загрузились хорошо, а атрибуты ЦВЕТ ПОЛ без влкченного флажка "Использовать в варианциях"
 * Придется писать скрипт ПОСЛЕ ОБЩЕЙ ВЫГРУЗКИ, который будет сканировать товары в CS-CART и включать соответсвующие вариации в ВП
 * Смотри в ВП таблицу wp_postmeta поле metakey = _product_attributes
 * в нем хранится массив
 * a:3:{s:9:"pa_razmer";a:6:{s:4:"name";s:9:"pa_razmer";s:5:"value";s:0:"";s:8:"position";i:0;s:10:"is_visible";
 * i:1;s:12:"is_variation";i:1;s:11:"is_taxonomy";i:1;}s:8:"pa_tsvet";a:6:{s:4:"name";s:8:"pa_tsvet";s:5:"value";
 * s:0:"";s:8:"position";i:1;s:10:"is_visible";i:1;s:12:"is_variation";i:0;s:11:"is_taxonomy";i:1;}s:6:"pa_pol";
 * a:6:{s:4:"name";s:6:"pa_pol";s:5:"value";s:0:"";s:8:"position";i:2;s:10:"is_visible";i:1;s:12:"is_variation";
 * i:1;s:11:"is_taxonomy";i:1;}}
 * - ансералайзим через любой онлайн сервис и видим
 * [position] => 0
 * [is_visible] => 1
 * [is_variation] => 0
 * Нас интересует переключатель is_variation = 1, его надо включиьт для соответсвующих атрибутов ЦВЕТ ПОЛ
 * Примечание: Можно менять последовательность отображения на морде сайта
 * Примечание: Можно установить значение по умолчанию, любого атрибута, когда страница загружается
 * ниде пример, возможно не пригодится
 *
 *
 * 4486 только пол http://temka.zt.ua/17.-shapki-osen/219.html
 * 4495 нет никаких параметров тока цена http://temka.zt.ua/17.-shapki-osen/229.html
 *
 */

$m = new Main();

$m->template_csv = 0;
$m->generatorCSVProducts();
//echo "\r\n\r\n\r\n";
//$m->template_csv = 0;
//$m->generatorCSVProducts();
//print_r($m->getAllOptinsByIDProduc('4999'));


class Main
{
    public $dbh;

    public $template_csv;

    function __construct()
    {

        $dbname = 'temka';
        $hostname = 'localhost';
        $username = 'temka';
        $mysqlpassword = '4A0jhskP';

        try {
            $this->dbh = new PDO("mysql:host=$hostname;dbname=" . $dbname . ";charset=utf8", $username, $mysqlpassword,
                [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
            );
            $this->dbh->query("SET NAMES 'utf8'");
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // <== add this line
        } catch (PDOException $e) {
            echo $e->getMessage();
        }


    }


    public function generatorCSVProducts()
    {

//p.status = 'A'
        $sql = "SELECT p.product_id, p.status, d.product, d.full_description, price.price 
FROM 
`cscart_products` p LEFT JOIN cscart_product_descriptions d 
ON p.product_id=d.product_id 
LEFT JOIN cscart_product_prices price ON p.product_id=price.product_id
WHERE 1";
//WHERE p.product_id in ( 4999) AND  AND p.status = 'A'";
//WHERE p.product_id=449 AND p.status = 'A'";
        //WHERE p.status = 'A'";

        $stmt = $this->dbh->query($sql);
        $result_init = [
            '$attr1default' => '',
            '$attr1glob' => '',
            '$attr1name' => '',
            '$attr1val' => '',
            '$attr1visibl' => '',
            '$attr2default' => '',
            '$attr2glob' => '',
            '$attr2name' => '',
            '$attr2val' => '',
            '$attr2visibl' => '',
            '$attr3default' => '',
            '$attr3glob' => '',
            '$attr3name' => '',
            '$attr3val' => '',
            '$attr3visibl' => '',
            '$categories' => '',
            '$desc' => '',
            '$images' => '',
            '$name' => '',
            '$parent' => '',
            '$price' => '0',
            '$sku' => '',
            '$type' => '',
        ];


        $template_head = '<table border=1><tr><td>Type \'$type\',<td>   SKU \'$sku\',<td>   Name \'$name\',<td>	  Published \'1\',<td>   Is featured? \'0\',<td>   
Visibility in catalog \'visible\',<td>  	Short description \'\',<td>   Description \'$desc\',<td>   
Date sale price starts \'\',<td>	  Date sale price ends \'\',<td>   Tax class \'taxable\',<td>  	In stock? \'1\',<td>  	
Stock	\'\',<td>   Backorders allowed?	\'0\',<td>   Sold individually?	\'0\',<td>   Weight (kg) \'\',<td>	Length (cm)	\'\',<td> Width (cm) \'\',<td>	
Height (cm)	\'\',<td> Allow customer reviews? \'1\',<td>	Purchase note \'\',<td> Sale price	\'\',<td> Regular price	\'$price\',<td> 
Categories \'$categories\',<td> Tags \'\',<td> 	Shipping class	\'\',<td> Images \'$images\',<td> Download limit \'\',<td> 	
Download expiry days \'\',<td> 	Parent \'$parent\',<td> Grouped products \'\',<td> Upsells \'\',<td> Cross-sells \'\',<td> 
External URL \'\',<td> Button text \'\',<td> Download 1 name \'\',<td> Download 1 URL \'\',<td> 
Attribute 1 name \'$attr1name\',<td> Attribute 1 value(s) \'$attr1val\',<td> Attribute 1 visible \'$attr1visibl\',<td> Attribute 1 global \'$attr1glob\',<td> Attribute 1 default \'$attr1default\',<td> 
Attribute 2 name \'$attr2name\',<td> Attribute 2 value(s) \'$attr2val\',<td> Attribute 2 visible \'$attr2visibl\',<td> Attribute 2 global \'$attr2glob\',<td> Attribute 2 default \'$attr2default\',<td> 
Attribute 3 name \'$attr3name\',<td> Attribute 3 value(s) \'$attr3val\',<td> Attribute 3 visible \'$attr3visibl\',<td> Attribute 3 global \'$attr3glob\',<td> Attribute 3 default \'$attr3default\' 
';
        $template = '<tr><td>\'$type\',<td>\'$sku\',<td>\'$name\',<td>\'1\',<td>\'0\',<td>   
\'visible\',<td>\'\',<td>\'$desc\',<td>   
\'\',<td>\'\',<td>\'taxable\',<td>\'1\',<td>  	
\'\',<td>\'0\',<td>\'0\',<td>\'\',<td>\'\',<td>\'\',<td>	
\'\',<td>\'1\',<td>\'\',<td>\'\',<td>\'$price\',<td> 
\'$categories\',<td>\'\',<td>\'\',<td>\'$images\',<td>\'\',<td> 	
\'\',<td>\'$parent\',<td>\'\',<td>\'\',<td>\'\',<td> 
\'\',<td>\'\',<td>\'\',<td>\'\',<td> 
\'$attr1name\',<td>\'$attr1val\',<td>\'$attr1visibl\',<td>\'$attr1glob\',<td>\'$attr1default\',<td> 
\'$attr2name\',<td>\'$attr2val\',<td>\'$attr2visibl\',<td>\'$attr2glob\',<td>\'$attr2default\',<td> 
\'$attr3name\',<td>\'$attr3val\',<td>\'$attr3visibl\',<td>\'$attr3glob\',<td>\'$attr3default\'';
        $template = str_replace("\r", ' ', $template);
        $template = str_replace("\n", ' ', $template) . "\r\n";

        if ($this->template_csv) {
            $template_head = 'Type,SKU,Name,Published,Is featured?,Visibility in catalog,Short description,Description,Date sale price starts,Date sale price ends,Tax class,In stock?,Stock,Backorders allowed?,Sold individually?,Weight (kg),Length (cm),Width (cm),Height (cm),Allow customer reviews?,Purchase note,Sale price,Regular price,Categories,Tags,Shipping class,Images,Download limit,Download expiry days,Parent,Grouped products,Upsells,Cross-sells,External URL,Button text,Download 1 name,Download 1 URL,Attribute 1 name,Attribute 1 value(s),Attribute 1 visible,Attribute 1 global,Attribute 1 default,Attribute 2 name,Attribute 2 value(s),Attribute 2 visible,Attribute 2 global,Attribute 2 default,Attribute 3 name,Attribute 3 value(s),Attribute 3 visible,Attribute 3 global,Attribute 3 default';
            $template = '"$type","$sku","$name","1","0","visible","","$desc","","","taxable","1","999","0","0","","","","","1","","","$price","$categories","","","$images","","","$parent","","","","","","","","$attr1name","$attr1val","$attr1visibl","$attr1glob","$attr1default","$attr2name","$attr2val","$attr2visibl","$attr2glob","$attr2default","$attr3name","$attr3val","$attr3visibl","$attr3glob","$attr3default"';
            $template = str_replace("\r", ' ', $template);
            $template = str_replace("\n", ' ', $template) . "\r\n";
        }
        echo $template_head . "\r\n";
        while ($row = $stmt->fetch()) {

            $i = 0;
            $result = $result_init;
            $options = $this->getAllOptinsByIDProduc($row['product_id']);
            //
            $result['$sku'] = $row['product_id'];
            $result['$name'] = str_replace('"', '""', $row['product']);
            $result['$desc'] = str_replace('"', '""', str_replace("\r", " ", (str_replace("\n", " ", $row['full_description']))));
            $result['$desc'] = trim(explode('**', $result['$desc'])[0]);

            $result['$price'] = $row['price']; // в опциях своя
            $result['$categories'] = str_replace('"', '""', $this->getAllStrCategoryByIDProduc($row['product_id']));
            $result['$images'] = $this->getStrImagesLiastByIDProduct($row['product_id']);

            if (!sizeof($options)) {
                //SIMPLE НЕТ ВООБЩЕ ВАРИАЦИЙ
                $result['$type'] = 'simple';
                // ОСНОВНОй товар для НЕ ВАРИАТИВНЫХ
                echo strtr($template, $result);

                //
            } else {

                // генерируем VARIABLE первую строку
                // генерируем VARIABLE первую строку
                // генерируем VARIABLE первую строку
                // генерируем VARIABLE первую строку
                // генерируем VARIABLE первую строку
                $result['$type'] = 'variable';

                // в первой строке также надо упомянуть и размеры если есть
                if (isset($options['размер'])) {

                    $i++;
                    $all_values = '';
                    $_flag_rostovka_ye = 0;
                    foreach ($options['размер'] as $size) {
                        //
                        if (preg_match('/ростов/ui', $size['val'])) {
                            $size['val'] = 'ростовка';
                            $_flag_rostovka_ye = 1;
                        }
                        $all_values .= $size['val'] . ',';
                    }
                    if (empty($_flag_rostovka_ye) AND sizeof($options['размер']) > 1) {
                        $all_values .= 'ростовка';
                    }

                    //
                    $result['$attr' . $i . 'default'] = '1';
                    $result['$attr' . $i . 'glob'] = '1';
                    $result['$attr' . $i . 'name'] = 'размер';
                    $result['$attr' . $i . 'val'] = trim($all_values, ',');
                    $result['$attr' . $i . 'visibl'] = '1';

                }/// <-РАЗМЕР


                if (isset ($options['расцветка'])) {
                    $i++;
                    $all_values = '';
                    foreach ($options['расцветка'] as $size) {
                        $all_values .= $size['val'] . ',';
                    }
                    //
                    $result['$attr' . $i . 'default'] = '1';
                    $result['$attr' . $i . 'glob'] = '1';
                    $result['$attr' . $i . 'name'] = 'расцветка';
                    $result['$attr' . $i . 'val'] = trim($all_values, ',');
                    $result['$attr' . $i . 'visibl'] = '1';

                } /// <-РАСЦВЕТКА

                if (isset ($options['пол'])) {
                    $i++;
                    $all_values = '';
                    foreach ($options['пол'] as $size) {
                        // опечатка раное на разное
                        if (mb_strpos($size['val'], 'раное') === 0) $size['val'] = 'разное';
                        //
                        $all_values .= $size['val'] . ',';
                    }
                    //
                    $result['$attr' . $i . 'default'] = '1';
                    $result['$attr' . $i . 'glob'] = '1';
                    $result['$attr' . $i . 'name'] = 'пол';
                    $result['$attr' . $i . 'val'] = trim($all_values, ',');
                    $result['$attr' . $i . 'visibl'] = '1';
                } // <-ПОЛ


                // ОСНОВНОй товар для вариативгных
                // далее сомтри вариации выводятся
                echo strtr($template, $result);


                //// может быть опция БЕЗ РАЗМЕРОВ только или пол или расцветка
                /// РАЗМЕР тлько влияет на цены, по этому строки нужны только если есть размер остальные
                if (isset($options['размер'])) {
                    foreach ($options['размер'] as $size) {
                        // обнуляем
                        $result = $result_init;
                        $result['$type'] = 'variation';
                        $result['$parent'] = $row['product_id'];
                        $result['$price'] = $size['price']; // в опциях своя
                        $result['$attr1glob'] = '1';
                        $result['$attr1name'] = 'размер';
                        if (preg_match('/ростов/ui', $size['val'])) {
                            $size['val'] = 'ростовка';
                        }
                        $result['$attr1val'] = $size['val'];
                        $result['$attr1visibl'] = '1';
                        echo strtr($template, $result);
                    }

                    // ростовки нет - добаыляем, цену любую кстати
                    if (empty($_flag_rostovka_ye) AND sizeof($options['размер']) > 1) {
                        // обнуляем
                        $result = $result_init;
                        $result['$type'] = 'variation';
                        $result['$parent'] = $row['product_id'];
                        $result['$price'] = 999; // в опциях своя
                        $result['$attr1glob'] = '1';
                        $result['$attr1name'] = 'размер';
                        $result['$attr1val'] = 'ростовка';
                        $result['$attr1visibl'] = '1';
                        echo strtr($template, $result);
                    }


                    //  В ЭТОМ ТОВАРЕ РАЗМРОВ НЕТ ..... ?
                } else {
                    // к сожалению Woocommerce не принимапет вариативный товар с пустыми вариациями
                    // добавляем сюда вариации из первого атрибута и цену базовую товара
                    // опредеяем какая опция в отсуствии размера будет влиять на цену товара
                    if (isset($options['пол'])) {
                        $opt = $options['пол'];
                        $opt_name = 'пол';
                    } else {
                        $opt = $options['расцветка'];
                        $opt_name = 'расцветка';
                    }

                    if (!isset($opt)) {
                        print_r($options);
                        die("!!!!!!!!!!!!!");
                    }

                    foreach ($opt as $size) {
                        // обнуляем
                        $result = $result_init;
                        $result['$type'] = 'variation';
                        $result['$parent'] = $row['product_id'];
                        $result['$price'] = $row['price']; // ЗДЕСЬ ЦЕНА ТОВАРА ГЛОБАЛЬНАЯ
                        $result['$attr1glob'] = '1';
                        $result['$attr1name'] = $opt_name;
                        // опечатка раное на разное
                        if (mb_strpos($size['val'], 'раное') === 0) $size['val'] = 'разное';
                        // размео типа  хххх ростовка шт. - заменяем просто на ростовка
                        if (preg_match('/ростов/ui', $size['val'])) {
                            $size['val'] = 'ростовка';
                        }
                        //
                        $result['$attr1val'] = $size['val'];
                        $result['$attr1visibl'] = '1';
                        echo strtr($template, $result);
                    }

                }
            }

            // ИМАДЖЕС
            //echo $this->getStrImagesLiastByIDProduct($row['product_id']);
            //echo "\r\n\r\n";
        }
        if (!$this->template_csv) echo "</table>";
    }


    /**
     * Схалтурим! Дерево категорий данного проекта в одномерном массиве
     * Загоним его в массив ассоциативный
     */
    private function getCategoryNames2Array()
    {
        $sql = "SELECT * FROM `cscart_category_descriptions` ";
        $stmt = $this->dbh->query($sql);
        while ($row = $stmt->fetch()) {
            $result[$row['category_id']] = $row['category'];
        }
        return $result;
    }

    // на выходе стринг со списком категорий данного товара
    public function getAllStrCategoryByIDProduc($id)
    {
        $categories = $this->getCategoryNames2Array();

        // сначала мэйн категория потом сторостепенные
        $result = '';
        $sql = "SELECT * 
FROM  `cscart_products_categories` 
WHERE `product_id`= {$id}
ORDER BY  `cscart_products_categories`.`link_type` DESC ";
        $stmt = $this->dbh->query($sql);
        //
        while ($row = $stmt->fetch()) {
            // запятую выкидываем т.к. в спике категорий на выгрухке несколько категорий разделяются запятой
            $result .= str_replace(',', '/', $categories[$row['category_id']]) . ',';
        }
        return trim($result, ',');
    }

    // на выходе ассоциативный массив опций
    public function getAllOptinsByIDProduc($id)
    {

        //  cscart_product_options	Обзор Обзор	 Структура Структура	Поиск Поиск	 Вставить Вставить	Очистить Очистить	 Удалить Удалить	904	MyISAM	utf8_general_ci	67.3 КБ	-
        //	cscart_product_options_descriptions	Обзор Обзор	 Структура Структура	Поиск Поиск	 Вставить Вставить	Очистить Очистить	 Удалить Удалить	904	MyISAM	utf8_general_ci	65.5 КБ	-
        //	cscart_product_option_variants	Обзор Обзор	 Структура Структура	Поиск Поиск	 Вставить Вставить	Очистить Очистить	 Удалить Удалить	3,543	MyISAM	utf8_general_ci	334.2 КБ	-
        //	cscart_product_option_variants_descriptions
        $sql = "SELECT * 
FROM  `cscart_product_options` op
RIGHT JOIN  `cscart_product_option_variants` var ON op.option_id = var.option_id
RIGHT JOIN  `cscart_product_options_descriptions` de ON op.option_id = de.option_id
RIGHT JOIN  `cscart_product_option_variants_descriptions` vd ON vd.variant_id = var.variant_id
WHERE  `product_id` ={$id}
ORDER BY  `var`.`position` ASC ";

        $stmt = $this->dbh->query($sql);
        $flag_sex = FALSE;
        $result = [];
        //
        while ($row = $stmt->fetch()) {
            // определяем тип опции
            // размер
            if (preg_match('/размер/iu', $row['option_name'])) {
                $result['размер'][] = [
                    'val' => $row['variant_name'],
                    'price' => $row['modifier'],
                ];
            }
            //
            if (preg_match('/цвет/iu', $row['option_name'])) {
                $result['расцветка'][] = [
                    'val' => $row['variant_name'],
                    'price' => 0,
                ];
            }
            //
            if (preg_match('/мальчик|девочка/i', $row['variant_name'])) {
                $flag_sex = TRUE;
            }
        }

        if ($flag_sex) {
            $result['пол'] = [
                0 => [
                    'val' => 'раное',
                    'price' => 0,
                ],
                1 => [
                    'val' => 'мальчик',
                    'price' => 0,
                ],
                2 => [
                    'val' => 'девочка',
                    'price' => 0,
                ],
            ];
        }

        return $result;

    }



    // список адресов картинок
    // абсолютных
    // по ID товара через запятую
    public function getStrImagesLiastByIDProduct($id)
    {
        $result = '';
        //object_id 4999
        //

        $sql = "SELECT * FROM  `cscart_images` img  RIGHT JOIN `cscart_images_links` link ON link.detailed_id=img.image_id
WHERE  `object_id` = '{$id}'
ORDER BY `type` DESC ";

        // Maximum number of files, stored in directory. You may change this parameter straight after a store was installed. And you must not change it when the store has been populated with products already.
        define('MAX_FILES_IN_DIR', 1000);
        //
        $stmt = $this->dbh->query($sql);
        while ($row = $stmt->fetch()) {
            $dir = '/' . floor($row[0] / MAX_FILES_IN_DIR) . '/';
            $result .= 'http://old.temka.zt.ua/images/detailed' . $dir . $row['image_path'] . ',';
        }
        return $result;
    }


}