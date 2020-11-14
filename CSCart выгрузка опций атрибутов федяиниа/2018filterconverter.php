<?php


/**
 * Алгоритм переноса params в filters
 * - добаялем вспомогательные поля p_value_id p_id в filter_group, filter (в ручную)
 * ALTER TABLE `filter` ADD `p_value_id` VARCHAR(201) NULL AFTER `sort_order`, ADD UNIQUE (`p_value_id`);
 * ALTER TABLE `filter_description` ADD `p_value_id` VARCHAR(201) NULL AFTER `name`, ADD UNIQUE (`p_value_id`);
 * ALTER TABLE `filter_group_description` ADD `p_id` VARCHAR(201) NULL AFTER `mf_tooltip`, ADD UNIQUE (`p_id`);
 * ALTER TABLE `filter_group` ADD `p_id` VARCHAR(201) NULL AFTER `sort_order`, ADD UNIQUE (`p_id`);
 *
 *
 * 001 - сканируем param и переносим в соответсвубющие filter_group + decription
 * 002 - сканируем param_value и переносим в соответсвующие filter + decription
 * 003 - сканируем product_to_param_value и переносим в соответсвующие product_filter
 * 004 - вспомогательные поля можно удаллить а можно и оставить
 * 005 - ШАГ нсмотри модуль МегаФильтр

 * Там вкладка Filters001 в первом уровне меню и во втором в первой вкладке LAyers Filters002
 * Надо ОБЯЗАТЕЛЬНО поставить в обоих вкладках вкладка DEFAULR - инаблдед филтры
 * Возможно включить опцию "Фильтры из катклогов"
 * Filters002 - НАЖАТЬ УСТАНОВИТЬ ВСЕ ДЕФОЛТ! иначе бдудет КЭШироваться на морде фильтры
 * Перповерь на морде и вптор если надо 
 */

$m = new Main();
//шаг 001 выполнен
//$m->parse_old_params();
//
//шаг 002 выполнен
//$m->parse_old_param_value();
//
//шаг 003 выполнен
//$m->parse_old_product_to_param_value();
//
//шаг 004 пишем в плагин список используемых фильтров
$m->update_megafilter_plugin();


class Main
{
    public $dbh;

    function __construct()
    {

        $dbname = 'test';
        $hostname = 'localhost';
        $username = 'test';
        $mysqlpassword = '777limited;';

        try {
            $this->dbh = new PDO("mysql:host=$hostname;dbname=" . $dbname, $username, $mysqlpassword);
            $this->dbh->query("SET NAMES 'utf8'");
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // <== add this line
        } catch (PDOException $e) {
            echo $e->getMessage();
        }


    }

    public function parse_old_params()
    {

        $sql = "SELECT * FROM `param` WHERE is_filter = 1 AND `param_id` not in ('brand', 'kollektsii')";
        $stmt = $this->dbh->query($sql);
        /*
         *     [param_id] => aksess-poker
    [0] => aksess-poker
    [param_group_id] =>
    [1] =>
    [name] => Тип
    [2] => Тип
    [description] =>
    [3] =>
    [prefix] =>
    [4] =>
    [is_filter] => 1
    [5] => 1
    [is_sdesc] => 0
    [6] => 0
    [is_desc] => 0
    [7] => 0
    [is_list] => 1
    [8] => 1
    [is_multiple] => 1
    [9] => 1
    [created_at] => 2013-08-17 05:05:15
    [10] => 2013-08-17 05:05:15
    [updated_at] => 2017-06-23 09:39:54
    [11] => 2017-06-23 09:39:54
    [created_by] => nikolay
    [12] => nikolay
    [updated_by] => admin
    [13] => admin
         */
        while ($row = $stmt->fetch()) {
            $this->save_new_filter_group($row);
        }


    }

    function save_new_filter_group($param)
    {
        echo " {$param['param_id']} ";

        $sql = "INSERT INTO `filter_group` (`filter_group_id`, `sort_order`, `p_id`) VALUES ('', 0, '{$param['param_id']}');";
        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo("ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n");
            }
        }
        // вторая таблдица локализхации
        $id = $this->dbh->lastInsertId();
        if (!$id > 0) return true;

        //
        $sql = "INSERT INTO `filter_group_description` (`filter_group_id`, `language_id`, `name`, `mf_tooltip`, `p_id`) 
