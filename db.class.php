<?php

/**
 * PHP DB Class
 */

class db
{
    protected static $connectionName = null;
    protected static $connections = array();
    protected static $id = null;
    protected static $object = array();
    protected static $pageObject = null;
    protected static $friendlyURL = false;
    private static $dbInit = null;
    protected static $language = "en";
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

    public static function getInstance()
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
            $connection = new PDO('mysql:host=' . self::$connections[self::$connectionName]['HOST'] . ";dbname=" . self::$connections[self::$connectionName]['NAME'] . ";charset=utf8;", self::$connections[self::$connectionName]['USER'], self::$connections[self::$connectionName]['PASSWORD']);
            if ($connection) {
                $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                //$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::updateTotalRequests();
                return $connection;
            } else {
                return false;
            }
        } catch (Exception $exception) {
            echo $exception;
            exit();
        }
    }

    public static function addConnection($connectionName, $connectionCredentials)
    {
        if (!array_key_exists($connectionName, self::$connections)) {
            self::$connections[$connectionName] = $connectionCredentials;
            return new static();
        }
    }

    public static function useConnection($connectionName)
    {
        if (array_key_exists($connectionName, self::$connections)) {
            self::$connectionName = $connectionName;
            return new static();
        }
    }

    private static function updateTotalRequests()
    {
        if (!isset(self::$connections[self::$connectionName]['totalRequests'])) {
            self::$connections[self::$connectionName]['totalRequests'] = 0;
        }
        self::$connections[self::$connectionName]['totalRequests']++;
    }

    public static function getTotalRequests()
    {
        return (isset(self::$connections[self::$connectionName]['totalRequests']) ? self::$connections[self::$connectionName]['totalRequests'] : 0);
    }

    public static function performance()
    {
        echo "<!-- Time ellapsed: " . ((int) microtime() - (int) self::$dbInit) . " -->";
    }

    private static function encapsulate($mixed)
    {
        if (is_string($mixed)) {
            $key = md5($mixed);
            if (!array_key_exists($key, self::$object)) {
                $instance = self::getInstance();
                $object = new dbObject($instance->query($mixed), array("key" => $key));
                self::$object[$key] = $object;
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

    public static function fetch($mixed, $simple = false)
    {
        if (is_string($mixed)) {
            if ($simple) {
                if (!preg_match("/limit/", $mixed)) {
                    $mixed .= " LIMIT 1";
                }
            }
            $mixed = self::encapsulate($mixed);
            if (!isset($mixed->extra['rows'])) {
                $mixed->extra['rows'] = 0;
            }
            $mixed->extra['rows']++;
            return $mixed->getInstance()->fetch(PDO::FETCH_ASSOC);
        }
        if ($mixed instanceof dbObject) {
            return $mixed->getInstance()->fetch(PDO::FETCH_ASSOC);
        }
        if ($mixed instanceof PDOStatement) {
            return $mixed->fetch(PDO::FETCH_ASSOC);
        }
    }

    public static function fetchAll($mixed)
    {
        $mixed = self::encapsulate($mixed);
        if (is_string($mixed)) {
            $mixed = self::encapsulate($mixed);
            return $mixed->getInstance()->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($mixed instanceof dbObject) {
            return $mixed->getInstance()->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($mixed instanceof PDOStatement) {
            return $mixed->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public static function count($mixed)
    {
        $object = self::encapsulate($mixed);
        return $object->extra['totalEntries'];
    }

    public static function empty($query)
    {
        if (is_string($query)) {
            $object = self::query($query);
            return ($object->getInstance()->rowCount() == 0);
        }
        if ($query instanceof dbObject) {
            return ($query->getInstance()->rowCount() == 1);
        }
    }

    public static function query($mixed)
    {
        return self::encapsulate($mixed);
    }

    public static function pagedQuery($mixed, $limit, $page = false, $words = false)
    {
        if (is_string($mixed)) {
            $object = self::query($mixed);
            $object->extra['limit'] = $limit;
            self::setPaginationWords($object, $words);
            if ($page == false) {
                $page = self::getPageNow();
            }
            $newObject = self::query($mixed . " LIMIT " . (($limit * $page) - $limit) . ", " . $limit);
            return $newObject;
        }
        if ($mixed instanceof dbObject) {
            $totalRows = $mixed->extra['totalEntries'];
            $object = new dbObject($mixed->getInstance(), array("limit" => $limit, 'totalEntries' => $totalRows));
            self::setPaginationWords($object, $words);
            $page = self::getPageNow();
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
            $page = self::getPageNow();
            if ($totalRows > $limit) {
                $newObject = self::query($mixed->queryString . " LIMIT " . (($limit * $page) - $limit) . ", " . $limit);
                return $newObject;
            } else {
                self::setPaginationWords($object, $words);
                return $object;
            }
        }
    }

    public static function setLanguage($language)
    {
        self::$language = $language;
        return new static();
    }

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

    private static function getPageNow($object = false)
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
            $pageNow = self::getPageNow(self::$pageObject);
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
            $htmlButton = "<li {disabled}><a class='" . $words['class'] . " {active}' href='{target}'>{text|number}</a></li>";
            $buttons = array();
            if ($pageNow > 1) {
                if (self::$friendlyURL) {
                    $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow - 1);
                    $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "/" . implode("/", $parts), $words["prev"], '', ''), $htmlButton);
                } else {
                    $_GET[$words['url']] = $pageNow - 1;
                    $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "?" . http_build_query($_GET), $words["prev"], '', ''), $htmlButton);
                }
            }
            $pageCount = 0;
            while ($pageCount <= $totalPages) {
                $pageCount++;
                if ($pageCount == $pageNow) {
                    if (self::$friendlyURL) {
                        $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow);
                        $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array("javascript:void(0)", $pageNow, 'active', 'disabled'), $htmlButton);
                    } else {
                        $_GET[$words['url']] = $pageNow;
                        $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array("javascript:void(0)", $pageNow, 'active', 'disabled'), $htmlButton);
                    }
                } else {
                    if ($pageCount <= $pageNow && ($pageCount >= $pageNow - 4 && ($pageNow == $totalPages || $pageNow + 1 == $totalPages || $pageNow + 2 == $totalPages) || ($pageCount >= $pageNow - 2)) && $pageCount > 0 && count($buttons) < 5 && ($pageCount == $pageNow - 1 || $pageCount == $pageNow - 2 || count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageCount);
                            $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "/" . implode("/", $parts), $pageCount, '', ''), $htmlButton);
                        } else {
                            $_GET[$words['url']] = $pageCount;
                            $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "?" . http_build_query($_GET), $pageCount, '', ''), $htmlButton);
                        }
                    }
                    if ($pageCount >= $pageNow && $pageCount <= $totalPages && count($buttons) <= 5 && ($pageCount == $pageNow + 1 || $pageCount == $pageNow + 2 ||  count($buttons) <= 5)) {
                        if (self::$friendlyURL) {
                            $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageCount);
                            $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "/" . implode("/", $parts), $pageCount, '', ''), $htmlButton);
                        } else {
                            $_GET[$words['url']] = $pageCount;
                            $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "?" . http_build_query($_GET), $pageCount, '', ''), $htmlButton);
                        }
                    }
                }
            }
            if ($pageNow < $totalPages) {
                if (self::$friendlyURL) {
                    $parts[$keyPart] = self::$friendlyURL->gerarLink($words['url'], $pageNow + 1);
                    $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "/" . implode("/", $parts), $words["next"], '', ''), $htmlButton);
                } else {
                    $_GET[$words['url']] = $pageNow + 1;
                    $buttons[] = str_replace(array("{target}", '{text|number}', '{active}', '{disabled}'), array($url . "?" . http_build_query($_GET), $words["next"], '', ''), $htmlButton);
                }
            }
            if ($echo) {
                echo str_replace(array("{additional}", "{buttons}"), array($words['class'], implode("", $buttons)), $htmlWrapper);
            } else {
                return true;
            }
        }
    }

    public static function date($time = false)
    {
        return ($time ? date("Y-m-d", $time) : date("Y-m-d"));
    }

    public static function dateTime($time = false)
    {
        return ($time ? date("Y-m-d H:s:i", $time) : date("Y-m-d H:s:i"));
    }

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

    public static function setFriendlyURL($FriendlyURLInstance)
    {
        self::$friendlyURL = $FriendlyURLInstance;
        return new static();
    }

    private static function getTableCollumns($table)
    {
        return self::fetchAll(self::query("DESCRIBE $table"));
    }

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

    public static function prepare($object, $sql)
    {
        return $object->prepare($sql);
    }

    public static function set(&$object, $key, $value)
    {
        $object->bindValue(":" . $key, $value);
    }

    public static function execute($sql)
    {
        return self::getInstance()->exec($sql);
    }

    public static function insert($data, $table, $rules = array())
    {
        self::fixDataCollumns($data, $table, $newData);
        $sql = "INSERT INTO $table (" . implode(", ", array_keys($newData)) . ") VALUES (:" . implode(", :", array_keys($newData)) . ");";
        $object = self::getInstance();
        $stmnt = self::prepare($object, $sql);
        foreach ($newData as $key => $value) {
            self::set($stmnt, $key, $value);
        }
        if ($stmnt->execute()) {
            self::updateTotalRequests();
            self::$id = $object->lastInsertId();
            return true;
        } else {
            return false;
        }
    }

    public static function id()
    {
        return self::$id;
    }

    public static function update($data, $table, $rules = array())
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
        $object = self::getInstance();
        $stmnt = self::prepare($object, $sql);
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

    public static function delete($table, $rules = array())
    {
        if (!empty($rules)) {
            $newRules = array();
            foreach ($rules as $key => $value) {
                $newRules[] = $key . "=:" . "rule_" . $key;
            };
        }
        $sql = "DELETE FROM $table " . (empty($newRules) ? "" : "WHERE " . implode(" AND ", $newRules)) . ";";
        $object = self::getInstance();
        $stmnt = self::prepare($object, $sql);
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

    public function URLNormalize($string)
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

class dbObject
{
    protected static $instance = null;
    public $extra = null;

    public function __construct($instance, $extra = array())
    {
        $this->setInstance($instance);
        $this->extra = $extra;
        $this->extra['totalEntries'] = $instance->rowCount();
        return $this;
    }
    private static function setInstance($instance)
    {
        self::$instance = $instance;
    }
    public static function getInstance()
    {
        return self::$instance;
    }
}
