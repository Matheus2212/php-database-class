<?php

/**
 * PHP DB Class
 * 2021-01-07 -> Class created
 * 2021-03-12 -> Added rel="canonical|prev|next" to <a> pagination tags
 * 2021-03-12 -> Added a lot of comments
 * 2021-06-05 -> Added transformWith private method for insert and update methods using the $additional array. Added formatMonney public method to work with monetary values. Changed getPageNow to getCurrentPage. Added search method.
 * 2021-08-17 -> Made a few improvements within base core functions
 * 2021-11-16 -> Fixed Fetch method when same SQL is called more than once
 * 2022-01-22 -> Fixed Fetch method when same SQL is called in a simple way. Also added a way to RETRIEVE data if same SQL is sent again
 * 2022-03-30 -> Made some refactor in try/catch methods. Changed explanations to methods
 * 2022-04-04 -> Turned DB Objet into non-static object. This way is much more easier to run multiple queries while fetching more data
 */

class db
{
    /** This stores the current $connectionName */
    protected static $connectionName = null;

    /** This array stores all $connections added so far on execution */
    protected static $connections = array();

    /** This will receive the last inserted row $id */
    protected static $id = null;

    /** This is an array with the objects keys. */
    protected static $object = array();

    /** It is the $pagedObject. It is set by pagedQuery method */
    protected static $pageObject = null;

    /** It will receive a $friendURL instance to work with friendly URLs */
    protected static $friendlyURL = false;

    /** It will receive the time() when the class has first executed anything */
    private static $dbInit = null;

    /** This is the default class $language */
    protected static $language = "en";

    /** This is the max number of words given a list of words on search method */
    protected static $wordLimit = 25;

    /** These are the words that pagination may have */
    protected static $defaultPaginationWords = array(
        "en" => array(
            "class" => "pagination",
            "url" => "page",
            "prev" => "< Prev",
            "next" => "Next >"
        ),
        "pt-br" => array(
            "class" => "pagination",
            "url" => "pagina",
            "prev" => "< Anterior",
            "next" => "Próximo >"
        )
    );

    /**
     * @return object $instance Returns the database instance
     */
    private static function getInstance($key = false)
    {
        if (self::$dbInit == null) {
            self::$dbInit = microtime();
        }
        if ($key && isset(self::$object[$key])) {
            return self::$object[$key]->getInstance();
        }
        if (is_null(self::$connectionName)) {
            global $DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME;
            $connectionName = "default";
            $connectionCredentials = array(
                "HOST" => $DB_HOST,
                "USER" => $DB_USER,
                "PASSWORD" => $DB_PASSWORD,
                "NAME" => $DB_NAME
            );
            self::addConnection($connectionName, $connectionCredentials);
            self::useConnection($connectionName);
        }
        try {
            $instance = new PDO('mysql:host=' . self::$connections[self::$connectionName]['HOST'] . ";dbname=" . self::$connections[self::$connectionName]['NAME'] . ";", self::$connections[self::$connectionName]['USER'], self::$connections[self::$connectionName]['PASSWORD']);
            if ($instance) {
                $instance->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                self::updateTotalRequests();
                return $instance;
            } else {
                exit("There's been an error within the database");
                return false;
            }
        } catch (Error $e) {
            throw "Unable to connect to database: " . $e;
            exit();
        }
    }

    /**
     * @param string $connectionName This is the connection name. Useful when needed to use more than one connection in application
     * @param array $connectionCredentials Array containing all connection details
     * @return object Return class itself
     */
    public static function addConnection($connectionName, $connectionCredentials)
    {
        if (!array_key_exists($connectionName, self::$connections)) {
            self::$connections[$connectionName] = $connectionCredentials;
            return new static(new stdClass);
        }
    }

    /**
     * @param $connectionName Defines which connection will use
     * @return object|bool Return class itself or false
     */
    public static function useConnection($connectionName)
    {
        if (array_key_exists($connectionName, self::$connections)) {
            self::$connectionName = $connectionName;
            return new static(new stdClass);
        }
        return false;
    }

    /**
     * @return string Return current used connection
     */
    public static function getConnectionName()
    {
        return self::$connectionName;
    }

