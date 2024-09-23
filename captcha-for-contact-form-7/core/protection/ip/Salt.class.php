<?php

namespace f12_cf7_captcha\core\protection\ip;

use DateTime;
use Exception;
use f12_cf7_captcha\core\wpdb;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Salt
 *
 * @package forge12\contactform7
 */
class Salt
{
    /**
     * The unique ID
     *
     * @var int
     */
    private $id = 0;
    /**
     * The Salt
     *
     * @var string
     */
    private $salt = '';

    /**
     * The datetime whenever the captcha code has been created
     *
     * @var string
     */
    private $createtime = '';

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
     * Sets the parameters of the object.
     *
     * @param array $params An associative array containing the parameters
     *                      to be set. The keys correspond to the names of
     *                      the properties of the object.
     *                      The values are the new values to be assigned to
     *                      the corresponding properties.
     *
     * @return void
     */
    public function set_params(array $params): void
    {
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                if ($key === 'salt') {
                    $value = base64_decode($value);
                }
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Remove records older than the specified period from the database table.
     *
     * @param string $period The period indicating the age of the records to be removed.
     *
     * @return int The number of records deleted.
     */
    public function remove_older_than(string $period): int
    {
        global $wpdb;

        if (!$wpdb) {
            return 0;
        }

        $timestamp = strtotime($period);

        $wp_table_name = $this->get_table_name();

        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $dt_formatted = $dt->format('Y-m-d H:i:s');

        return $wpdb->query(sprintf('DELETE FROM %s WHERE createtime < "%s"', $wp_table_name, $dt_formatted));
    }

    /**
     * @deprecated
     */
    public static function removeOlderThan($period)
    {
        $Salt = new Salt();
        return $Salt->remove_older_than($period);
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
     * @deprecated
     */
    public static function resetTable()
    {
        $Salt = new Salt();
        return $Salt->reset_table();
    }

    /**
     * Retrieves the count of entries from the specified table.
     *
     * @param int $validated The validation parameter.
     *
     * @return int The count of entries.
     */
    public function get_count(int $validated = -1): int
    {
        global $wpdb;

        if (!$wpdb) {
            return 0;
        }

        $wp_table_name = $this->get_table_name();

        $prepare_stmt = sprintf('SELECT count(*) AS entries FROM %s', $wp_table_name);

        $results = $wpdb->get_results($prepare_stmt);

        if (is_array($results) && isset($results[0])) {
            return $results[0]->entries;
        }
        return 0;
    }

    /**
     * @param $validated
     *
     * @return int
     * @deprecated
     */
    public static function getCount($validated = -1)
    {
        $Salt = new Salt();
        return $Salt->get_count($validated);
    }

    /**
     * Create a new table in the WordPress database for storing salts.
     *
     * @return void
     */
    public function create_table(): void
    {
        $wp_table_name = $this->get_table_name();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = sprintf("CREATE TABLE %s (
                id int(11) NOT NULL auto_increment, 
                salt varchar(255) NOT NULL,
                createtime varchar(255) DEFAULT '', 
                PRIMARY KEY  (id)
            )", $wp_table_name);
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
        $Salt = new Salt();
        $Salt->create_table();
    }

    /**
     * Deletes the table associated with the current object.
     *
     * @return void
     */
    public function delete_table(): void
    {
        global $wpdb;

        $wp_table_name = $this->get_table_name();

        $wpdb->query("DROP TABLE IF EXISTS " . $wp_table_name);

        # clear cron
        wp_clear_scheduled_hook('weeklyIPClear');
    }

    /**
     * @deprecated
     */
    public static function deleteTable()
    {
        $Salt = new Salt();
        $Salt->delete_table();
    }

    /**
     * Return the Table Name
     *
     * @return string
     * @deprecated
     */
    public static function getTableName()
    {
        $Salt = new Salt();
        return $Salt->get_table_name();
    }

    /**
     * Retrieves the name of the table prefixed with the WordPress database table prefix.
     *
     * @return string The name of the table prefixed with the WordPress database table prefix.
     * @global wpdb $wpdb WordPress database access abstraction class instance.
     */
    public function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'f12_cf7_salt';
    }

    /**
     * Get the ID of the current object.
     *
     * @return int The ID of the current object.
     */
    public function get_id(): int
    {
        return $this->id;
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
     * Set the ID of the object.
     *
     * @param int $id The ID to set. Must be an integer.
     *
     * @return void
     */
    private function set_id(int $id)
    {
        $this->id = $id;
    }

    /**
     * Get the salt value for the object
     *
     * @return string The salt value
     */
    private function get_salt(): string
    {
        return $this->salt;
    }

    /**
     * Get the creation time of the object.
     * If the creation time is empty, it will generate a new DateTime object and set the creation time to the current
     * date and time.
     *
     * @return string The creation time formatted as 'Y-m-d H:i:s'
     */
    public function get_create_time(): string
    {
        if (empty($this->createtime)) {
            $dt = new DateTime();
            $this->createtime = $dt->format('Y-m-d H:i:s');
        }
        return $this->createtime;
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
     * Set the creation time for the object to current date and time
     *
     * @return void
     */
    public function set_create_time(): void
    {
        $dt = new DateTime();
        $this->createtime = $dt->format('Y-m-d H:i:s');
    }

    /**
     * Create a new Salt object.
     *
     * @return Salt The newly created Salt object.
     * @throws RuntimeException If the Salt could not be created.
     * @throws Exception
     */
    private function create_salt(): Salt
    {
        // Create a new salt if there is no salt
        $Salt = new Salt([
            'salt' => $this->generate_salt()
        ]);
        $Salt->save();

        if ($Salt->get_id() === 0) {
            throw new RuntimeException("Salt could not be created. Please check the Database");
        }

        return $Salt;
    }

    /**
     * Retrieves the last record from the database table.
     *
     * @return Salt|null The last record retrieved from the database table, or null if the global $wpdb object is not
     *                   available.
     * @throws Exception
     */
    public function get_last(): ?Salt
    {
        global $wpdb;

        if (!$wpdb) {
            throw new \RuntimeException('WPDB not found');
        }

        $table = $this->get_table_name();

        $prepare_stmt = sprintf("SELECT * FROM %s ORDER BY createtime DESC LIMIT 1", $table);

        $results = $wpdb->get_results($prepare_stmt, ARRAY_A);

        $Salt = null;

        if (is_array($results) && isset($results[0])) {
            $Salt = new Salt($results[0]);
        }

        /*
         * Create a salt if none exists yet
         */
        if (null === $Salt) {
            // Try to create a new salt
            $Salt = $this->create_salt();
        }

        /*
         * Create a new salt if the existing one is older than 30 days
         */
        if ($this->is_older_than($Salt->get_create_time())) {
            $Salt = $this->create_salt();
        }

        return $Salt;
    }

    /**
     * Return the first element found by the given id.
     *
     * @param $id
     *
     * @return Salt|null
     * @throws Exception
     * @deprecated
     */
    public static function getLast()
    {
        $Salt = new Salt();
        return $Salt->get_last();
    }

    /**
     * Determines if a given date is older than a specified number of days.
     *
     * @param string $date The date to check. Format: "Y-m-d" or "Y-m-d H:i:s".
     * @param string $days The number of days to compare against. Format: "+/-n day(s)", where n is a positive or
     *                     negative integer.
     *
     * @return bool Returns true if the date is older than the specified number of days, otherwise returns false.
     */
    public function is_older_than(string $date, string $days = '+30 Days'): bool
    {
        $d1 = new DateTime($date);
        $d1->modify($days);

        $d2 = new DateTime();

        return $d2 > $d1;
    }

    /**
     * Generates a random salt.
     *
     * @return string The randomly generated salt as a string.
     * @throws Exception
     */
    public function generate_salt(): string
    {
        return random_bytes(512);
    }

    /**
     * @param $value
     *
     * @return string
     * @deprecated
     */
    public function getSalted($value)
    {
        return $this->get_salted($value);
    }

    /**
     * Returns a salted hash of the given value using PBKDF2 algorithm with SHA512 hashing.
     *
     * @param string $value The value to be hashed and salted.
     *
     * @return string The salted hash of the given value.
     */
    public function get_salted(string $value): string
    {
        return hash_pbkdf2('sha512', $value, $this->salt, 10);
    }

    /**
     * Return the first element found by the given id.
     *
     * @param $id
     *
     * @deprecated
     */
    public static function get($offset = 1)
    {
        $Salt = new Salt();
        return $Salt->get_one_salt_by_offset($offset);
    }

    /**
     * Retrieves a single salt object by its offset.
     * This will return the previous salt. We only store 2 salts - the new one and the previous one. So therefor
     * it is a little bit special
     *
     * @param int $offset The offset of the salt object to retrieve.
     *
     * @return Salt|null The salt object found at the specified offset, or null if not found.
     *
     */
    public function get_one_salt_by_offset(int $offset): ?Salt
    {
        global $wpdb;

        if (!$wpdb) {
            return null;
        }

        $table = $this->get_table_name();

        $results = $wpdb->get_results(sprintf("SELECT * FROM %s ORDER BY createtime DESC LIMIT 1 OFFSET %d", $table, $offset), ARRAY_A);

        $Salt = null;

        if (null !== $results && isset($results[0])) {
            $Salt = new Salt($results[0]);
        }
        return $Salt;
    }

    /**
     * Clean up the database by deleting old records.
     *
     * This method deletes records from the specified table that have a creation
     * time older than three weeks ago. It uses the global $wpdb object to execute
     * the delete query.
     *
     * @return void
     */
    private function maybe_clean(): void
    {
        global $wpdb;

        if (!$wpdb) {
            throw new \RuntimeException('WPDB not found');
        }

        $table = $this->get_table_name();

        /*
         * Date Interval: 3 Weeks
         */
        $date_time = new DateTime('-3 Weeks');

        $date_time_formatted = $date_time->format('Y-m-d H:i:s');

        /*
         * Run the query to delete all entries older than 3 weeks
         */
        $wpdb->query(sprintf('DELETE FROM %s WHERE createtime < "%s"', $table, $date_time_formatted));
    }

    /**
     * Saves the object to the database.
     *
     * @return int|null The result of the save operation, or null if $wpdb is not available.
     * @global wpdb $wpdb The WordPress database object.
     *
     */
    public function save(): int
    {
        global $wpdb;

        if (null === $wpdb) {
            throw new RuntimeException('WPDB not found');
        }

        if ($this->id !== 0) {
            return 0;
        }

        $table = $this->get_table_name();

        $result = $wpdb->insert($table, array(
            'salt' => base64_encode($this->salt),
            'createtime' => $this->get_create_time()
        ));

        if ($result === false) {
            throw new RuntimeException('Database error occurred. Reactivate the plugin to create missing tables.');
        }

        $this->set_id($wpdb->insert_id);

        // clean older than 3 weeks
        $this->maybe_clean();

        return $result;

    }
}