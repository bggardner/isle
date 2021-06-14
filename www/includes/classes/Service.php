<?php

/**
 * The ISLE\Service class provides data services to the ISLE web application
 */

namespace ISLE;

use ISLE\DataDataModels\Node as Node;

class Service
{
    protected const TABLES = [
        'roles',
        'users',
        'categories',
        'locations',
        'manufacturers',
        'uploads',
        'models',
        'model_attachments',
        'model_categories',
        'assets',
        'asset_attachments',
        'transaction_types',
        'transactions',
        'attributes',
        'model_attributes'
    ];

    protected static $config;
    protected static $data_source;
    protected static $install_verified = false;

    public static function authenticateUser($email, $password)
    {
        $user = static::executeStatement(
            '
  SELECT * FROM `' . Settings::get('table_prefix') . 'users` WHERE LOWER(`email`) = LOWER(?)
            ',
            [
                [
                    'value' => $email,
                    'type' => \PDO::PARAM_STR
                ]
            ]
        )->fetch();
        if (!$user) {
            throw new \Exception('User not found!');
        }
        if (!password_verify($password, $user['hash'])) {
            throw new \Exception('Authentication failed!');
        }
        return array_diff($user, ['hash' => null]);
    }

    public static function userAuthenticator()
    {
        if (!isset($_GET['login']) || !isset($_POST['email']) || !isset($_POST['password'])) {
           require_once __DIR__ . '/../views/layouts/pagestart.php';
           require_once __DIR__ . '/../views/login.php';
           require_once __DIR__ . '/../views/layouts/pageend.php';
           exit;
        }
        return static::authenticateUser($_POST['email'], $_POST['password']);
    }

    /**
     * =========================================================================
     * | PDO Wrapper Methods                                                   |
     * =========================================================================
     */
    protected static function getDataSource()
    {
        if (!is_a(static::$data_source, '\PDO')) {
            static::setDataSource(
                Settings::get('pdo_dsn'),
                Settings::get('pdo_username'),
                Settings::get('pdo_password')
            );
        }
        return static::$data_source;
    }