    /**
     * @void This will update the total amount of requests your application made
     */
    private static function updateTotalRequests()
    {
        if (!isset(self::$connections[self::$connectionName]['totalRequests'])) {
            self::$connections[self::$connectionName]['totalRequests'] = 0;
        }
        self::$connections[self::$connectionName]['totalRequests']++;
    }

    /**
     * @return int $totalRequests Returns total amount of requests made
     */
    public static function getTotalRequests()
    {
        return (isset(self::$connections[self::$connectionName]['totalRequests']) ? self::$connections[self::$connectionName]['totalRequests'] : 0);
    }

    /**
     * @void Echos an HTML comment in page to see time ellapsed to do everything
     */
    public static function performance()
    {
        echo "<!-- Time ellapsed: " . ((int) microtime() - (int) self::$dbInit) . " -->";
    }

    /**
     * @param string|object Source to fetch data from database
     * @param bool Defines if you'll get only one row, or if it's a collection
     * @return object Returns DB Object instance
     */
    private static function encapsulate($mixed, $simple = false)
    {
        if (is_string($mixed)) {
            $key = md5($mixed);
            if (!array_key_exists($key, self::$object)) {
                $instance = new dbObject(self::getInstance()->query($mixed), array("key" => $key, "simple" => $simple));
                self::$object[$key] = $instance;
            }
            if (self::$object[$key] == "unsetted") {
                unset(self::$object[$key]);
                return false;
            }
            return self::$object[$key];
        }
        if ($mixed instanceof dbObject) {
            return $mixed;
        }
        if ($mixed instanceof PDOStatement) {
            self::updateTotalRequests();
            return new dbObject($mixed);
        }
    }

    /**
     * @param string|object Data source to fetch data from
     * @param bool Defines if it's only one row or a collection
     * @return array Returns single row fetched
     */
    public static function fetch($mixed, $simple = false)
    {
        if (is_string($mixed)) {
            if ($simple) {
                if (!preg_match("/limit/", $mixed)) {
                    $mixed .= " LIMIT 1";
                }
            }
            $mixed = self::encapsulate($mixed, $simple);
            return is_bool($mixed) ? $mixed : $mixed->getData();
        }
        if ($mixed instanceof dbObject) {
            return $mixed->getData();
        }
        if ($mixed instanceof PDOStatement) {
            return $mixed->fetch(PDO::FETCH_ASSOC);
        }
    }

