<?php

/**
 * PHP DB Class
 * 2021-01-07 -> Class created
 * 2021-03-12 -> Added rel="canonical|prev|next" to <a> pagination tags
 * 2021-03-12 -> Added a lot of comments
 * 2021-06-05 -> Added transformWith private method for insert and update methods using the $additional array. Added formatMonney public method to work with monetary values. Changed getPageNow to getCurrentPage. Added search method.
 * 2021-08-17 -> Made a few improvements within base core functions
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

    /** Will return a database instance. It also sets manny vars about the connection, if isn't yet defined */
    private static function getInstance()
    {
        if (self::$dbInit == null) {
            self::$dbInit = microtime();
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
                //$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::updateTotalRequests();
                return $instance;
            } else {
                exit("There's been an error within the database");
                return false;
            }
        } catch (Exception $exception) {
            echo $exception;
            exit();
        }
    }

    /** Adds a database connection */
    public static function addConnection($connectionName, $connectionCredentials)
    {
        if (!array_key_exists($connectionName, self::$connections)) {
            self::$connections[$connectionName] = $connectionCredentials;
            return new static(new stdClass);
        }
    }

    /** Defines which connection the class will use */
    public static function useConnection($connectionName)
    {
        if (array_key_exists($connectionName, self::$connections)) {
            self::$connectionName = $connectionName;
            return new static(new stdClass);
        }
    }

    /** Sets total amount of requests to +1 */
    private static function updateTotalRequests()
    {
        if (!isset(self::$connections[self::$connectionName]['totalRequests'])) {
            self::$connections[self::$connectionName]['totalRequests'] = 0;
        }
        self::$connections[self::$connectionName]['totalRequests']++;
    }

    /** Returns the total amount of requests that the class did so far */
    public static function getTotalRequests()
    {
        return (isset(self::$connections[self::$connectionName]['totalRequests']) ? self::$connections[self::$connectionName]['totalRequests'] : 0);
    }

    /** Checks the server speed */
    public static function performance()
    {
        echo "<!-- Time ellapsed: " . ((int) microtime() - (int) self::$dbInit) . " -->";
    }

    /** Creates a new DB Object with the given PDO instance */
    private static function encapsulate($mixed)
    {
        if (is_string($mixed)) {
            $key = md5($mixed);
            if (!array_key_exists($key, self::$object)) {
                $instance = new dbObject(self::getInstance()->query($mixed), array("key" => $key));
                self::$object[$key] = $instance;
            }
            if (self::$object[$key]->extra["rows"] + 1 == self::$object[$key]->extra["totalEntries"] || self::$object[$key]->extra["rows"] + 1 >= self::$object[$key]->extra["totalEntries"]) {
                unset(self::$object[$key]);
                return self::encapsulate($mixed);
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

    /** Returns a single row */
    public static function fetch($mixed, $simple = false)
    {
        if (is_string($mixed)) {
            if ($simple) {
                if (!preg_match("/limit/", $mixed)) {
                    $mixed .= " LIMIT 1";
                }
            }
            $mixed = self::encapsulate($mixed);
            return $mixed->getData();
            //return ($mixed ? $mixed->getData() : $mixed);
        }
        if ($mixed instanceof dbObject) {
            return $mixed->getData();
        }
        if ($mixed instanceof PDOStatement) {
            return $mixed->fetch(PDO::FETCH_ASSOC);
        }
    }

    /** Returns all fetched data */
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

    /** It counts the total rows that $mixed have */
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

    /** Checks if the given $query returns null. If it returns null or 0, the function return true (is empty) */
    public static function empty($query)
    {
        if (is_string($query)) {
            return (self::query($query)->getInstance()->rowCount() == 0);
        }
        if ($query instanceof dbObject) {
            return ($query->getInstance()->rowCount() == 1);
        }
        if (is_bool($query)) {
            return !$query;
        }
    }

    /** Simple query */
    public static function query($mixed)
    {
        return self::encapsulate($mixed);
    }

    /** This is a paged query */
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

    /** It sets the class $language */
    public static function setLanguage($language)
    {
        self::$language = $language;
        return new static(new stdClass);
    }

    /** It defines the words that the pagination HTML will have */
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
        } catch (Exception $error) {
            echo '$object must be a instance of dbObject<br/>';
            echo '$words must have keys: url, prev, next<br/>';
            echo $error;
            return false;
        }
    }

    /** It retrieves the current page */
    private static function getCurrentPage($object = false)
    {
        if (!$object) {
            $object = self::$pageObject;
        }
        $words = $object->extra['words'];
        $pageNow = 1;
        if (self::$friendlyURL !== false) {
            if (self::$friendlyURL->contem($words['url'])) {
                foreach (self::$friendlyURL->getPartes() as $part) {
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

    /** It creates the pagination HTML */
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
                $parts = self::$friendlyURL->getPartes();
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
                    $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow - 1);
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
                        $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow);
                    } else {
                        $_GET[$words['url']] = $pageNow;
                    }
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", "javascript:void(0)", $pageNow, 'active', 'disabled'), $htmlButton);
                } else {
                    if ($pageCount <= $pageNow && ($pageCount >= $pageNow - 4 && ($pageNow == $totalPages || $pageNow + 1 == $totalPages || $pageNow + 2 == $totalPages) || ($pageCount >= $pageNow - 2)) && $pageCount > 0 && count($buttons) < 5 && ($pageCount == $pageNow - 1 || $pageCount == $pageNow - 2 || count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageCount);
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "/" . implode("/", $parts), $pageCount, '', ''), $htmlButton);
                        } else {
                            $_GET[$words['url']] = $pageCount;
                            $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("", $url . "?" . http_build_query($_GET), $pageCount, '', ''), $htmlButton);
                        }
                    }
                    if ($pageCount >= $pageNow && $pageCount <= $totalPages && count($buttons) <= 5 && ($pageCount == $pageNow + 1 || $pageCount == $pageNow + 2 ||  count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageCount);
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
                    $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow + 1);
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='next'", $url . "/" . implode("/", $parts), $words["next"], '', ''), $htmlButton);
                } else {
                    $_GET[$words['url']] = $pageNow + 1;
                    $buttons[] = str_replace(array("{rel}", "{target}", '{text|number}', '{active}', '{disabled}'), array("rel='next'", $url . "?" . http_build_query($_GET), $words["next"], '', ''), $htmlButton);
                }
            }
            if ($echo) {
                echo str_replace(array("{additional}", "{buttons}"), array($words['class'], implode("", $buttons)), $htmlWrapper);
            } else {
                return true;
            }
        }
    }

    /** Returns server date */
    public static function date($time = false)
    {
        return ($time ? date("Y-m-d", $time) : date("Y-m-d"));
    }

    /** Returns server dateTime */
    public static function dateTime($time = false)
    {
        return ($time ? date("Y-m-d H:s:i", $time) : date("Y-m-d H:s:i"));
    }

    /** It will change the whole database $collation */
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

    /** Will define the $friendlyURL instance on the class */
    public static function setFriendlyURL($FriendlyURLInstance)
    {
        self::$friendlyURL = $FriendlyURLInstance;
        return new static(new stdClass);
    }

    /** It will retrieve the collumns of the given $table */
    private static function getTableCollumns($table)
    {
        return self::fetchAll(self::query("DESCRIBE $table"));
    }

    /** It will fix invalid $data keys before insert them on the $table. It removes not used keys inside $data, and bind a empty string if a specific $key that exists on the table, doesn't exist on $data */
    private static function fixDataCollumns($data, $table, &$newData = array(), $mode = "insert")
    {
        $collumns = self::getTableCollumns($table);
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

    /** It will prepare a given $sql to the given $object */
    public static function prepare($instance, $sql)
    {
        return $instance->prepare($sql);
    }

    /** It will set the given $value on the specific $key, inside the $object. Object is a PDO Statement */
    public static function set(&$instance, $key, $value)
    {
        $instance->bindValue(":" . $key, $value);
    }

    /** It executes any given $sql */
    public static function execute($sql)
    {
        return self::getInstance()->exec($sql);
    }

    /** It will apply the additional steps before the real method applyment */
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

    /** This function is responsible to make the given value a decimal value for monetary operations */
    /** It looks like the best option to save monney on a database is to use the DECIMAL 19,4 */
    public static function formatMonney($value)
    {
        $source = array('.', ',');
        $replace = array('', '.');
        $value = str_replace($source, $replace, $value);
        return $value;
    }


    /** It will insert $data on a specific $table. The $rules are optional */
    public static function insert($data, $table, $additional = array())
    {
        self::fixDataCollumns($data, $table, $newData);
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
        if ($stmnt->execute()) {
            self::updateTotalRequests();
            self::$id = $instance->lastInsertId();
            unset($stmnt, $instance);
            return true;
        } else {
            return false;
        }
    }

    /** It will return the id of the last inserted row */
    public static function id()
    {
        return self::$id;
    }

    /** It will update a row on a specific $table with new $data. The row must attend to the $rules */
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
        if ($stmnt->execute()) {
            self::updateTotalRequests();
            return true;
        } else {
            return false;
        }
    }

    /** It will delete a record - or all - on a specific $table */
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
        if ($stmnt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    /** Will return an array with all words inside the given input */
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

    /** It will search for the given input inside the given table and return all records found */
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
        return $query;;
    }

    /** It will normalize a string to be accepted on URL addresses */
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
    protected static $instance = null;

    private $data = array();

    /** Array with extra info */
    public $extra = array();

    /** It already sets a number of info on $extra */
    public function __construct($instance, $extra = array())
    {
        $this->setInstance($instance);
        $this->extra = $extra;
        $this->extra['rows'] = -1;
        $this->extra['totalEntries'] = $instance->rowCount();
        $this->extra['query'] = $instance->queryString;
        $this->data = $instance->fetchAll(PDO::FETCH_ASSOC);
        return $this;
    }

    /** It will set the $instance */
    private static function setInstance($instance)
    {
        self::$instance = $instance;
    }

    /** It will retrieve the object's DB $instance */
    public static function getInstance()
    {
        return self::$instance;
    }

    /** Returns current data */
    public function getData($all = false)
    {
        if ($all) {
            return $this->data;
        }
        $this->extra["rows"]++;
        return isset($this->data[$this->extra["rows"]]) ? $this->data[$this->extra["rows"]] : false;
    }
}
