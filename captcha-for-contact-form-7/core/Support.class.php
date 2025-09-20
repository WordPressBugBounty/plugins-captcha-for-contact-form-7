<?php

namespace f12_cf7_captcha\core;
use f12_cf7_captcha\CF7Captcha;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Class Support
 */
class Support extends BaseModul
{
	public function __construct(CF7Captcha $Controller)
	{
		parent::__construct($Controller);
		$this->get_logger()->info('Konstruktor gestartet.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Fügt einen Hook hinzu, um die Methode wp_add_link() im Footer aufzurufen.
		// Die Priorität 9999 stellt sicher, dass der Link sehr spät in den Footer eingefügt wird.
		add_action('wp_footer', array($this, 'wp_add_link'), 9999);
		$this->get_logger()->debug('Hook "wp_footer" für die Methode "wp_add_link" mit Priorität 9999 hinzugefügt.');

		$this->get_logger()->info('Konstruktor abgeschlossen.');
	}

    /**
     * Returns the link to be used in the maybe_load_link method.
     *
     * @return string The link HTML markup with the title, href, and display text.
     */
	private function get_link(): string
	{
		$this->get_logger()->debug('Erstelle den NoScript-Link für die Agentur.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Definiere die konstanten Werte, um den Code lesbarer zu machen und die Wiederverwendbarkeit zu verbessern.
		$title = 'Digital Agentur';
		$url = 'https://www.forge12.com';
		$text = 'Digitalagentur Forge12 Interactive GmbH';

		// Verwende sprintf, um den HTML-String zu erstellen.
		// esc_attr() und esc_html() sind wichtig, um XSS-Sicherheitslücken zu vermeiden.
		$link_html = sprintf(
			'<noscript><a title="%s" href="%s">%s</a></noscript>',
			esc_attr($title),
			esc_url($url), // Verwende esc_url() für URLs
			esc_html($text)
		);

		$this->get_logger()->info('HTML-Link erfolgreich generiert.');

		return $link_html;
	}

    /**
     * Retrieves a link to be loaded if it exists.
     * The link will only be loaded if the support setting for 'global' is set to 1.
     *
     * @return string The link to be loaded, or an empty string if the link does not exist.
     */
	public function maybe_load_link(): string
	{
		$this->get_logger()->info('Überprüfe, ob der Support-Link geladen werden soll.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		$link = ''; // Standardmäßig ein leerer String

		// Rufe die Einstellung für den Support-Link ab.
		$support_setting = $this->Controller->get_settings('support', 'global');
		$this->get_logger()->debug('Support-Einstellung abgerufen.', ['value' => $support_setting]);

		// Wenn die Einstellung '1' ist, lade den Link.
		if ($support_setting === 1) {
			$this->get_logger()->info('Support-Link-Einstellung ist aktiviert. Lade den Link.');
			$link = $this->get_link();
		} else {
			$this->get_logger()->info('Support-Link-Einstellung ist deaktiviert. Link wird nicht geladen.');
		}

		return $link;
	}

    /**
     * Adds a link to the current page if the link exists.
     * The link is loaded using the maybe_load_link() method.
     *
     * @return void
     */
	public function wp_add_link()
	{
		$this->get_logger()->info('Füge den Agentur-Support-Link zum WordPress-Footer hinzu.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
		]);

		// Rufe die maybe_load_link()-Methode auf, um den HTML-Link-String zu erhalten.
		// Diese Methode gibt den Link-HTML-Code nur zurück, wenn die entsprechende Einstellung aktiviert ist.
		$link_html = $this->maybe_load_link();

		// Gib den HTML-Code aus.
		// Wenn der Link nicht geladen werden soll, ist $link_html ein leerer String,
		// sodass nichts in den Footer eingefügt wird.
		echo $link_html;

		if (!empty($link_html)) {
			$this->get_logger()->info('Agentur-Support-Link wurde erfolgreich in den Footer eingefügt.');
		} else {
			$this->get_logger()->info('Kein Link zum Einfügen in den Footer, da die Einstellung deaktiviert ist.');
		}
	}
}