    /**
     * @param string|object Data source to fetch all data
     * @return array Returns an array with all rows fetched
     */
    public static function fetchAll($mixed)
    {
        $mixed = self::encapsulate($mixed);
        if ($mixed instanceof dbObject) {
            return $mixed ? $mixed->getdata(1) : $mixed;
        }
        if ($mixed instanceof PDOStatement) {
            return $mixed->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * @param object DB Object to be unsetted from the class
     */
    public static function unsetObject($object)
    {
        self::$object[$object->extra['key']] = "unsetted";
        unset(self::$object[$object->extra['key']]);
    }

    /**
     * @param array|object|string Data source to count rows
     * @param int Returns total rows number
     */
    public static function count($mixed)
    {
        if ($mixed == null) {
            return 0;
        } else {
            if (is_array($mixed)) {
                return 1;
            } else {
                return self::encapsulate($mixed)->extra['totalEntries'];
            }
        }
    }

    /**
     * @param string|bool|object Data source fetched to see if it's empty
     * @return bool Returns the inverse of the bool generated
     */
    public static function empty($query)
    {
        if (is_bool($query)) {
            return !$query;
        }
        if (is_string($query)) {
            $query = self::query($query);
            if (is_bool($query)) {
                return !$query;
            } else {
                return ($query->getInstance()->rowCount() == 0);
            }
        }
        if ($query instanceof dbObject) {
            return ($query->getInstance()->rowCount() == 0);
        }
    }

    /**
     * @param string|object Data source to pull data from
     * @return object Returns DB instance
     */
    public static function query($mixed)
    {
        return self::encapsulate($mixed);
    }

    /**
     * @param string|object Quey string or object to pull data from
     * @param int Number of rows to get for each page
     * @param int Page number 
     * @return object Returns the DB Object instance with pagination setted
     */
    public static function pagedQuery($mixed, $limit, $page = false, $words = false)
    {
        if (is_string($mixed)) {
            $instance = self::query($mixed);
            $instance->extra['limit'] = $limit;
            self::setPaginationWords($instance, $words);
            if ($page == false) {
                $page = self::getCurrentPage();
            }
            $newObject = self::query($mixed . " LIMIT " . (($limit * $page) - $limit) . ", " . $limit);
            return $newObject;
        }
        if ($mixed instanceof dbObject) {
            $totalRows = $mixed->extra['totalEntries'];
            $instance = new dbObject($mixed->getInstance(), array("limit" => $limit, 'totalEntries' => $totalRows));
            self::setPaginationWords($instance, $words);
            $page = self::getCurrentPage();
            if ($totalRows > $limit) {
                $newObject = self::query($mixed->getInstance()->queryString . " LIMIT " . (($limit * $page) - $limit) . ", " . $limit);
                return $newObject;
            } else {
                $mixed->extra['limit'] = $limit;
                self::$pageObject = $mixed;
                return $mixed;
            }
        }
        if ($mixed instanceof PDOStatement) {
            $totalRows = $mixed->rowCount();
            self::updateTotalRequests();
            $object =  new dbObject($mixed, array("limit" => $limit));
            self::setPaginationWords($object, $words);
            $page = self::getCurrentPage();
            if ($totalRows > $limit) {
                $newObject = self::query($mixed->queryString . " LIMIT " . (($limit * $page) - $limit) . ", " . $limit);
                return $newObject;
            } else {
                self::setPaginationWords($object, $words);
                return $object;
            }
        }
    }

    /**
     * @param string Set class language. Default is en
     * @return object Return self
     */
    public static function setLanguage($language)
    {
        self::$language = $language;
        return new static(new stdClass);
    }

    /**
     * @param object Receives DB Object instance
     * @param array Receives which words to use on pagination URL
     */
    public static function setPaginationWords($object, $words = false)
    {
        try {
            if (!$words) {
                $words = self::$defaultPaginationWords[self::$language];
            }
            if (isset($words['url'])) {
                $words['url'] = self::URLNormalize($words['url']);
            }
            self::$pageObject = $object;
            self::$pageObject->extra['words'] = $words;
        } catch (Error $e) {
            throw '$object must be a instance of dbObject.';
            throw '$words must have keys: url, prev, next.';
            throw $e;
            return false;
        }
    }

    /**
     * @param object|bool Receives or not, current object. The class will try to get it automatically
     * @return int Current Page number
     */
    private static function getCurrentPage($object = false)
    {
        if (!$object) {
            $object = self::$pageObject;
        }
        $words = $object->extra['words'];
        $pageNow = 1;
        if (self::$friendlyURL !== false) {
            if (self::$friendlyURL->has($words['url'])) {
                foreach (self::$friendlyURL->getParts() as $part) {
                    if (preg_match("/$words[url]/", $part)) {
                        $aux = explode('-', $part);
                        if (isset($aux[1])) {
                            $pageNow = (int) $aux[1];
                            break;
                        }
                    }
                }
            }
        } else {
            $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if (preg_match("/\?{1,}/", $url)) {
                $url = explode("?", $url)[0];
                if (isset($_GET[$words['url']])) {
                    $pageNow = (int) $_GET[$words['url']];
                }
            }
        }
        return $pageNow;
    }

    /**
     * @param bool Defines if the HTML will be output or not
     * @param string Defines which classes will be attached on pagination <li>s
     * @return string Returns generated HTML to page
     */
    public static function page($echo = true, $class = "")
    {
        $totalRows = self::$pageObject->extra['totalEntries'];
        $limit = self::$pageObject->extra['limit'];
        if ($totalRows <= $limit) {
            return false;
        } else {
            if (!isset(self::$pageObject->extra['words'])) {
                self::setPaginationWords(self::$pageObject);
            }
            $words = self::$pageObject->extra['words'];
            $totalPages = ceil($totalRows / $limit);
            $pageNow = self::getCurrentPage(self::$pageObject);
            if ($class) {
                $words['class'] . " " . $class . " ";
            }
            if (self::$friendlyURL !== false) {
                $url = $_SERVER['REQUEST_SCHEME'] . "://" . self::$friendlyURL->getSite();
                $parts = self::$friendlyURL->getParts();
                $keyPart = null;
                foreach ($parts as $key => $part) {
                    if (preg_match("/$words[url]/", $part)) {
                        $keyPart = $key;
                        break;
                    }
                }
            } else {
                $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                if (preg_match("/\?{1,}/", $url)) {
                    $url = explode("?", $url)[0];
                }
            }
            $htmlWrapper = "<ul class='pagination {additional}'>{buttons}</ul>";
            $htmlButton = "<li {disabled}><a {rel} class='" . $words['class'] . " {active}' href='{target}'>{text|number}</a></li>";
            $buttons = array();
            if ($pageNow > 1) {
                if (self::$friendlyURL) {
                    $parts[$keyPart] = self::$friendlyURL->makeLink($words['url'], $pageNow - 1);
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='prev'", $url . "/" . implode("/", $parts), $words["prev"], '', ''), $htmlButton);
                } else {
                    $_GET[$words['url']] = $pageNow - 1;
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='prev'", $url . "?" . http_build_query($_GET), $words["prev"], '', ''), $htmlButton);
                }
            }
            $pageCount = 0;
            while ($pageCount <= $totalPages) {
                $pageCount++;
                if ($pageCount == $pageNow) {
                    if (self::$friendlyURL) {
                        $parts[$keyPart] = self::$friendlyURL->makeLink($words['url'], $pageNow);
                    } else {
                        $_GET[$words['url']] = $pageNow;
                    }
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", "javascript:void(0)", $pageNow, 'active', 'disabled'), $htmlButton);
                } else {
                    if ($pageCount <= $pageNow && ($pageCount >= $pageNow - 4 && ($pageNow == $totalPages || $pageNow + 1 == $totalPages || $pageNow + 2 == $totalPages) || ($pageCount >= $pageNow - 2)) && $pageCount > 0 && count($buttons) < 5 && ($pageCount == $pageNow - 1 || $pageCount == $pageNow - 2 || count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->makeLink($words['url'], $pageCount);
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "/" . implode("/", $parts), $pageCount, '', ''), $htmlButton);
                        } else {
                            $_GET[$words['url']] = $pageCount;
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "?" . http_build_query($_GET), $pageCount, '', ''), $htmlButton);
                        }
                    }
                    if ($pageCount >= $pageNow && $pageCount <= $totalPages && count($buttons) <= 5 && ($pageCount == $pageNow + 1 || $pageCount == $pageNow + 2 ||  count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->makeLink($words['url'], $pageCount);
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "/" . implode("/", $parts), $pageCount, '', ''), $htmlButton);
                        } else {
                            $_GET[$words['url']] = $pageCount;
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "?" . http_build_query($_GET), $pageCount, '', ''), $htmlButton);
                        }
                    }
                }
            }
            if ($pageNow < $totalPages) {
                if (self::$friendlyURL) {
                    $parts[$keyPart] = self::$friendlyURL->makeLink($words['url'], $pageNow + 1);
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='next'", $url . "/" . implode("/", $parts), $words["next"], '', ''), $htmlButton);
                } else {
                    $_GET[$words['url']] = $pageNow + 1;
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='next'", $url . "?" . http_build_query($_GET), $words["next"], '', ''), $htmlButton);
                }
            }
            $return = str_replace(array("{additional}", "{buttons}"), array($words['class'], implode("", $buttons)), $htmlWrapper);
            if ($echo) {
                echo $return;
            } else {
                return $return;
            }
        }
    }

    /**
     * @param string|bool Receives which time to be setted
     * @return string Returns server current date
     */
    public static function date($time = false)
    {
        return ($time ? date("Y-m-d", $time) : date("Y-m-d"));
    }

    /**
     * @param string|bool Receives which datetime to be setted
     * @return string Returns server current datetime
     */
    public static function dateTime($time = false)
    {
        return ($time ? date("Y-m-d H:s:i", $time) : date("Y-m-d H:s:i"));
    }

    /**
     * @param string Receives which collation will be used to switch tables from
     * @param bool Defines if will display SQL queries
     * @param bool Defines if will execute the query automatically
     */
    public static function setCollation($collation, $show = false, $execute = false)
    {
        $db_nome = self::$connections[self::$connectionName]['NAME'];
        $prefixo = explode("_", $collation)[0];
        $sql = 'SELECT CONCAT("ALTER TABLE `\", TABLE_NAME, \"` convert to character set ' . $prefixo . ' collate ' . $collation . ';") AS mySQL
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = "' . $db_nome . '"
        AND TABLE_TYPE = "BASE TABLE"';
        $data = self::query($sql);
        $showArray = array();
        if ($execute && !self::empty($data)) {
            while ($query = self::fetch($data)) {
                if ($show) {
                    $showArray[] = $query['mySQL'];
                }
                self::execute($query['mySQL']);
            }
            self::execute("ALTER DATABASE $db_nome CHARACTER SET $prefixo COLLATE $collation;");
        }
        if ($show) {
            echo "<pre>";
            print_r(implode("<br/>", $showArray));
            echo "</pre>";
        }
    }

    /**
     * @param class Receives FriendlyURL instance
     * @return class Returns self
     */
    public static function setFriendlyURL($FriendlyURLInstance)
    {
        self::$friendlyURL = $FriendlyURLInstance;
        return new static(new stdClass);
    }

    /**
     * @param string Defines which table will be described
     * @return array Returns data pulled from table schema
     */
    private static function getTableCollumns($table)
    {
        return self::fetchAll("DESCRIBE $table;");
    }

    /**
     * @param array Data array where the key is the collumn of the table
     * @param string Table to compare keys and collumns
     * @param array New array with new data to be setted
     * @param string Defines if Data will be fixed to INSERT or to UPDATE - Class handles itself
     * @return array Return new array with data matching all table collumns
     */
    private static function fixDataCollumns($data, $table, &$newData = array(), $mode = "insert")
    {
        $collumns = self::getTableCollumns($table);
        if (!$collumns) {
            return false;
        }
        foreach ($collumns as $collumn) {
            if ($collumn['Key'] == "PRI" && $collumn['Extra'] == "") {
                $id = self::fetch(self::query("SELECT $collumn[Field] FROM $table ORDER BY $collumn[Field] DESC LIMIT 1"));
                $newData[$collumn['Field']] = ++$id['id'];
            } else if ($collumn['Key'] == "PRI" && $collumn['Extra'] !== "") {
                unset($newData[$collumn['Field']]);
            }
            if ($mode !== "insert" && isset($data[$collumn['Field']])) {
                $newData[$collumn['Field']] = $data[$collumn['Field']];
            }
            if ($collumn['Null'] == "NO" && $mode == "insert") {
                if (isset($data[$collumn['Field']])) {
                    $newData[$collumn['Field']] = $data[$collumn['Field']];
                } else {
                    if (preg_match("/int/", $collumn['Type']) && !isset($newData[$collumn['Field']]) && $collumn['Extra'] == "") {
                        $newData[$collumn['Field']] = 0;
                    }
                    if (preg_match("/char|text/", $collumn['Type'])) {
                        $newData[$collumn['Field']] = "";
                    }
                    if (preg_match("/date|time|year/", $collumn['Type'])) {
                        if ($collumn['Type'] == "datetime") {
                            $newData[$collumn['Field']] = "0000-00-00 00:00:00";
                        }
                        if ($collumn['Type'] == "date") {
                            $newData[$collumn['Field']] = "0000-00-00";
                        }
                        if ($collumn['Type'] == "year") {
                            $newData[$collumn['Field']] = "0000";
                        }
                        if ($collumn['Type'] == "timestamp") {
                            $newData[$collumn['Field']] = time();
                        }
                    }
                    if (preg_match('/enum|set/', $collumn['Type'])) {
                        preg_match("/(?:[set|enum])(?:\()(.*?)(?:\))/", $collumn['Type'], $matches);
                        $values = explode(",", $matches[1]);
                        $value = substr($values[0], 1);
                        $value = substr($value, 0, -1);
                        $newData[$collumn['Field']] = $value;
                        unset($value);
                    }
                }
            }
        }
        return $newData;
    }

    /**
     * @param object Instance to prepare query
     * @param string SQL to be prepared
     * @return bool Returns bool if query got prepared or not
     */
    public static function prepare($instance, $sql)
    {
        return $instance->prepare($sql);
    }

    /**
     * @param object Prepared query instance
     * @param string Array key matching table collumn
     * @param string Value for the cell, on current table row
     */
    public static function set(&$instance, $key, $value)
    {
        $instance->bindValue(":" . $key, is_array($value) ? json_encode($value) : $value);
    }

    /**
     * @param string Query to be executed
     * @return bool Returns if it failed or succeeded
     */
    public static function execute($sql)
    {
        return self::getInstance()->exec($sql);
    }

    /**
     * @param array Data to be transformed
     * @param array Additional array containing callback functions to apply to setted data keys
     */
    private static function transformWith(&$data, $additional)
    {
        if (isset($additional['function'])) {
            foreach ($additional['function'] as $fieldKey => $function) {
                if (isset($data[$fieldKey])) {
                    $data[$fieldKey] = call_user_func($function, $data[$fieldKey]);
                }
            }
        }
    }

    /** It looks like the best option to save monney on a database is to use the DECIMAL 19,4 */
    /**
     * @param string|int|float Monney value
     * @return string|float Formatted value
     */
    public static function formatMonney($value)
    {
        $source = array('.', ',');
        $replace = array('', '.');
        $value = str_replace($source, $replace, $value);
        return $value;
    }


    /**
     * @param array Data array to be inserted
     * @param string Table to insert data
     * @param array Additional array to use as callbacks for each data position
     * @return bool Returns if data were inserted or not
     */
    public static function insert($data, $table, $additional = array())
    {
        self::fixDataCollumns($data, $table, $newData);
        if (!$newData) {
            return false;
        }
        $array_keys = array_keys($newData);
        $sql = "INSERT INTO $table (" . implode(", ", $array_keys) . ") VALUES (:" . implode(", :", $array_keys) . ");";
        $instance = self::getInstance();
        $stmnt = self::prepare($instance, $sql);
        if (!empty($additional)) {
            self::transformWith($newData, $additional);
        }
        foreach ($newData as $key => $value) {
            self::set($stmnt, $key, $value);
        }
        try {
            if (is_object($stmnt)) {
                $stmnt->execute();
                self::updateTotalRequests();
                self::$id = $instance->lastInsertId();
                unset($stmnt, $instance);
                return true;
            }
            return false;
        } catch (Error $e) {
            throw ("An error ocurred: " . $e);
            return false;
        }
    }

    /**
     * @return int Returns inserted row ID
     */
    public static function id()
    {
        return self::$id;
    }

    /**
     * @param array Data array to be updated
     * @param string Table to update data
     * @param array Rules to define WHERE data will be updated
     * @param array Additional array to use as callbacks for each data position
     * @return bool Returns if data were updated or not
     */
    public static function update($data, $table, $rules = array(), $additional = array())
    {
        self::fixDataCollumns($data, $table, $newData, "update");
        $collumns = array();
        foreach (array_keys($newData) as $field) {
            $collumns[] = $field . "=:" . $field;
        }
        if (!empty($rules)) {
            $newRules = array();
            foreach ($rules as $key => $value) {
                $newRules[] = $key . "=:" . "rule_" . $key;
            };
        }
        $sql = "UPDATE $table SET " . implode(", ", $collumns) . (!empty($rules) ? " WHERE " . implode(" AND ", $newRules) : "") . ";";
        $instance = self::getInstance();
        $stmnt = self::prepare($instance, $sql);
        if (!empty($additional)) {
            self::transformWith($newData, $additional);
        }
        foreach ($newData as $key => $value) {
            self::set($stmnt, $key, $value);
        }

        if (!empty($rules)) {
            foreach ($rules as $key => $value) {
                self::set($stmnt, "rule_" . $key, $value);
            }
        }
        try {
            if (is_object($stmnt)) {
                $stmnt->execute();
                self::updateTotalRequests();
                return true;
            }
            return false;
        } catch (Error $e) {
            throw ("An error has occured: " . $e);
            return false;
        }
    }

    /**
     * @param string Which table will delete data
     * @param array Rules to define WHERE data will be deleted
     * @return bool Returns if data was or wasn't deleted
     */
    public static function delete($table, $rules = array())
    {
        if (!empty($rules)) {
            $newRules = array();
            foreach ($rules as $key => $value) {
                $newRules[] = $key . "=:" . "rule_" . $key;
            };
        }
        $sql = "DELETE FROM $table " . (empty($newRules) ? "" : "WHERE " . implode(" AND ", $newRules)) . ";";
        $instance = self::getInstance();
        $stmnt = self::prepare($instance, $sql);
        if (!empty($rules)) {
            foreach ($rules as $key => $value) {
                self::set($stmnt, "rule_" . $key, $value);
            }
        }
        try {
            if (is_object($stmnt)) {
                return $stmnt->execute();
            }
            return false;
        } catch (Error $e) {
            throw ("An error has occured: " . $e);
            return false;
        }
    }

    /**
     * @param string Search term to get all words
     * @return array Array containing all words and combinations with the given words
     */
    private static function getAllWords($input)
    {
        $words = array_filter(explode(" ", $input), function ($word) {
            if (strlen($word) > 3) {
                return $word;
            }
        });
        sort($words);
        $allcombinations = array();
        $total = count($words);
        foreach ($words as $word) {
            for ($iterate = 0; $iterate < $total; $iterate++) {
                if ($words[$iterate] !== $word) {
                    $wordNow = $word . " " . $words[$iterate];
                    if (!in_array($wordNow, $allcombinations)) {
                        $allcombinations[] = $wordNow;
                    }
                } else {
                    $allcombinations[] = $word;
                }
            }
            if (count($allcombinations) == self::$wordLimit) {
                break;
            }
        }
        function csort($a, $b)
        {
            return strlen($b) - strlen($a);
        }
        usort($allcombinations, 'csort');
        echo "<pre>";
        print_r($allcombinations);
        echo "</pre>";
        return $allcombinations;
    }

    /**
     * @param string Search term to find data
     * @param string Table name to search data
     * @return object Query instance with prepared SQL
     */
    public static function search($what, $where)
    {
        $words = self::getAllWords($what);
        $collumns = array();
        foreach (self::getTableCollumns($where) as $key => $field) {
            $collumns[] = $field['Field'];
        }
        $concat = "CONCAT(" . implode(", ", $collumns) . ")";
        $sql = "SELECT * FROM $where WHERE $concat LIKE ('%" . implode("%') OR $concat LIKE ('%", $words) . "%')";
        $query = self::query($sql);
        return $query;
    }

    /**
     * @param string String to be transormed into URL acceptable
     * @return string String accepted as URLs
     */
    public static function URLNormalize($string)
    {
        $string = preg_replace('/[áàãâä]/ui', 'a', $string);
        $string = preg_replace('/[éèêë]/ui', 'e', $string);
        $string = preg_replace('/[íìîï]/ui', 'i', $string);
        $string = preg_replace('/[óòõôö]/ui', 'o', $string);
        $string = preg_replace('/[úùûü]/ui', 'u', $string);
        $string = preg_replace('/[ç]/ui', 'c', $string);
        $string = preg_replace('/[^a-z0-9]/i', '_', $string);
        $string = preg_replace('/_+/', '-', $string);
        return $string;
    }
}

