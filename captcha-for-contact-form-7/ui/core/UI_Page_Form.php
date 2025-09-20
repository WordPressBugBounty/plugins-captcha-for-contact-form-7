<?php

namespace f12_cf7_captcha\ui {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	abstract class UI_Page_Form extends UI_Page {
		/**
		 * define if the button for the submit should be displayed or not.
		 * if hidden, the wp_nonce will also be removed. Ensure you handle
		 * the save process on your own. The onSave function will still be called
		 *
		 * @var bool
		 */
		private $hide_submit_button = false;

		/**
		 * @return mixed
		 */
		protected function maybe_save(): void
		{
			$this->get_logger()->info('Starte den "Maybe Save" Prozess, um die Einstellungen zu speichern, wenn die Nonce gültig ist.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);

			// Überprüfe die Nonce (eine Sicherheitsmaßnahme von WordPress), um die Authentizität der Anfrage sicherzustellen.
			$nonce_name = $this->get_domain() . '_nonce';
			$nonce_action = $this->get_domain() . '_action';

			if (isset($_POST[$nonce_name]) && wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
				$this->get_logger()->info('Nonce-Validierung erfolgreich. Starte den Speichervorgang.');

				$settings = [];

				// Lade die Standardeinstellungen durch einen Filter.
				// Andere Komponenten können hier ihre Standardeinstellungen in das Array einfügen.
				$settings = apply_filters($this->get_domain() . '_get_settings', $settings);
				$this->get_logger()->debug('Einstellungen über den Filter "_get_settings" abgerufen.');

				// Ermögliche es Entwicklern, Aktionen vor dem Speichern der Einstellungen auszuführen.
				$settings = apply_filters($this->get_domain() . '_ui_' . $this->slug . '_before_on_save', $settings);
				$this->get_logger()->debug('Filter "_before_on_save" angewendet.');

				// Überprüfe, ob die Einstellungen tatsächlich gespeichert werden sollen.
				// Ein Entwickler kann diesen Filter nutzen, um das Speichern zu deaktivieren,
				// z.B. wenn ein benutzerdefinierter Button eine andere Aktion auslösen soll.
				$do_save = apply_filters($this->get_domain() . '_ui_do_save_settings', true);

				if ($do_save) {
					$this->get_logger()->info('Speichern der Einstellungen ist erlaubt. Führe on_save() aus.');

					// Führe die spezifische on_save-Logik der jeweiligen UI-Seite aus.
					$settings = $this->on_save($settings);

					// Speichere die finalen Einstellungen in der WordPress-Datenbank.
					update_option($this->get_domain() . '-settings', $settings);

					// Füge eine Erfolgsmeldung für den Benutzer hinzu.
					$this->get_ui_manager()->get_ui_message()->add(__('Settings updated', 'captcha-for-contact-form-7'), 'success');

					$this->get_logger()->info('Einstellungen erfolgreich in der Datenbank aktualisiert.');
				} else {
					$this->get_logger()->info('Speichern der Einstellungen wurde durch den Filter "_ui_do_save_settings" unterdrückt.');
				}

				// Ermögliche es Entwicklern, Aktionen nach dem Speichern der Einstellungen auszuführen.
				$settings = apply_filters($this->get_domain() . '_ui_' . $this->slug . '_after_on_save', $settings);
				$this->get_logger()->debug('Filter "_after_on_save" angewendet.');

			} else {
				$this->get_logger()->warning('Nonce-Validierung fehlgeschlagen oder Nonce nicht vorhanden. Speichervorgang abgebrochen.');
			}
		}

		/**
		 * Option to hide the submit button
		 *
		 * @param bool $hide
		 *
		 * @return void
		 */
		protected function hide_submit_button(bool $hide): void
		{
			$this->get_logger()->info('Lege fest, ob der Senden-Button versteckt werden soll.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'hide_button' => $hide,
			]);

			// Setzt die private/protected Eigenschaft, die den Status speichert.
			$this->hide_submit_button = $hide;

			$this->get_logger()->debug('Senden-Button-Status erfolgreich auf ' . ($hide ? 'versteckt' : 'sichtbar') . ' gesetzt.');
		}