    protected static function setDataSource($dsn, $username, $password)
    {
            static::$data_source = new \PDO(
                $dsn,
                $username,
                $password
            );
            static::$data_source->setAttribute(\PDO::ATTR_EMULATE_PREPARES, TRUE);
            static::$data_source->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            static::$data_source->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    protected static function exec($stmt)
    {
        return static::getDataSource()->exec($stmt);
    }

    protected static function prepare($stmt)
    {
        return static::getDataSource()->prepare($stmt);
    }

    protected static function query($stmt)
    {
        return static::getDataSource()->query($stmt);
    }

    /**
     * =========================================================================
     * | PDOStatement Utility Methods                                          |
     * =========================================================================
     */
    public static function executeStatement($query, $values = [])
    {
        $stmt = static::prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key + 1, $value['value'], $value['type']);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function insertStatement($query, $values = [])
    {
      static::executeStatement($query, $values);
      return static::getDataSource()->lastInsertId();
    }

    /**
     * =========================================================================
     * | Database Utility Methods                                              |
     * =========================================================================
     */

    protected static function enumToRowConstructorList($enum)
    {
        $array = array_filter(call_user_func('ISLE\\' . $enum . '::getValues'));
        array_walk($array, function(&$value, $key) { $value = '(' . $value . ', "' . $key . '")'; });
        return implode(",\n", $array);
    }

    public static function export()
    {
        $sql = '';
        foreach (static::TABLES as $table) {
            $sql .= static::executeStatement('SHOW CREATE TABLE `' . Settings::get('table_prefix') . $table . '`')->fetchColumn(1) . ";\n\n";
        }
        foreach (static::TABLES as $table) {
            $table = '`' . Settings::get('table_prefix') . $table . '`';
            $columns = static::executeStatement('SHOW COLUMNS FROM ' . $table)->fetchAll();
            $sql .= 'INSERT INTO ' . $table . ' (';
            $sql .= implode(', ', array_map(function($column) { return '`' . $column['Field'] . '`'; }, $columns));
            $sql .= ") VALUES\n";
            $stmt = static::executeStatement('SELECT * FROM ' . $table);
            while ($row = $stmt->fetch()) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column['Field']];
                    if (!preg_match('/^int/i', $column['Type'])) {
                        $value = '"' . $value . '"';
                    }
                    $values[] = $value;
                }
                $sql .= '(' . implode(', ', $values) . ")\n";
            }
            $sql .= "\n";
        }
        return $sql;
    }

    public static function install()
    {
        static::setDataSource(
            $_POST['pdo_dsn'],
            $_POST['pdo_username'],
            $_POST['pdo_password']
        );

        $thumbs_dir = $_POST['uploads_path'] . '/images/thumbs';
        if (!file_exists($thumbs_dir)) {
            if (!mkdir($thumbs_dir, 0777, true)) {
                throw new \Exception('Failed to create uploads directory');
            }
        }

        file_put_contents(Settings::FILE, "<?php

return [
    'pdo_dsn' => '" . $_POST['pdo_dsn'] . "',
    'pdo_username' => '" . $_POST['pdo_username'] . "',
    'pdo_password' => '" . $_POST['pdo_password'] . "',
    'table_prefix' => '" . $_POST['table_prefix'] . "',
    'uploads_path' => '" . $_POST['uploads_path'] . "',
    'web_root' => '" . rtrim($_POST['web_root'], '/') . "'
];
"
        );

        static::createTables();
        $_POST['role'] = DataModels\Role::ADMINISTRATOR;
        $_SESSION['user']['id'] = static::addUser($_POST);
    }

    protected static function createTables()
    {
        $table_prefix = Settings::get('table_prefix');
        $engine = 'InnoDB';
        $character_set = 'utf8';
        $collation = 'utf8_general_ci';
        $table_options = 'ENGINE ' . $engine . ' CHARACTER SET ' . $character_set . ' COLLATE ' . $collation;

        static::exec(
            '
  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'roles` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE
  )
  ' . $table_options . ';

  INSERT INTO `' . $table_prefix . 'roles` (`id`, `name`)
  VALUES
    ' . static::enumToRowConstructorList('DataModels\Role') . '
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'users` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `hash` VARCHAR(255) NOT NULL,
    `role` INT(10) UNSIGNED NOT NULL,
    FOREIGN KEY (`role`)
      REFERENCES `' . $table_prefix . 'roles` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'categories` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `left` INT(10) UNSIGNED NOT NULL,
    `right` INT(10) UNSIGNED NOT NULL
  )
  ' . $table_options . ';

  INSERT INTO `' . $table_prefix . 'categories` (`id`, `name`, `left`, `right`)
  VALUES (1, "Categories", 1, 2)
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'locations` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `left` INT(10) UNSIGNED NOT NULL,
    `right` INT(10) UNSIGNED NOT NULL
  )
  ' . $table_options . ';

  INSERT INTO `' . $table_prefix . 'locations` (`id`, `name`, `left`, `right`)
  VALUES (1, "Locations", 1, 2)
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'manufacturers` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'uploads` (
    `hash` VARCHAR(255) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `extension` VARCHAR(255),
    `image` TINYINT(1)
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'models` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `description` VARCHAR(255) NOT NULL,
    `manufacturer` INT(10) UNSIGNED NOT NULL,
    `model` VARCHAR(255) NOT NULL,
    `series` VARCHAR(255),
    `url` VARCHAR(255),
    `image` VARCHAR(255),
    UNIQUE KEY (
      `manufacturer`,
      `model`
    ),
    FOREIGN KEY (`manufacturer`)
      REFERENCES `' . $table_prefix . 'manufacturers` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`image`)
      REFERENCES `' . $table_prefix . 'uploads` (`hash`)
      ON DELETE SET NULL
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'model_categories` (
    `model` INT(10) UNSIGNED NOT NULL,
    `category` INT(10) UNSIGNED NOT NULL,
    UNIQUE KEY (
      `model`,
      `category`
    ),
    FOREIGN KEY (`model`)
      REFERENCES `' . $table_prefix. 'models` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`category`)
      REFERENCES `' . $table_prefix . 'categories` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'model_attachments` (
    `model` INT(10) UNSIGNED NOT NULL,
    `attachment` VARCHAR(255) NOT NULL,
    UNIQUE KEY (
      `model`,
      `attachment`
    ),
    FOREIGN KEY (`model`)
      REFERENCES `' . $table_prefix. 'models` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`attachment`)
      REFERENCES `' . $table_prefix . 'uploads` (`hash`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'assets` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `model` INT(10) UNSIGNED NOT NULL,
    `location` INT(10) UNSIGNED NOT NULL,
    `serial` VARCHAR(255) NOT NULL,
    UNIQUE KEY (
      `model`,
      `serial`
    ),
    FOREIGN KEY (`model`)
      REFERENCES `' . $table_prefix . 'models` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`location`)
      REFERENCES `' . $table_prefix . 'locations` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'asset_attachments` (
    `asset` INT(10) UNSIGNED NOT NULL,
    `attachment` VARCHAR(255) NOT NULL,
    UNIQUE KEY (
      `asset`,
      `attachment`
    ),
    FOREIGN KEY (`asset`)
      REFERENCES `' . $table_prefix. 'assets` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`attachment`)
      REFERENCES `' . $table_prefix . 'uploads` (`hash`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'transaction_types` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE
  )
  ' . $table_options . ';

  INSERT INTO `' . $table_prefix . 'transaction_types` (`id`, `name`)
  VALUES
    ' . static::enumToRowConstructorList('DataModels\TransactionType') . '
  ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'transactions` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `type` INT(10) UNSIGNED NOT NULL,
    `user` INT(10) UNSIGNED NOT NULL,
    `asset` INT(10) UNSIGNED NOT NULL,
    `location` INT(10) UNSIGNED NOT NULL,
    `returning` DATE NULL,
    `notes` VARCHAR(255),
    `ts` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`type`)
      REFERENCES `' . $table_prefix . 'transaction_types` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`user`)
      REFERENCES `' . $table_prefix . 'users` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`asset`)
      REFERENCES `' . $table_prefix . 'assets` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`location`)
      REFERENCES `' . $table_prefix . 'locations` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'attributes` (
    `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(255)
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'model_attributes` (
    `model` INT(10) UNSIGNED NOT NULL,
    `attribute` INT(10) UNSIGNED NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    UNIQUE KEY (
      `model`,
      `attribute`,
      `value`
    ),
    FOREIGN KEY (`model`)
      REFERENCES `' . $table_prefix . 'models` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`attribute`)
      REFERENCES `' . $table_prefix . 'attributes` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';

  CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'asset_attributes` (
    `asset` INT(10) UNSIGNED NOT NULL,
    `attribute` INT(10) UNSIGNED NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    UNIQUE KEY (
      `asset`,
      `attribute`,
      `value`
    ),
    FOREIGN KEY (`asset`)
      REFERENCES `' . $table_prefix . 'assets` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
    FOREIGN KEY (`attribute`)
      REFERENCES `' . $table_prefix . 'attributes` (`id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
  )
  ' . $table_options . ';
            '
        );
    }

    public static function uninstall()
    {
        try {
            static::exec(
                implode(
                    ";\n",
                    array_map(
                        function($table)
                        {
                            return 'DELETE FROM `' . Settings::get('table_prefix') . $table . '`';
                        },
                        array_reverse(static::TABLES)
                    )
                )
            );
            static::deleteOrphanUploads();
        } catch (\Exception $e) {}

        static::exec(
            implode(
                ";\n",
                array_map(
                    function($table)
                    {
                        return 'DROP TABLE IF EXISTS `' . Settings::get('table_prefix') . $table . '`';
                    },
                    array_reverse(static::TABLES)
                )
            )
        );

        static::deleteFiles(Settings::get('uploads_path'));
        static::deleteFiles(Settings::FILE);

        $_SESSION['message']['type'] = 'success';
        $_SESSION['message']['text'] = 'Uninstall successful';
    }

    protected static function deleteFiles($path)
    {
        if (empty($path)) {
            return false;
        }
        return is_file($path) ? @unlink($path) : array_map('static::' . __FUNCTION__, glob($path . '/*')) == @rmdir($path); /**/
    }


    public static function verifyInstall()
    {
        if (static::$install_verified) {
            return;
        }
        if (!file_exists(Settings::FILE)) {
            if ($_POST) {
                try {
                    static::install();
                } catch (\Exception $e) {
                    static::uninstall();
                    $_SESSION['message'] = [
                        'type' => 'danger',
                        'text' => 'Installation failed: ' . $e->getMessage()
                    ];
                    require __DIR__ . '/../setup.php';
                    exit;
                }
                $_SESSION['message']['type'] = 'success';
                $_SESSION['message']['text'] = 'Setup completed successfully';
                header('Location: ' . $_SERVER['REQUEST_URI']); // Clear POST data
                exit;
            }
            unset($_SESSION['user']);
            $_SESSION['message'] = [
                'type' => 'warning',
                'text' => 'Please complete setup to continue.'
            ];
            require __DIR__ . '/../setup.php';
            exit;
        }

        // These checks are optional, except when installing a new instance
        $database = static::executeStatement('SELECT DATABASE()')->fetchColumn();
        $tables = static::executeStatement('
  SHOW TABLES
  FROM `' . $database . '`
  WHERE `Tables_in_' . $database . '` IN (
    "' . implode('","', array_map(function($table) { return Settings::get('table_prefix') . $table; }, static::TABLES)) . '"
  )
        ')->fetchAll();
        if (count($tables) != count(static::TABLES)) {
            try {
                static::createTables();
            } catch (Exception $e) {
                $_SESSION['message'] = [
                    'type' => 'danger',
                    'text' => 'Installation failed: ' . $e->getMessage()
                ];
                exit;
            }
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'The database was successfully initialized'
            ];
        }

        $admin = static::executeStatement(
            'SELECT * FROM `' . Settings::get('table_prefix') . 'users` WHERE `role` = ? LIMIT 1',
            [['value' => DataModels\Role::ADMINISTRATOR, 'type' => \PDO::PARAM_INT]]
        )->fetch();
        if (!$admin) {
            if ((($_SESSION['state'] ?? null) == 'adminonly') && $_POST) {
                $_POST['role'] = DataModels\Role::ADMINISTRATOR;
                $_SESSION['user']['id'] = static::addUser($_POST);
                unset($_SESSION['state']);
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => 'Administrator account successfully added'
                ];
            } else {
                $_SESSION['state'] = 'adminonly';
                require __DIR__ . '/../setup.php';
                exit;
            }
        }

        static::$install_verified = true;
    }

    /**
     * =========================================================================
     * | Tree (Nested Set) Node Methods                                        |
     * =========================================================================
     */

    /**
     * Adds a new node as the left-most child of a parent in a tree
     *
     * @param string $table Name of the database table
     * @param string $name Name of the new node
     * @param int $parent ID of the parent
     */
    public static function addTreeNode($table, $name, $parent)
    {
        static::checkDuplicateTreeNode($table, $name, $parent);
        $table = '`' . Settings::get('table_prefix') . $table . '`';
        return static::insertStatement(
            '
  LOCK TABLES ' . $table . ' WRITE;
  SELECT @newLeft := `right` FROM ' . $table . ' WHERE `id` = ?;
  UPDATE ' . $table . ' SET `right` = `right` + 2 WHERE `right` >= @newLeft;
  UPDATE ' . $table . ' SET `left` = `left` + 2 WHERE `left` > @newLeft;
  INSERT INTO ' . $table . ' (`name`,`left`,`right`) VALUES (?, @newLeft, @newLeft + 1);
  UNLOCK TABLES;
            ',
            [
                ['value' => $parent, 'type' => \PDO::PARAM_INT],
                ['value' => $name, 'type' => \PDO::PARAM_STR]
            ]
        );
    }

    public static function checkDuplicateTreeNode($table, $name, $parent, $node = 0)
    {
        $table = '`' . Settings::get('table_prefix') . $table . '`';
        $stmt = static::executeStatement(
            '
  SELECT
    1
  FROM ' . $table . ' AS `nodes`
  CROSS JOIN ' . $table . ' AS `parents`
  WHERE
    `nodes`.`left` BETWEEN `parents`.`left` AND `parents`.`right`
    AND `parents`.`id` = ?
    AND `nodes`.`name` = ?
    AND `nodes`.`id` != ?
            ',
            [
                ['value' => $parent, 'type' => \PDO::PARAM_INT],
                ['value' => $name, 'type' => \PDO::PARAM_STR],
                ['value' => $node, 'type' => \PDO::PARAM_INT]
            ]
        )->fetchColumn();
        if ($stmt) {
            throw new \Exception('Tree cannot have a sibling with the same name');
        }
    }

    /**
     * Deletes a node from the tree
     *
     * @param string $table Name of the database table
     * @param int $id ID of the node
     */
    public static function deleteTreeNode($table, $id)
    {
        $table = '`' . Settings::get('table_prefix') . $table . '`';
        static::executeStatement(
            '
  LOCK TABLES ' . $table . ' WRITE;
  SELECT @myLeft := `left`, @myRight := `right`, @myWidth = `right` - `left` + 1 FROM ' . $table . ' WHERE `id` = ?;
  DELETE FROM ' . $table . ' WHERE `left` BETWEEN @myLeft AND @myRight;
  UPDATE ' . $table . ' SET `right` = `right` - @myWidth WHERE `right` > @myRight;
  UPDATE ' . $table . ' SET `left` = `left` - @myWidth WHERE `left` > @myRight;
  UNLOCK TABLES;
            ',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
    }

    /**
     * Edits the node name and optionally moves the node (and its children) to a new parent
     *
     * @param string $table Name of the database table
     * @param int $id ID of the node
     * @param string $name New name for the node
     * @param int|null $parent ID of the parent if node is to be moved
     */
    public static function editTreeNode($table, $id, $name, $parent = null)
    {
        static::checkDuplicateTreeNode($table, $name, $parent, $id);
        $stmt = static::executeStatement(
            'UPDATE `' . Settings::get('table_prefix') . $table . '` SET `name` = ? WHERE `id` = ?',
            [
                ['value' => $name, 'type' => \PDO::PARAM_STR],
                ['value' => $id, 'type' => \PDO::PARAM_INT]
            ]
        );
        if (!is_null($parent)) {
            static::moveTreeNode($table, $id, $parent);
        }
    }

    /**
     * Moves a node and its children as the left-most child of a parent
     *
     * @param string $table Name of the database table
     * @param int $id ID of the node to be moved
     * @param int $parent ID of the parent
     */
    public static function moveTreeNode($table, $id, $parent)
    {
        $table_suffix = $table;
        $table = Settings::get('table_prefix') . $table;
        $stmt = static::executeStatement(
            '
  LOCK TABLES `' . $table . '` WRITE;
  SELECT @nodeLeft := `left`, @nodeRight := `right`, @nodeSize := `right` - `left` + 1 FROM `' . $table . '` WHERE `id` = ?;
  SELECT @maxRight := MAX(`right`) FROM `' . $table . '`;
  UPDATE `' . $table . '` SET `left` = `left` + @maxRight, `right` = `right` + @maxRight WHERE `left` BETWEEN @nodeLeft AND @nodeRight; # Shift sub-tree above @maxRight
  UPDATE `' . $table . '` SET `right` = `right` - @nodeSize  WHERE `right` BETWEEN @nodeRight AND @maxRight; # Same as deleting
  UPDATE `' . $table . '` SET `left` = `left` - @nodeSize  WHERE `left` BETWEEN @nodeRight AND @maxRight; # Same as deleting
  SELECT @parentLeft := `left`, @parentRight := `right` FROM `' . $table . '` WHERE `id` = ?;
  UPDATE `' . $table . '` SET `right` = `right` + @nodeSize WHERE `right` >= @parentLeft AND `right` <= @maxRight; # Same as adding
  UPDATE `' . $table . '` SET `left` = `left` + @nodeSize WHERE `left` > @parentLeft AND `left` <= @maxRight; # Same as adding
  UPDATE `' . $table . '` SET `left` = `left` - @maxRight - @nodeLeft + @parentLeft + 1, `right` = `right` - @maxRight - @nodeLeft + @parentLeft + 1 WHERE `left` > @maxRight;
  UNLOCK TABLES;
            ',
            [
                ['value' => $id, 'type' => \PDO::PARAM_INT],
                ['value' => $parent, 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    /**
     * =========================================================================
     * | Node Methods                                                         |
     * =========================================================================
     */

    public static function addAsset($node)
    {
        $node = DataModels\Asset::validate($node);
        $id =  static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'assets` (
    `model`,
    `location`,
    `serial`
  )
  VALUES (?, ?, ?)
            ',
            [
                ['value' => $node['model'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['location'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['serial'], 'type' => \PDO::PARAM_STR]
            ]
        );
        static::updateAttributes('asset', $id, $node['attributes']);
        static::updateAttachments('asset', $id, $node['attachments']);
        return $id;
    }

    public static function addAttribute($node)
    {
        $node = DataModels\Attribute::validate($node);
        return static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'attributes` (`name`)
  VALUES (?)
            ',
            [['value' => $node['name'], 'type' => \PDO::PARAM_STR]]
        );
    }

    public static function addModel($node)
    {
        $node = DataModels\Model::validate($node);
        $id = static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'models` (
    `description`,
    `manufacturer`,
    `model`,
    `series`,
    `url`,
    `image`
  )
  VALUES (?, ?, ?, ?, ?, ?)
            ',
            [
                ['value' => $node['description'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['manufacturer'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['model'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['series'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['url'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['image'], 'type' => \PDO::PARAM_STR]
            ]
        );
        static::updateAttributes('model', $id, $node['attributes']);
        static::updateAttachments('model', $id, $node['attachments']);
        static::updateModelCategories($id, $node['categories']);
    }

    public static function addCategory($node)
    {
        $node = DataModels\Category::validate($node);
        return static::addTreeNode('categories', $node['name'], $node['parent']);
    }

    public static function addLocation($node)
    {
        $node = DataModels\Location::validate($node);
        return static::addTreeNode('locations', $node['name'], $node['parent']);
    }

    public static function addManufacturer($node)
    {
        $node = DataModels\Manufacturer::validate($node);
        return static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'manufacturers` (`name`)
  VALUES (?)
            ',
            [['value' => $node['name'], 'type' => \PDO::PARAM_STR]]
        );
    }

    public static function addTransaction($node) {
        $node = DataModels\Transaction::validate($node);
        return static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'transactions`
  ( `type`, `user`, `asset`, `location`, `returning`, `notes`)
  VALUES (?, ?, ?, ?, ?, ?)
            ',
            [
                ['value' => $node['type'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['user'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['asset'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['location'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['returning'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['notes'], 'type' => \PDO::PARAM_STR],
            ]
        );
    }

    public static function addUpload($file)
    {
        $mime_type = mime_content_type($file['tmp_name']);
        $hash = sha1_file($file['tmp_name']);
        if (preg_match('/^image\//', $mime_type)) {
            $max_geometry = ['width' => 440, 'height' => 880]; // Max width in Edit Asset dialog
            $thumbnail_geometry = ['width' => 5 * 16, 'height' => 3 * 16]; // max-width: 3rem; max-height: 3rem;

            $extension = 'jpg';
            $filename = $hash . '.' . $extension;
            $images_dir = Settings::get('uploads_path') .'/images';
            $file_path = $images_dir . '/' . $filename;
            $thumbnail_path = $images_dir . '/thumbs/' . $filename;

            $image = new \Imagick($file['tmp_name']);
            $image_geometry = $image->getImageGeometry();
            if (
                $image_geometry['width'] > $max_geometry['width']
                || $image_geometry['height'] > $max_geometry['height']
            ) {
                $width = min($image_geometry['width'], $max_geometry['width']);
                $height = min($image_geometry['height'], $max_geometry['height']);
                $image->resizeImage($width, $height, \imagick::FILTER_SINC, 1, true);
            }
            $image->writeImage($file_path);
            if (
                $image_geometry['width'] > $thumbnail_geometry['width']
                || $image_geometry['height'] > $thumbnail_geometry['height']
            ) {
                $width = min($image_geometry['width'], $thumbnail_geometry['width']);
                $height = min($image_geometry['height'], $thumbnail_geometry['height']);
                $image->resizeImage($width, $height, \imagick::FILTER_SINC, 1, true);
            }
            $image->writeImage($thumbnail_path);
            $image->clear();
            $image = 1;
        } else if (!in_array($mime_type, Settings::get('mime_types'))) {
            throw new \Exception('Files of type ' . $mime_type . ' are not allowed');
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $hash . '.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], Settings::get('uploads_path') . '/' . $filename)) {
                throw new \Exception('Failed to save upload: ' . $file['name']);
            }
            $image = 0;
        }
        static::executeStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'uploads` (
    `hash`,
    `name`,
    `extension`,
    `image`
  )
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE `name` = `name`, `extension` = `extension`
           ',
           [
               ['value' => $hash, 'type' => \PDO::PARAM_STR],
               ['value' => $file['name'], 'type' => \PDO::PARAM_STR],
               ['value' => $extension, 'type' => \PDO::PARAM_STR],
               ['value' => $image, 'type' => \PDO::PARAM_INT]
           ]
        );
        return $hash;
    }

    public static function addUser($user)
    {
        $user = DataModels\User::validate($user);
        return static::insertStatement(
            '
  INSERT INTO `' . Settings::get('table_prefix') . 'users` (
    `name`, `email`, `hash`, `role`
  )
  VALUES (?, ?, ?, ?)
            ',
            [
                ['value' => $user['name'], 'type' => \PDO::PARAM_STR],
                ['value' => $user['email'], 'type' => \PDO::PARAM_STR],
                ['value' => $user['hash'], 'type' => \PDO::PARAM_STR],
                ['value' => $user['role'], 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    public static function deleteAsset($id)
    {
        static::updateAttachments('asset', $id, []);
        $stmt = static::executeStatement(
            'DELETE FROM `' . Settings::get('table_prefix') . 'assets` WHERE `id` = ?',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
        if ($stmt->rowCount() != 1) {
            throw new \Exception('Error deleting asset');
        }
        static::deleteOrphanUploads();
    }

    public static function deleteAttribute($id)
    {
        $stmt = static::executeStatement(
            'DELETE FROM `' . Settings::get('table_prefix') . 'attributes` WHERE `id` = ?',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
        if ($stmt->rowCount() != 1) {
            throw new \Exception('Error deleting attribute');
        }
    }

    public static function deleteCategory($id)
    {
        static::deleteTreeNode('categories', $id);
    }

    public static function deleteLocation($id)
    {
        static::deleteTreeNode('locations', $id);
    }

    public static function deleteModel($id)
    {
        static::deleteModelImages($id);
        static::updateAttachments('model', $id, []);
        $stmt = static::executeStatement(
            'DELETE FROM `' . Settings::get('table_prefix') . 'models` WHERE `id` = ?',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
        if ($stmt->rowCount() != 1) {
            throw new \Exception('Error deleting asset model');
        }
        static::deleteOrphanUploads();
    }

    protected static function deleteOrphanUploads()
    {
        $stmt = static::executeStatement(
            '
  SELECT *
  FROM `'. Settings::get('table_prefix') . 'uploads`
  WHERE
    `hash` NOT IN (
      SELECT `image` AS `hash` FROM `'. Settings::get('table_prefix') . 'models`
      UNION
      SELECT `attachment` AS `hash` FROM `' . Settings::get('table_prefix') . 'asset_attachments`
      UNION
      SELECT `attachment` AS `hash` FROM `' . Settings::get('table_prefix') . 'model_attachments`
    )
            '
        );
        while ($row = $stmt->fetch()) {
            $filename = $row['hash'] . ($row['extension'] ? '.' . $row['extension'] : '');
            if ($row['image']) {
                $thumbnail = Settings::get('uploads_path') . '/images/thumbs/' . $filename;
                if (file_exists($thumbnail)) {
                    unlink($thumbnail);
                }
                $filename = 'images/' . $filename;
            }
            $filename = Settings::get('uploads_path') . '/' . $filename;
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
        static::executeStatement(
            '
  DELETE
  FROM `'. Settings::get('table_prefix') . 'uploads`
  WHERE
    `hash` NOT IN (
      SELECT `image` AS `hash` FROM `'. Settings::get('table_prefix') . 'models`
      UNION
      SELECT `attachment` AS `hash` FROM `' . Settings::get('table_prefix') . 'asset_attachments`
      UNION
      SELECT `attachment` AS `hash` FROM `' . Settings::get('table_prefix') . 'model_attachments`
    )
            '
        );
    }

    public static function deleteTransaction($id)
    {
        $stmt = static::executeStatement(
            'DELETE FROM `' . Settings::get('table_prefix') . 'transactions` WHERE `id` = ?',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
        if ($stmt->rowCount() != 1) {
            throw new \Exception('Error deleting transaction');
        }
    }

    public static function deleteUser($id)
    {
        $other_admins = static::executeStatement(
            'SELECT COUNT(*) FROM `' . Settings::get('table_prefix') . 'users` WHERE `role` = ? AND `id` != ?',
            [
                ['value' => DataModels\Role::ADMINISTRATOR, 'type' => \PDO::PARAM_INT],
                ['value' => $id, 'type' => \PDO::PARAM_INT]
            ]
        )->fetchColumn();
        if ($other_admins == 0) {
            throw new \Exception('Cannot delete the only administrator');
        }
        $stmt = static::executeStatement(
            'DELETE FROM `' . Settings::get('table_prefix') . 'users` WHERE `id` = ?',
            [['value' => $id, 'type' => \PDO::PARAM_INT]]
        );
        if ($stmt->rowCount() != 1) {
            throw new \Exception('Error deleting user');
        }
    }

    public static function editAsset($node) {
        $node = DataModels\Asset::validate($node);
        $stmt = static::executeStatement(
            '
  UPDATE `' . Settings::get('table_prefix') . 'assets`
  SET
    `model` = ?,
    `location` = ?,
    `serial` = ?
  WHERE `id` = ?
            ',
            [
                ['value' => $node['model'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['location'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['serial'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
        static::updateAttributes('asset', $node['id'], $node['attributes']);
        static::updateAttachments('asset', $node['id'], $node['attachments']);
    }

    public static function editAttribute($node)
    {
        $node = DataModels\Attribute::validate($node);
        static::executeStatement(
            'UPDATE `' . Settings::get('table_prefix') . 'attributes` SET `name` = ? WHERE `id` = ?',
            [
                ['value' => $node['name'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    public static function editCategory($node)
    {
        $node = DataModels\Category::validate($node);
        static::editTreeNode('categories', $node['id'], $node['name'], $node['parent']);
    }

    public static function editLocation($node)
    {
        $node = DataModels\Location::validate($node);
        static::editTreeNode('locations', $node['id'], $node['name'], $node['parent']);
    }

    public static function editManufacturer($node)
    {
        $node = DataModels\Manufacturer::validate($node);
        static::executeStatement(
            'UPDATE `' . Settings::get('table_prefix') . 'manufacturers` SET `name` = ? WHERE `id` = ?',
            [
                ['value' => $node['name'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    public static function editModel($node) {
        $node = DataModels\Model::validate($node);
        $image = static::executeStatement(
            'SELECT `image` FROM `'. Settings::get('table_prefix') . 'models` WHERE `id` = ?',
            [['value' => $node['id'], 'type' => \PDO::PARAM_INT]]
        )->fetchColumn();
        if ($image != $node['image']) {
            static::deleteModelImages($node['id']);
        }
        $stmt = static::executeStatement(
            '
  UPDATE `' . Settings::get('table_prefix') . 'models`
  SET
    `description` = ?,
    `manufacturer` = ?,
    `model` = ?,
    `series` = ?,
    `url` = ?,
    `image` = ?
  WHERE `id` = ?
            ',
            [
                ['value' => $node['description'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['manufacturer'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['model'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['series'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['url'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['image'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
        static::updateAttributes('model', $node['id'], $node['attributes']);
        static::updateAttachments('model', $node['id'], $node['attachments']);
        static::updateModelCategories($node['id'], $node['categories']);
    }

    public static function editTransaction($node) {
        $node = DataModels\Transaction::validate($node);
        static::executeStatement(
            '
  UPDATE `' . Settings::get('table_prefix') . 'transactions`
  SET
    `user` = ?,
    `location` = ?,
    `returning` = ?,
    `notes` = ?,
    `ts` = `ts`
  WHERE `id` = ?
            ',
            [
                ['value' => $node['user'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['location'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['returning'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['notes'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    public static function editUser($node)
    {
        $node = DataModels\User::validate($node);
        static::executeStatement(
            '
  UPDATE `' . Settings::get('table_prefix') . 'users`
  SET
    `name` = ?, `email` = ?, `hash` = ?, `role` = ?
  WHERE `id` = ?
            ',
            [
                ['value' => $node['name'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['email'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['hash'], 'type' => \PDO::PARAM_STR],
                ['value' => $node['role'], 'type' => \PDO::PARAM_INT],
                ['value' => $node['id'], 'type' => \PDO::PARAM_INT]
            ]
        );
    }

    protected static function updateAttachments($table_prefix, $id, $attachments)
    {
        $table = '`' . Settings::get('table_prefix') . $table_prefix . '_attachments`';

        $values = [['value' => $id, 'type' => \PDO::PARAM_INT]];
        foreach ($attachments as $attachment) {
            $values[] = ['value' => $id, 'type' => \PDO::PARAM_INT];
            $values[] = ['value' => $attachment, 'type' => \PDO::PARAM_STR];
        }

        $stmt = static::executeStatement(
            '
  /* LOCK TABLES ' . $table . ' WRITE; */
  DELETE FROM ' . $table . ' WHERE `' . $table_prefix . '` = ?;
  ' . (count($attachments) ? 'INSERT INTO ' . $table . ' (`' . $table_prefix . '`, `attachment`)
  VALUES
    ' . implode(",\n", array_fill(0, count($attachments), '(?, ?)')) : '') . ';
  /* UNLOCK TABLES; */
            ',
            $values
        );
        $stmt->closeCursor(); // Otherwise unbuffered query error
        static::deleteOrphanUploads();
    }

    protected static function updateAttributes($table_prefix, $id, $attributes)
    {
        $table = '`' . Settings::get('table_prefix') . $table_prefix . '_attributes`';

        $values = [['value' => $id, 'type' => \PDO::PARAM_INT]];
        foreach ($attributes as $attribute => $value) {
            $values[] = ['value' => $id, 'type' => \PDO::PARAM_INT];
            $values[] = ['value' => $attribute, 'type' => \PDO::PARAM_INT];
            $values[] = ['value' => $value, 'type' => \PDO::PARAM_STR];
        }

        $stmt = static::executeStatement(
            '
  /* LOCK TABLES ' . $table . ' WRITE; */
  DELETE FROM ' . $table . ' WHERE `' . $table_prefix . '` = ?;
  ' . (count($attributes) ? 'INSERT INTO ' . $table . ' (`' . $table_prefix . '`, `attribute`, `value`)
  VALUES
    ' . implode(",\n", array_fill(0, count($attributes), '(?, ?, ?)')) : '') . ';
  /* UNLOCK TABLES; */
            ',
            $values
        );
        $stmt->closeCursor(); // Otherwise unbuffered query error
        static::deleteOrphanUploads();
    }

    protected static function updateModelCategories($model, $categories)
    {
        $table = '`' . Settings::get('table_prefix') . 'model_categories`';

        // Do not add parents if children are included
        if (count($categories) > 1) {
            $nodes = static::executeStatement(
                '
SELECT *
FROM `' . Settings::get('table_prefix') . 'categories`
WHERE `id` IN (' . implode(', ', array_fill(0, count($categories), '?')) . ')
                ',
                array_map(function($id) { return ['value' => $id, 'type' => \PDO::PARAM_INT]; }, $categories)
            )->fetchAll();
            foreach ($nodes as $node) {
                foreach ($nodes as $parent) {
                    if ($node['left'] > $parent['left'] && $node['left'] < $parent['right']) {
                        $categories = array_diff($categories, [$parent['id']]);
                    }
                }
            }
        }

        $values = [['value' => $model, 'type' => \PDO::PARAM_INT]];
        foreach ($categories as $category) {
            $values[] = ['value' => $model, 'type' => \PDO::PARAM_INT];
            $values[] = ['value' => $category, 'type' => \PDO::PARAM_INT];
        }

        $stmt = static::executeStatement(
            '
  LOCK TABLES ' . $table . ' WRITE;
  DELETE FROM ' . $table . ' WHERE `model` = ?;
  ' . (count($categories) ? 'INSERT INTO ' . $table . ' (`model`, `category`)
  VALUES
    ' . implode(",\n", array_fill(0, count($categories), '(?, ?)')) : '') . ';
  UNLOCK TABLES;
            ',
            $values
        );
    }

}
