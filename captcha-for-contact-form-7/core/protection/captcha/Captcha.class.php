<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\core\UserData;
use f12_cf7_captcha\core\wpdb;
use IPAddress;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Captcha
 * Model
 *
 * @package forge12\contactform7
 */
class Captcha
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
     * The code validated against
     *
     * @var string
     */
    private $code = '';
    /**
     * Flag if the code has been validated already
     *
     * @var int
     */
    private $validated = 0;
    /**
     * The datetime whenever the captcha code has been created
     *
     * @var string
     */
    private $createtime = '';
    /**
     * The datetime whenever the captcha code has been updated
     *
     * @var string
     */
    private $updatetime = '';

    private $ip_address = '';

    /**
     * Create a new Captcha Object
     *
     * @param string $ip_address
     * @param array  $params
     */
    public function __construct(string $ip_address, $params = array())
    {
        $this->ip_address = $ip_address;

        $this->set_params($params);
    }

    /**
     * Sets the parameters of the object.
     *
     * @param array $params An associative array where the keys represent the parameter names and the values
     *                      represent the new values for the corresponding parameters.
     *
     * @return void
     */
    private function set_params(array $params): void
    {
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Retrieves the count of entries from the specified table.
     *
     * @param int $validated (optional) Optional argument to filter the count based on validation status.
     *                       If -1 (default), it returns the count of all entries.
     *                       If 0, it returns the count of entries with validation status as 0.
     *                       If 1, it returns the count of entries with validation status as 1.
     *
     * @return int The count of entries from the specified table. Returns 0 if the count cannot be retrieved or if
     *             the result is empty.
     */
    public function get_count(int $validated = -1): int
    {
        global $wpdb;

        if (!$wpdb) {
            return 0;
        }

        $wp_table_name = $this->get_table_name();
        if ($validated == -1) {
            $results = $wpdb->get_results('SELECT count(*) AS entries FROM ' . $wp_table_name);
        } else if ($validated == 0) {
            $results = $wpdb->get_results('SELECT count(*) AS entries FROM ' . $wp_table_name . ' WHERE validated=0');
        } else {
            $results = $wpdb->get_results('SELECT count(*) AS entries FROM ' . $wp_table_name . ' WHERE validated=1');
        }

        if (is_array($results) && isset($results[0])) {
            return $results[0]->entries;
        }

        return 0;
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
        $Captcha = new Captcha('');
        $Captcha->create_table();
    }

    /**
     * Creates a new table in the WordPress database using the specified table name and schema.
     *
     * @return void
     */
    public function create_table(): void
    {
        $wp_table_name = $this->get_table_name();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE " . $wp_table_name . " (
                id int(11) NOT NULL auto_increment, 
                hash varchar(255) NOT NULL, 
                code varchar(255) NOT NULL, 
                validated int(1) DEFAULT 0,
                createtime varchar(255) DEFAULT '', 
                updatetime varchar(255) DEFAULT '',
                PRIMARY KEY  (id)
            )";

        dbDelta($sql);
    }

    /**
     * @return void
     * @deprecated
     */
    public static function deleteTable()
    {
        $Captcha = new Captcha('');
        $Captcha->delete_table();
    }

    /**
     * Deletes the table associated with the current object from the WordPress database.
     *
     * @return void
     * @global wpdb $wpdb The WordPress database object.
     *
     */
    public function delete_table(): void
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table_name = $this->get_table_name();

        $wpdb->query(sprintf("DROP TABLE IF EXISTS %s", $table_name));

        # clear cron
        wp_clear_scheduled_hook('dailyCaptchaClear');
    }

    /**
     * Resets the table by deleting all records.
     *
     * @return int The number of rows deleted from the table.
     *
     * @throws RuntimeException If $wpdb is not defined.
     */
    public function reset_table(): int
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table_name = $this->get_table_name();

        return (int)$wpdb->query(sprintf("DELETE FROM %s", $table_name));
    }

    /**
     * Deletes rows from the database table where the 'validated' column matches the given value.
     *
     * @param int $validated (Optional) The value to match against the 'validated' column. Defaults to 1.
     *
     * @return int The number of rows affected by the deletion operation.
     *
     * @throws RuntimeException When global $wpdb is not defined.
     */
    public function delete_by_validate_status(int $validated = 1)
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table_name = $this->get_table_name();

        return (int)$wpdb->query(sprintf('DELETE FROM %s WHERE validated="%d"', $table_name, $validated));
    }

    /**
     * Deletes records from the database that are older than the specified creation time.
     *
     * @param string $create_time The creation time to compare against.
     *
     * @return int The number of records deleted.
     *
     * @throws RuntimeException If the WPDB global variable is not defined.
     */
    public function delete_older_than(string $create_time): int
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table_name = $this->get_table_name();

        return (int)$wpdb->query(sprintf('DELETE FROM %s WHERE createtime < "%s"', $table_name, $create_time));
    }

    /**
     * Retrieves the table name for storing contact form 7 captcha data.
     *
     * @return string The full table name including the WordPress database prefix.
     */
    public function get_table_name(): string
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        return $wpdb->prefix . 'f12_cf7_captcha';
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
     * Retrieves the ID of the object.
     *
     * @return int The ID of the object.
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
     * Set the ID for the object.
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
     * Returns the hash value of the current object.
     *
     * If the hash value is not already set, it will be generated using the `generate_hash()` method.
     *
     * @return string The hash value.
     */
    public function get_hash(): string
    {
        if (empty($this->hash)) {
            $this->hash = $this->generate_hash($this->ip_address);
        }

        return $this->hash;
    }

    /**
     * Generates a hash using the current timestamp and the user's IP address.
     *
     * @return string The generated hash string.
     */
    private function generate_hash(string $ip_address): string
    {
        if (empty($ip_address)) {
            return '';
        }

        return \password_hash(time() . $ip_address, PASSWORD_DEFAULT);
    }

    /**
     * Check if the hash is valid. Only if the ip adress could be determined.
     * If do not store this item in the db.
     *
     * @deprecated
     */
    private function isValidHash()
    {
        return $this->is_valid_hash();
    }

    /**
     * Checks if the hash value is valid.
     *
     * @return bool Returns true if the hash value is not empty, otherwise returns false.
     */
    private function is_valid_hash(): bool
    {
        return !empty($this->hash);
    }

    /**
     * @return string
     * @deprecated
     */
    public function getCode()
    {
        return $this->get_code();
    }

    /**
     * Retrieves the value of the code property.
     *
     * @return string The value of the code property.
     */
    public function get_code(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     *
     * @deprecated
     */
    public function setCode($code)
    {
        $this->set_code($code);
    }

    /**
     * Set the code for the object.
     *
     * @param string $code The code to be set.
     *
     * @return void
     */
    public function set_code(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return int
     * @deprecated
     */
    public function getValidated()
    {
        return $this->get_validated();
    }

    /**
     * Returns the validated value.
     *
     * @return int The validated value.
     */
    public function get_validated(): int
    {
        return $this->validated;
    }

    /**
     * @param int $validated
     *
     * @deprecated
     */
    public function setValidated($validated)
    {
        $this->set_validated($validated);
    }

    /**
     * Sets the validated property of the object.
     *
     * @param int $validated The new value for the validated property.
     *
     * @return void
     */
    public function set_validated(int $validated)
    {
        $this->validated = $validated;
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
     * Returns the creation time of the object in string format.
     *
     * If the `createtime` property is not set or is empty, it will be initialized with the current date and time.
     *
     * @return string The creation time in the format 'Y-m-d H:i:s'.
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
     * @param string $createtime
     *
     * @deprecated
     * Update the createtime with the current timestamp
     */
    public function setCreatetime()
    {
        $this->set_create_time();
    }

    /**
     * Sets the createtime value of the object.
     *
     * This method sets the value of the createtime property to the current date and time in the format 'Y-m-d
     * H:i:s'.
     *
     * @return void
     */
    public function set_create_time(): void
    {
        $dt = new \DateTime();
        $this->createtime = $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     */
    public function getUpdatetime()
    {
        $this->get_update_time();
    }

    /**
     * Retrieves the update time of the object.
     *
     * If the update time is not set, it will be initialized with the current date and time.
     *
     * @return string The update time of the object in the format 'Y-m-d H:i:s'.
     */
    public function get_update_time(): string
    {
        if (empty($this->updatetime)) {
            $dt = new \DateTime();
            $this->updatetime = $dt->format('Y-m-d H:i:s');
        }

        return $this->updatetime;
    }

    /**
     * Updates the updatetime with the current timestamp
     *
     * @deprecated
     */
    public function setUpdatetime()
    {
        $this->set_update_time();
    }

    /**
     * Sets the update time of the object to the current date and time.
     *
     * @return void
     */
    public function set_update_time(): void
    {
        $dt = new \DateTime();
        $this->updatetime = $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return bool
     * @deprecated
     * Check if this is an update or a new object
     */
    private function isUpdate()
    {
        return $this->is_update();
    }

    /**
     * Checks if the object represents an update.
     *
     * It checks if the object has a valid hash and the ID is not equal to 0.
     *
     * @return bool Returns true if the object represents an update, otherwise returns false.
     */
    private function is_update(): bool
    {
        if ($this->is_valid_hash() && $this->id != 0) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves a Captcha object by its ID from the database.
     *
     * @param int $id The ID of the Captcha to retrieve.
     *
     * @return Captcha|null The Captcha object corresponding to the provided ID, or null if the global $wpdb object
     *                      is not available or no record is found.
     */
    public function get_by_id(int $id): ?Captcha
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table = $this->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $table . " WHERE id=%d", $id), ARRAY_A);

        if (null != $results) {
            $results = new Captcha($this->ip_address, $results[0]);
        }

        return $results;
    }

    /**
     * @param $hash
     *
     * @return null|Captcha
     * @deprecated
     * Return the first element found by the given hash.
     */
    public static function getByHash($hash)
    {
        $Captcha = new Captcha();

        return $Captcha->get_by_hash($hash);
    }

    /**
     * Retrieves a Captcha object by its hash.
     *
     * @param string $hash The hash value of the Captcha.
     *
     * @return Captcha|null The Captcha object matching the provided hash, or null if not found.
     */
    public function get_by_hash(string $hash): ?Captcha
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table = $this->get_table_name();

        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $table . " WHERE hash=%s", $hash), ARRAY_A);

        if (isset($results[0])) {
            $results = new Captcha($this->ip_address, $results[0]);
        } else {
            $results = null;
        }

        return $results;
    }

    /**
     * Save the object to the database
     */
    public function save()
    {
        global $wpdb;

        if (!$wpdb) {
            throw new RuntimeException('WPDB not defined.');
        }

        $table = $this->get_table_name();

        if ($this->is_update()) {
            return $wpdb->update($table, array(
                'hash' => $this->get_hash(),
                'createtime' => $this->get_create_time(),
                'updatetime' => $this->get_update_time(),
                'code' => $this->get_code(),
                'validated' => $this->get_validated(),
            ), array(
                'id' => $this->get_id()
            ));
        } else {
            $result = $wpdb->insert($table, array(
                'hash' => $this->get_hash(),
                'code' => $this->get_code(),
                'updatetime' => $this->get_update_time(),
                'createtime' => $this->get_create_time(),
                'validated' => $this->get_validated()
            ));

            /*
             * Update the ID
             */
            if ($result) {
                $this->set_id($wpdb->insert_id);
            }

            /*
             * Return the result
             *
             */

            return $result;
        }
    }
}