		/**
		 * Returns true if the button should be hidden.
		 *
		 * @return bool
		 */
		protected function is_submit_button_hidden(): bool
		{
			$this->get_logger()->info('Überprüfe, ob der Senden-Button ausgeblendet ist.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
			]);

			$is_hidden = $this->hide_submit_button;

			$this->get_logger()->debug('Senden-Button-Status: ' . ($is_hidden ? 'versteckt' : 'sichtbar'));

			return $is_hidden;
		}

		/**
		 * Update the settings and return them
		 *
		 * @param $settings
		 *
		 * @return array
		 */
		protected abstract function on_save( $settings );

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function render_content(string $slug, string $page): void
		{
			$this->get_logger()->info('Starte das Rendering des Seiteninhalts.', [
				'class' => __CLASS__,
				'method' => __METHOD__,
				'requested_slug' => $slug,
				'page_slug' => $page,
				'expected_slug' => $this->get_slug(),
			]);

			// Überprüfe, ob der angeforderte Seiten-Slug mit dem der aktuellen Seite übereinstimmt.
			if ($this->get_slug() !== $page) {
				$this->get_logger()->debug('Die angeforderte Seite stimmt nicht mit der aktuellen überein. Rendering wird übersprungen.');
				return;
			}

			$this->get_logger()->info('Rendering-Prozess gestartet. Führe maybe_save() aus.');

			// Versuche, die Einstellungen zu speichern. Diese Methode überprüft selbstständig, ob ein POST-Request vorliegt und ob die Nonce gültig ist.
			$this->maybe_save();

			// Rufe die globalen Einstellungen über einen Filter ab.
			$settings = apply_filters($this->get_domain() . '_get_settings', []);
			$this->get_logger()->debug('Einstellungen über Filter abgerufen.');

			// Rende die UI-Nachrichten (z.B. Erfolgs- oder Fehlermeldungen).
			$this->get_ui_manager()->get_ui_message()->render();

			?>
            <div class="box">
                <form action="" method="post">
					<?php
					// Löse einen Hook aus, der vor dem Hauptinhalt liegt.
					do_action($this->get_domain() . '_ui_' . $page . '_before_content', $settings);
					$this->get_logger()->debug('Hook "before_content" ausgelöst.', ['hook' => $this->get_domain() . '_ui_' . $page . '_before_content']);

					// Rende den eigentlichen Inhalt der Seite.
					$this->the_content($slug, $page, $settings);

					// Löse einen Hook aus, der nach dem Hauptinhalt liegt.
					do_action($this->get_domain() . '_ui_' . $page . '_after_content', $settings);
					$this->get_logger()->debug('Hook "after_content" ausgelöst.', ['hook' => $this->get_domain() . '_ui_' . $page . '_after_content']);
					?>

					<?php
					// Zeige den "Speichern"-Button und das Nonce-Feld nur an, wenn er nicht explizit ausgeblendet wurde.
					if (!$this->is_submit_button_hidden()):
						// Füge das Nonce-Feld hinzu, um die Anfrage vor Cross-Site Request Forgery (CSRF) zu schützen.
						wp_nonce_field($this->get_domain() . '_action', $this->get_domain() . '_nonce');
						?>
                        <input type="submit" name="<?php echo esc_attr($this->get_domain()); ?>-settings-submit" class="button"
                               value=" <?php echo esc_attr(__('Save', 'captcha-for-contact-form-7')); ?>"/>
					<?php endif; ?>
                </form>
            </div>
			<?php
			$this->get_logger()->info('Rendering des Seiteninhalts abgeschlossen.');
		}
	}
}