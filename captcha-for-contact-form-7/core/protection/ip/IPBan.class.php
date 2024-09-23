<?php

namespace f12_cf7_captcha\core\protection\ip;

use f12_cf7_captcha\core\wpdb;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

require_once('IPBanCleaner.class.php');

/**
 * Class IPBan
 *
 * @package forge12\contactform7
 */
class IPBan
{
    /**
     * The unique ID
     *
     * @var int
     */
    private $id = 0;
    /**
     * The identifier used in the contact form
     *
     * @var string
     */
    private $hash = '';
    /**
     * The datetime whenever the captcha code has been created
     *
     * @var string
     */
    private $createtime = '';
    /**
     * The datetime until the user is blocked for submitting data
     *
     * @var string, e.g.: 2024-05-27 22:13:00
     */
    private $blockedtime = '';

    /**
     * Create a new Captcha Object
     *
     * @param $object
     */
    public function __construct($params = array())
    {
        $this->set_params($params);
    }

    /**
     * Sets the params of the object.
     *
     * @param array $params The params to set.
     *
     * @return void
     */
    public function set_params(array $params): void
    {
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Get the count of entries from the database table.
     *
     * @param string $hash          (optional) The hash value to filter the entries.
     * @param string $previous_hash (optional) The previous hash value to filter the entries.
     *
     * @return int The count of entries.
     * @throws \RuntimeException If WPDB is not defined.
     */
    public function get_count(string $hash = '', string $previous_hash = ''): int
    {
        global $wpdb;

        if (!$wpdb) {
            throw new \RuntimeException('WPDB not defined');
        }

        $table_name = $this->get_table_name();

        if (!empty($hash) && !empty($previous_hash)) {
            $dt = new \DateTime();
            $block_time = $dt->format('Y-m-d H:i:s');

            $prepare_stmt = sprintf('SELECT count(*) AS entries FROM %s WHERE (hash="%s" OR hash="%s") AND blockedtime > "%s"', $table_name, $hash, $previous_hash, $block_time);

            $results = $wpdb->get_results($prepare_stmt);
        } else {

            $prepare_stmt = sprintf('SELECT count(*) AS entries FROM %s', $table_name);
            $results = $wpdb->get_results($prepare_stmt);
        }

        if (is_array($results) && isset($results[0])) {
            return $results[0]->entries;
        }
        return 0;
    }

    /**
     * @param $hash
     * @param $hashPrevious
     *
     * @return int
     * @deprecated
     */
    public static function getCount($hash = '', $hashPrevious = '')
    {
        $IP_Ban = new IPBan();
        return $IP_Ban->get_count($hash, $hashPrevious);
    }

    /**
     * Creates a table in the database with the provided table name.
     *
     * @return void
     */
    public function create_table()
    {
        $table_name = $this->get_table_name();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = sprintf("CREATE TABLE %s (
                id int(11) NOT NULL auto_increment, 
                hash varchar(255) NOT NULL,
                createtime varchar(255) DEFAULT '',
                blockedtime varchar(255) DEFAULT '',
                PRIMARY KEY  (id)
            )", $table_name);

        dbDelta($sql);
    }

    /**
     * Create the database which saves the captcha codes
     * for the validation to be wordpress conform
     *
     * @return void
     * @deprecated
     */
    public static function createTable()
    {
        $IP_Ban = new IPBan();
        $IP_Ban->create_table();
    }

    /**
     * Reset the table by deleting all records.
     *
     * @return bool True if the table was reset successfully; otherwise, false.
     * @global wpdb $wpdb The WordPress database object.
     *
     */
    public function reset_table(): bool
    {
        global $wpdb;

        if (!$wpdb) {
            return 0;
        }

        $wp_table_name = $this->get_table_name();

        return $wpdb->query(sprintf('DELETE FROM %s', $wp_table_name));
    }


    /**
     * Deletes the table associated with the current object.
     *
     * Executes a SQL query to drop the table from the database if it exists.
     * Also, clears the scheduled cron job for 'weeklyIPClear'.
     *
     * @return void
     * @global wpdb $wpdb WordPress database object.
     */
    public function delete_table()
    {
        global $wpdb;

        $table_name = $this->get_table_name();

        $prepare_stmt = sprintf("DROP TABLE IF EXISTS %s", $table_name);
        $wpdb->query($prepare_stmt);

        # clear cron
        wp_clear_scheduled_hook('weeklyIPClear');
    }

    /**
     * Deletes records older than the specified creation time.
     *
     * @param string $create_time The creation time to compare against.
     *
     * @return int The number of deleted records.
     * @throws RuntimeException if WPDB is not defined.
     */
    public function delete_older_than(string $create_time): int
    {
        global $wpdb;

        if (null === $wpdb) {
            throw new RuntimeException('WPDB not defined');
        }

        $table = $this->get_table_name();

        return $wpdb->query(sprintf('DELETE FROM %s WHERE blockedtime < "%s"', $table, $create_time));
    }

    /**
     * @deprecated
     */
    public static function deleteTable()
    {
        (new IPBan())->delete_table();
    }

    /**
     * Return the Table Name
     *
     * @return string
     * @deprecated
     */
    public static function getTableName()
    {
        $IP_Ban = new IPBan();
        return $IP_Ban->get_table_name();
    }

    /**
     * Retrieves the table name for storing banned IP addresses.
     *
     * @return string The table name prefixed with the WordPress database prefix.
     * @throws \RuntimeException If WPDB is not found.
     */
    public function get_table_name(): string
    {
        global $wpdb;

        if (null === $wpdb) {
            throw new \RuntimeException('WPDB not found');
        }

        return $wpdb->prefix . 'f12_cf7_ip_ban';
    }

    /**
     * @return int
     * @deprecated
     */
    public function getId()
    {
        return $this->get_id();
    }

    /**
     * Retrieves the id of the current object.
     *
     * @return int The id of the object.
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @deprecated
     */
    private function setId($id)
    {
        $this->set_id($id);
    }

    /**
     * Set the ID of the object.
     *
     * @param int $id The ID to set.
     *
     * @return void
     */
    private function set_id(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     * @deprecated
     */
    public function getHash()
    {
        return $this->get_hash();
    }

    /**
     * Retrieves the hash value.
     *
     * @return string The hash value.
     */
    public function get_hash(): string
    {
        return $this->hash;
    }

    /**
     * @return string
     * @deprecated
     */
    public function getBlockedtime()
    {
        return $this->get_blocked_time();
    }

    /**
     * Retrieves the blocked time value.
     *
     * This method returns the blocked time value. If the blocked time value is empty, it sets the blocked time value
     * to the current datetime in 'Y-m-d H:i:s' format.
     *
     * @return string The blocked time value in 'Y-m-d H:i:s' format.
     * @throws \Exception
     */
    public function get_blocked_time(): string
    {
        if (empty($this->blockedtime)) {
            $this->set_blocked_time(3600);
        }
        return $this->blockedtime;
    }

    /**
     * @param string $seconds
     *
     * @throws \Exception
     * @deprecated
     */
    public function setBlockedtime($seconds)
    {
        $this->set_blocked_time($seconds);
    }

    /**
     * Sets the blocked time.
     *
     * @param string $seconds The number of seconds to block.
     *
     * @return void
     * @throws \Exception
     */
    public function set_blocked_time(string $seconds): void
    {
        $dt = new \DateTime('+' . $seconds . ' seconds');
        $this->blockedtime = $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     * @deprecated
     */
    public function getCreatetime()
    {
        return $this->get_create_time();
    }

    /**
     * Retrieves the create time value.
     *
     * If the create time value is empty, a new DateTime object is created and the current date and time
     * is formatted according to the 'Y-m-d H:i:s' format and stored in the $createtime property.
     * The $createtime property is then returned.
     *
     * @return string The create time value in 'Y-m-d H:i:s' format.
     */
    public function get_create_time(): string
    {
        if (empty($this->createtime)) {
            $dt = new \DateTime();
            $this->createtime = $dt->format('Y-m-d H:i:s');
        }
        return $this->createtime;
    }

    /**
     * Update the createtime with the current timestamp
     *
     * @param string $createtime
     *
     * @deprecated
     */
    public function setCreatetime()
    {
        $this->set_create_time();
    }

    /**
     * Sets the create time.
     *
     * @return void
     */
    public function set_create_time(): void
    {
        $dt = new \DateTime();
        $this->createtime = $dt->format('Y-m-d H:i:s');
    }

    /**
     * Saves the current instance to the database.
     *
     * @return int The result of the save operation. Returns 0 if the instance already has an identifier (id) set.
     *             Returns 1 if the save operation was successful.
     *
     * @throws \RuntimeException If WPDB is not defined.
     */
    public function save(): int
    {
        global $wpdb;

        if (!$wpdb) {
            throw new \RuntimeException('WPDB not defined');
        }

        $table = $this->get_table_name();

        if ($this->id !== 0) {
            return 0;
        }

        $result = $wpdb->insert($table, array(
            'hash' => $this->get_hash(),
            'createtime' => $this->get_create_time(),
            'blockedtime' => $this->get_blocked_time()
        ));

        $this->id = $wpdb->insert_id;

        if ($result === false) {
            throw new RuntimeException('Database error occurred. Reactivate the plugin to create missing tables.');
        }

        $this->id = $wpdb->insert_id;

        return $result;
    }
}