VALUES ($id, 1, '{$param['name']}', NULL, '{$param['param_id']}');";
        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo "ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n";
            }
        }

        return true;

    }

    public function parse_old_param_value()
    {

        $sql = "SELECT * FROM `param_value` WHERE is_active = 1 AND `param_id` not in ('brand', 'kollektsii')";
        $stmt = $this->dbh->query($sql);
        /*

Array
(
    [param_value_id] => aksess-poker_hraniteli-kart
    [0] => aksess-poker_hraniteli-kart
    [param_id] => aksess-poker
    [1] => aksess-poker
    [alias] => hraniteli-kart
    [2] => hraniteli-kart
    [value] => хранители карт
    [3] => хранители карт
    [sort_order] => 0
    [4] => 0
    [is_active] => 1
    [5] => 1
    [created_at] => 2013-08-17 05:05:16
    [6] => 2013-08-17 05:05:16
    [updated_at] => 2017-06-23 09:39:54
    [7] => 2017-06-23 09:39:54
    [created_by] => nikolay
    [8] => nikolay
    [updated_by] => admin
    [9] => admin
)
         */
        while ($row = $stmt->fetch()) {
            $this->save_new_filter($row);
        }

    }

    function get_filter_group_id_byNameParam($name_param)
    {
        $sql = "SELECT * FROM `filter_group` WHERE `p_id` = '$name_param'";
        $stmt = $this->dbh->query($sql)->fetch();
        return $stmt['filter_group_id'];
    }

    function get_filter_id_byNameParam($name_param)
    {
        $sql = "SELECT * FROM `filter` WHERE `p_value_id` = '$name_param'";
        $stmt = $this->dbh->query($sql)->fetch();
        return $stmt['filter_id'];
    }

    function save_new_filter($param)
    {
        echo " {$param['param_value_id']}/{$param['param_id']} ";
        $filter_group_id = $this->get_filter_group_id_byNameParam($param['param_id']);

        $sql = "INSERT INTO `filter` (`filter_id`, `filter_group_id`, `sort_order`, `p_value_id`) 
VALUES ('', '{$filter_group_id}', '{$param['sort_order']}', '{$param['param_value_id']}')";
        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo("ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n");
            }
        }
        // вторая таблдица локализхации
        $filter_id = $this->dbh->lastInsertId();
        if (!$filter_id > 0) return true;

        //
        $sql = "INSERT INTO `filter_description` (`filter_id`, `language_id`, `filter_group_id`, `name`, `p_value_id`) VALUES
('$filter_id', 1, '$filter_group_id', '{$param['value']}', '{$param['param_value_id']}');
";
        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo "ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n";
            }
        }

        return true;

    }

    public function parse_old_product_to_param_value()
    {

        $sql = "SELECT * FROM `product_to_param_value` WHERE `param_id` not in ('brand', 'kollektsii')";
        $stmt = $this->dbh->query($sql);

        while ($row = $stmt->fetch()) {
            $this->save_new_product2filter($row);
        }

    }

    /*

Array
(
   [id] => 17026
   [0] => 17026
   [product_id] => 12353
   [1] => 12353
   [param_id] => kolichestvo-fishek-v-nabore
   [2] => kolichestvo-fishek-v-nabore
   [param_value_id] => kolichestvo-fishek-v-nabore_100-fishek
   [3] => kolichestvo-fishek-v-nabore_100-fishek
   [value] =>
   [4] =>
   [created_at] => 0000-00-00 00:00:00
   [5] => 0000-00-00 00:00:00
   [updated_at] => 0000-00-00 00:00:00
   [6] => 0000-00-00 00:00:00
   [created_by] =>
   [7] =>
   [updated_by] =>
   [8] =>
)

        */
    function save_new_product2filter($param)
    {
        echo " {$param['param_value_id']}/{$param['param_id']} ";
        $filter_group_id = $this->get_filter_id_byNameParam($param['param_value_id']);

        $sql = "INSERT INTO `product_filter` (`product_id`, `filter_id`) VALUES
({$param['product_id']}, $filter_group_id)";

        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo("ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n");
            }
        }


        return true;

    }

    function update_megafilter_plugin()
    {
        $sql = "SELECT * FROM `mfilter_settings`";
        $set = $this->dbh->query($sql)->fetch();
        $m_filter_json = json_decode(trim($set['settings']), true);

        print_r($m_filter_json);


        $m_filter_json['filters'] =
            [
                'based_on_category' => '0',
                'default' =>
                    [
                        'enabled' => '1',
                        'type' => 'checkbox',
                        'display_live_filter' => '',
                        'collapsed' => '1',
                        'display_list_of_items' => '',
                        'sort_order_values' => ''
                    ],
            ];


        $sql = "SELECT * FROM `filter`";
        $stmt = $this->dbh->query($sql);
        while ($row = $stmt->fetch()) {
            $m_filter_json['filters'][$row['filter_id']] =
                [
                    'enabled' => '1',
                    'type' => 'checkbox',
                    'display_live_filter' => '',
                    'collapsed' => 0,
                    'display_list_of_items' => '',
                    'sort_order_values' => '',
                    'sort_order' => '',
                ];
        }

        print_r($m_filter_json);
        echo $m_filter_json = json_encode($m_filter_json);
        echo $sql = "UPDATE `mfilter_settings` SET `settings` = '$m_filter_json' WHERE `idx` = {$set['idx']};";
        try {
            $this->dbh->query($sql);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo("ТАКАЯ ЗАПИСЬ ЕСТЬ В БД\n");
            }
        }

    }


}