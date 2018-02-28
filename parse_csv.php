<?php

class ParseCSV {
    
    private $CSV_path;
    private $data_array;
    private $DB;
    private $charset;
    
    public function __construct( $CSV_path = '', $charset = 'UTF-8') {
        
        $this->CSV_path = $CSV_path;
        
        $this->data_array = []; 
        
        $this->charset = $charset;
        
        $this->DB = new mysqli('localhost', 'root', '', 'translate');
        
            if( $this->DB->connect_errno ) {
                die('Error connect to DB');
            } else {
                $this->DB->set_charset($this->charset);
            }
    }
    
    public function __destruct() {
        $this->DB->close();
    }
    
    /**
	 * Перетворення CSV файлу в масив
	 *
	 * Відркриває файл CSV та конвертує його у масив.
	 * Перед поверненням масиву, форматує поле "назва міста", перетворюючи його
         * з вигляду "НАЗВА" у "Назва"
	 *
	 * @return	Array при успішному читанні і перетворенні CSV файлу
         * @return	FALSE якщо не вдається відкрити файл CSV
	 */
    private function CSVtoArray() {
        
        if( ($resource = fopen($this->CSV_path,'r')) !== FALSE ) {
            
            while( ($row = fgetcsv($resource, 0, ';')) !== FALSE ) {
                
                if( strlen($row[3]) > 1 ) {
                    
                    $city_name = mb_strtolower($row[2], 'CP1251');
                    $city_name[0] = mb_strtoupper($city_name[0], 'CP1251');
                    $row[2] = $city_name;
                    
                    $this->data_array[] = $row;
                } else {
                    continue;
                } 
            }
            
        return $this->data_array;
        } else {
            return FALSE;
        }
    }
    
     /**
	 * Перевірка на відсутність назви області, району або міста
	 *
	 * Перевіряється лише область район та назва міста
	 * Перевірка на відсутність координат не відбувається        
	 *
	 * @return	BOOL TRUE - якщо всі назви не порожні, FALSE - якщо хоча б одна з назв порожня       
	 */
    private function EmptyArrayItem( $array_row = [] ) {
        
        if( ! empty($array_row[0]) && ! empty($array_row[1]) && ! empty($array_row[2]) ) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
        /**
	 * Запис масиву даних у базу даних
	 *
	 * Отримуємо масив з даними з методу CSVtoArray
	 * Записуємо почергово дані про місто у базу даних         
	 *
	 * @return	BOOL TRUE - якщо метод коректно завершив роботу 
         * та додав записи у базу даних, FALSE - якщо під час виконання виникла помилка        
	 */
    public function InsertToDB() {
        
        if( ($this->data_array = $this->CSVtoArray()) !== FALSE ) {
            
            $region_id = 0;
            $district_id = 0;
           
            foreach($this->data_array as $item) {
             
                if( ! $this->EmptyArrayItem($item) ) {
                    continue;
                } 
               
            //Обробляємо дані області    
                $query = $this->DB->query("SELECT id FROM region WHERE name_ua = '".$item[0]."'");
                
                    if( $query->num_rows ) {
                        
                        $query = $query->fetch_array();
                        $region_id = $query[0];
                    } else {
                        
                        $this->DB->query("INSERT INTO region(name_ua) VALUES('".$item[0]."')");
                        
                            if( $this->DB->affected_rows ) {
                                $region_id = $this->DB->insert_id;
                            } else {
                                return FALSE;
                            }
                    }
                    
            //Обробляємо дані району
                $query = $this->DB->query("SELECT id FROM district WHERE "
                        . "name_ua = '".$item[1]."' AND region_parent = '".$region_id."'");
                
                    if( $query->num_rows ) {
                        
                        $query = $query->fetch_array();
                        $district_id = $query[0];
                    } else {
                        
                        $this->DB->query("INSERT INTO district(region_parent,name_ua) "
                                . "VALUES('".$region_id."','".$item[1]."')");
                        
                            if( $this->DB->affected_rows ) {
                                $district_id = $this->DB->insert_id;
                            } else {
                                return FALSE;
                            }
                    }
            //Обробляємо дані про населений пункт
                    $query = $this->DB->query("SELECT id FROM city WHERE"
                            . " name_ua = '".$item[2]."' AND district_parent = '".$district_id."'");
                
                    if( $query->num_rows ) {
                        continue;
                    } else {
                        
                        $this->DB->query("INSERT INTO city(district_parent,name_ua,position1,position2) "
                                . "VALUES('".$district_id."','".$item[2]."','".$item[3]."','".$item[4]."')");
                        
                            if( ! $this->DB->affected_rows ) {
                                return FALSE;
                            }
                    }
                   
            }
        } else {
            return FALSE;
        }
    }
}

$CSV = new ParseCSV('ua-list.csv', 'CP1251');


var_dump($CSV->InsertToDB());















