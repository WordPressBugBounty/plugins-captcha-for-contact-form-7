<?php

namespace f12_cf7_captcha\ui {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Messages
	 */
	class UI_Message {
		private $UI_Manager;

		/**
		 * @var array
		 */
		private $messages = [];

		public function __construct(UI_Manager $UI_Manager)
		{
			// Set the UI Manager instance via a private/protected method.
			// This ensures that the logic for setting the property is encapsulated.
			$this->set_ui_manager($UI_Manager);
			$this->get_logger()->debug('UI_Manager instance has been set.');

			$this->get_logger()->info('Constructor completed.');
		}

		public function get_logger(): LoggerInterface {
			return $this->UI_Manager->get_logger();
		}

		private function set_ui_manager( UI_Manager $UI_Manager ) {
			$this->UI_Manager = $UI_Manager;
		}

		public function get_ui_manager(): UI_Manager
		{
			$this->get_logger()->info('Retrieving the UI_Manager instance.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);

			// Return the privately stored UI Manager instance.
			$ui_manager = $this->UI_Manager;

			if (!$ui_manager instanceof UI_Manager) {
				$this->get_logger()->critical('The UI_Manager instance is not available or is of the wrong type.', [
					'type' => gettype($this->UI_Manager)
				]);
				// Optional: An exception could be thrown here if the instance is absolutely required.
			}

			$this->get_logger()->debug('UI_Manager instance successfully retrieved.');

			return $ui_manager;
		}

		/**
		 * getAll function.
		 *
		 * @access public
		 * @return void
		 */
		public function render(): void
		{
			$this->get_logger()->info('Starting the rendering of all stored messages.', [
				'class'  => __CLASS__,
				'method' => __METHOD__,
			]);

			// Iterate through all HTML messages stored in the `messages` array.
			foreach ($this->messages as $key => $value) {
				$this->get_logger()->debug('Rendering message.', ['key' => $key, 'message_length' => strlen($value)]);

				// Use wp_kses() to protect the message from Cross-Site-Scripting (XSS).
				// The allowed HTML tags are 'div' with the attributes 'class' and 'role'.
				// PHP_EOL ensures that each message is output on a new line,
				// which improves the readability of the generated HTML source code.
				echo wp_kses($value, [
						'div' => [
							'class' => [],
							'role'  => []
						]
					]) . PHP_EOL;
			}

			$this->get_logger()->info('Rendering of all messages completed.');
		}

		/**
		 * add function.
		 *
		 * @access public
		 *
		 * @param mixed $message
		 * @param mixed $type
		 *
		 * @return void
		 */
		public function add(string $message, string $type): void
		{
			$this->get_logger()->info('Adding a new UI message.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'type' => $type,
			]);

			// Define the mapping of message types to CSS classes in an array.
			// This is more efficient and easier to maintain than a long elseif chain.
			$type_map = [
				'error'    => 'alert-danger',
				'success'  => 'alert-success',
				'info'     => 'alert-info',
				'warning'  => 'alert-warning',
				'offer'    => 'alert-offer',
				'critical' => 'alert-critical',
			];

			// Use the Null Coalescing Operator to assign the type.
			// If the passed $type does not exist in the map, the original value is used.
			$css_class = $type_map[$type] ?? $type;

			$this->get_logger()->debug('CSS class determined for message type.', [
				'original_type' => $type,
				'css_class'     => $css_class,
			]);

			// Create the HTML string.
			// The WordPress functions esc_attr() and esc_html() are important
			// to prevent Cross-Site-Scripting (XSS).
			$html_message = sprintf(
				'<div class="box %s" role="alert">%s</div>',
				esc_attr($css_class),
				esc_html($message)
			);

			// Add the generated HTML message to the messages array.
			$this->messages[] = $html_message;

			$this->get_logger()->info('Message successfully added to the messages array.');
		}
	}
}