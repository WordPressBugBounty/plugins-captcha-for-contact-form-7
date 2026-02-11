<?php

namespace f12_cf7_captcha\core\protection\captcha;

use f12_cf7_captcha\CF7Captcha;
use f12_cf7_captcha\core\BaseModul;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CaptchaPool
 *
 * Manages a pool of pre-generated captcha images for improved performance.
 * Instead of generating captcha images on-demand during form load (which takes ~50-100ms),
 * this class pre-generates a pool of captchas via cron jobs.
 *
 * @package forge12\contactform7
 */
class CaptchaPool extends BaseModul {
	/**
	 * Option name for storing pool data.
	 */
	private const POOL_OPTION = 'f12_captcha_image_pool';

	/**
	 * Maximum number of captchas to keep in pool.
	 */
	private const POOL_MAX_SIZE = 20;

	/**
	 * Minimum number of captchas before refill is triggered.
	 */
	private const POOL_MIN_SIZE = 10;

	/**
	 * Maximum age of a pooled captcha in seconds (1 hour).
	 */
	private const POOL_MAX_AGE = 3600;

	/**
	 * @param CF7Captcha $Controller
	 */
	public function __construct( CF7Captcha $Controller ) {
		parent::__construct( $Controller );

		$this->get_logger()->info(
			"__construct(): CaptchaPool initialized",
			[
				'plugin'   => 'f12-cf7-captcha',
				'class'    => __CLASS__,
				'max_size' => self::POOL_MAX_SIZE,
				'min_size' => self::POOL_MIN_SIZE,
			]
		);
	}

	/**
	 * Gets the current pool from options.
	 *
	 * @return array Array of pooled captcha data.
	 */
	public function get_pool(): array {
		$pool = get_option( self::POOL_OPTION, [] );

		if ( ! is_array( $pool ) ) {
			$pool = [];
		}

		$this->get_logger()->debug(
			"get_pool(): Pool retrieved",
			[
				'plugin' => 'f12-cf7-captcha',
				'size'   => count( $pool ),
			]
		);

		return $pool;
	}

	/**
	 * Saves the pool to options.
	 *
	 * @param array $pool The pool data to save.
	 *
	 * @return bool True on success.
	 */
	private function save_pool( array $pool ): bool {
		$result = update_option( self::POOL_OPTION, $pool, false );

		$this->get_logger()->debug(
			"save_pool(): Pool saved",
			[
				'plugin' => 'f12-cf7-captcha',
				'size'   => count( $pool ),
				'result' => $result,
			]
		);

		return $result;
	}

	/**
	 * Gets the current pool size.
	 *
	 * @return int Number of captchas in pool.
	 */
	public function get_pool_size(): int {
		return count( $this->get_pool() );
	}

	/**
	 * Checks if the pool needs to be refilled.
	 *
	 * @return bool True if pool size is below minimum.
	 */
	public function needs_refill(): bool {
		$size        = $this->get_pool_size();
		$needs_refill = $size < self::POOL_MIN_SIZE;

		$this->get_logger()->debug(
			"needs_refill(): Check result",
			[
				'plugin'       => 'f12-cf7-captcha',
				'current_size' => $size,
				'min_size'     => self::POOL_MIN_SIZE,
				'needs_refill' => $needs_refill,
			]
		);

		return $needs_refill;
	}

	/**
	 * Retrieves a captcha from the pool.
	 *
	 * Returns a pre-generated captcha and removes it from the pool.
	 * Returns null if pool is empty.
	 *
	 * @return array|null Captcha data array with 'code', 'image', 'created' or null.
	 */
	public function get_from_pool(): ?array {
		$pool = $this->get_pool();

		if ( empty( $pool ) ) {
			$this->get_logger()->warning(
				"get_from_pool(): Pool is empty",
				[ 'plugin' => 'f12-cf7-captcha' ]
			);
			return null;
		}

		// Remove expired entries first
		$pool = $this->clean_expired( $pool );

		if ( empty( $pool ) ) {
			$this->get_logger()->warning(
				"get_from_pool(): Pool empty after cleaning expired entries",
				[ 'plugin' => 'f12-cf7-captcha' ]
			);
			$this->save_pool( $pool );
			return null;
		}

		// Get first captcha from pool (FIFO)
		$captcha = array_shift( $pool );
		$this->save_pool( $pool );

		$this->get_logger()->info(
			"get_from_pool(): Captcha retrieved from pool",
			[
				'plugin'         => 'f12-cf7-captcha',
				'remaining_size' => count( $pool ),
			]
		);

		return $captcha;
	}

	/**
	 * Adds a captcha to the pool.
	 *
	 * @param string $code  The captcha code.
	 * @param string $image The base64 encoded image HTML.
	 *
	 * @return bool True on success.
	 */
	public function add_to_pool( string $code, string $image ): bool {
		$pool = $this->get_pool();

		// Don't exceed max size
		if ( count( $pool ) >= self::POOL_MAX_SIZE ) {
			$this->get_logger()->debug(
				"add_to_pool(): Pool is full, skipping",
				[
					'plugin'   => 'f12-cf7-captcha',
					'max_size' => self::POOL_MAX_SIZE,
				]
			);
			return false;
		}

		$pool[] = [
			'code'    => $code,
			'image'   => $image,
			'created' => time(),
		];

		$result = $this->save_pool( $pool );

		$this->get_logger()->debug(
			"add_to_pool(): Captcha added to pool",
			[
				'plugin'   => 'f12-cf7-captcha',
				'new_size' => count( $pool ),
			]
		);

		return $result;
	}