/** DB Object class - it will store the result of the request */
class dbObject
{
    /** It is the DB instance */
    protected $instance = null;

    private $data = array();

    /** Array with extra info */
    public $extra = array();

    /** It already sets a number of info on $extra */
    public function __construct($instance, $extra = array())
    {
        $this->setInstance($instance);
        $this->extra = $extra;
        $this->extra['rows'] = -1;
        $this->extra['totalEntries'] = $instance ? $instance->rowCount() : 0;
        $this->extra['query'] = $instance ? $instance->queryString : "";
        if ($extra['simple']) {
            $this->data = $instance ? $instance->fetchAll(PDO::FETCH_ASSOC) : array();
        }
        return $this;
    }

    /**
     * @param object Set as reference to DB Class to work with
     */
    private function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * @return object Returns DB Class intance reference
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param bool Defines if will returns all or a single row only
     * @return array|bool Returns current DB Object Class data
     */
    public function getData($all = false)
    {
        if ($all) {
            $data = $this->getInstance() ? $this->getInstance()->fetchAll(PDO::FETCH_ASSOC) : false;
            db::unsetObject($this);
            return $data;
        }
        if (!$this->extra['simple']) {
            $this->extra["rows"]++;
            $data = $this->getInstance()->fetch(PDO::FETCH_ASSOC);
            if ($this->extra['rows'] == $this->extra['totalEntries']) {
                db::unsetObject($this);
            }
            return $data;
        } else {
            if (empty($this->data)) {
                return false;
            }
            return $this->data[0];
        }
    }
}