	/**
	 * Removes expired entries from pool.
	 *
	 * @param array $pool The pool to clean.
	 *
	 * @return array Cleaned pool.
	 */
	private function clean_expired( array $pool ): array {
		$now     = time();
		$cleaned = [];
		$removed = 0;

		foreach ( $pool as $captcha ) {
			$age = $now - ( $captcha['created'] ?? 0 );

			if ( $age < self::POOL_MAX_AGE ) {
				$cleaned[] = $captcha;
			} else {
				$removed++;
			}
		}

		if ( $removed > 0 ) {
			$this->get_logger()->info(
				"clean_expired(): Expired captchas removed",
				[
					'plugin'  => 'f12-cf7-captcha',
					'removed' => $removed,
				]
			);
		}

		return $cleaned;
	}

	/**
	 * Fills the pool with pre-generated captchas.
	 *
	 * This method is called by the cron job to maintain the pool.
	 *
	 * @param int $count Number of captchas to generate. Default fills to max.
	 *
	 * @return int Number of captchas generated.
	 */
	public function fill_pool( int $count = 0 ): int {
		$pool = $this->get_pool();

		// Clean expired first
		$pool = $this->clean_expired( $pool );
		$this->save_pool( $pool );

		$current_size = count( $pool );

		if ( $count === 0 ) {
			$count = self::POOL_MAX_SIZE - $current_size;
		}

		if ( $count <= 0 ) {
			$this->get_logger()->debug(
				"fill_pool(): Pool is already full",
				[
					'plugin' => 'f12-cf7-captcha',
					'size'   => $current_size,
				]
			);
			return 0;
		}

		$this->get_logger()->info(
			"fill_pool(): Starting pool generation",
			[
				'plugin'       => 'f12-cf7-captcha',
				'current_size' => $current_size,
				'to_generate'  => $count,
			]
		);

		$generated = 0;
		$length    = 6; // Default captcha length

		// Get length from settings if available
		$setting_length = $this->Controller->get_settings( 'protection_captcha_length', 'global' );
		if ( is_numeric( $setting_length ) && $setting_length >= 4 && $setting_length <= 8 ) {
			$length = (int) $setting_length;
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$captcha_data = $this->generate_captcha_image( $length );

			if ( $captcha_data !== null ) {
				$this->add_to_pool( $captcha_data['code'], $captcha_data['image'] );
				$generated++;
			}
		}

		$this->get_logger()->info(
			"fill_pool(): Pool generation completed",
			[
				'plugin'    => 'f12-cf7-captcha',
				'generated' => $generated,
				'new_size'  => $this->get_pool_size(),
			]
		);

		return $generated;
	}

	/**
	 * Generates a single captcha image.
	 *
	 * @param int $length The length of the captcha code.
	 *
	 * @return array|null Array with 'code' and 'image' or null on failure.
	 */
	private function generate_captcha_image( int $length ): ?array {
		$allowed_chars = 'abcdefghjkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$max           = strlen( $allowed_chars ) - 1;
		$code          = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$code .= $allowed_chars[ rand( 0, $max ) ];
		}

		// Generate image
		$font_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/arial.ttf';

		if ( ! file_exists( $font_path ) ) {
			$this->get_logger()->error(
				"generate_captcha_image(): Font file not found",
				[
					'plugin' => 'f12-cf7-captcha',
					'path'   => $font_path,
				]
			);
			return null;
		}

		// Create the image
		$image = imagecreate( 125, 30 );
		imagecolorallocate( $image, 255, 255, 255 );

		$offset_left = 10;

		for ( $i = 0; $i < strlen( $code ); $i++ ) {
			imagettftext(
				$image,
				20,
				rand( -10, 10 ),
				$offset_left + ( ( $i == 0 ? 5 : 15 ) * $i ),
				25,
				imagecolorallocate( $image, 200, 200, 200 ),
				$font_path,
				$code[ $i ]
			);
			imagettftext(
				$image,
				16,
				rand( -15, 15 ),
				$offset_left + ( ( $i == 0 ? 5 : 15 ) * $i ),
				25,
				imagecolorallocate( $image, 69, 103, 137 ),
				$font_path,
				$code[ $i ]
			);
		}

		ob_start();
		imagepng( $image );
		$image_data = ob_get_contents();
		ob_end_clean();

		imagedestroy( $image );

		$rand       = uniqid( 'cpi_', true );
		$image_html = '<span class="captcha-image"><img id="' . $rand . '" alt="captcha" loading="eager" decoding="sync" class="no-lazy skip-lazy" data-skip-lazy="true" data-no-lazy="1" src="data:image/png;base64,' . base64_encode( $image_data ) . '"/></span>';

		return [
			'code'  => $code,
			'image' => $image_html,
		];
	}

	/**
	 * Clears the entire pool.
	 *
	 * @return bool True on success.
	 */
	public function clear_pool(): bool {
		$this->get_logger()->warning(
			"clear_pool(): Clearing entire captcha pool",
			[ 'plugin' => 'f12-cf7-captcha' ]
		);

		return delete_option( self::POOL_OPTION );
	}
}
