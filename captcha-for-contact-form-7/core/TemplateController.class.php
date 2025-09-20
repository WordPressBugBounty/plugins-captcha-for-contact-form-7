<?php

namespace f12_cf7_captcha\core;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class TemplateController
 */
class TemplateController extends BaseModul {
	/**
	 * Load a plugin template file.
	 *
	 * @param string $filename The name of the template file to load.
	 * @param array  $params   Optional. An associative array of parameters to pass to the template.
	 *
	 * @return void
	 */
	public function load_plugin_template(string $filename, array $params = []): void
	{
		$this->get_logger()->info('Versuche, eine Plugin-Template-Datei zu laden.', [
			'class' => __CLASS__,
			'method' => __METHOD__,
			'filename' => $filename,
		]);

		// Definiere den vollständigen Pfad zur Template-Datei.
		// Die Funktion dirname( __FILE__ ) gibt den übergeordneten Ordner des aktuellen Skripts zurück.
		$template_path = plugin_dir_path(dirname(__FILE__)) . "templates/$filename.php";

		$this->get_logger()->debug('Template-Pfad ermittelt.', ['path' => $template_path]);

		// Überprüfe, ob die Template-Datei existiert.
		if (file_exists($template_path)) {
			// Extrahiere die übergebenen Parameter in lokale Variablen.
			// Warnung: 'extract()' kann Sicherheitsrisiken bergen, wenn die Daten
			// von einer unsicheren Quelle stammen, da Variablen überschrieben werden könnten.
			// Da die Funktion als 'private' oder 'protected' angenommen wird, ist das Risiko gering.
			extract($params);

			$this->get_logger()->info('Template-Datei gefunden. Lade die Datei.');

			// Binde die Template-Datei ein.
			include($template_path);

			$this->get_logger()->debug('Template-Datei erfolgreich geladen.');
		} else {
			// Protokolliere einen Fehler, falls die Template-Datei nicht existiert.
			$error_message = sprintf('Template-Datei nicht gefunden: %s', $template_path);
			$this->get_logger()->error($error_message);

			// Verwende 'error_log' für direkte Fehlerprotokollierung, was in WordPress gängig ist.
			error_log($error_message);
		}
	}

	/**
	 * Retrieves the content of a plugin template file as a string.
	 *
	 * @param string $filename The name of the template file to load.
	 * @param array  $params   Optional. An array of parameters to pass to the template file. Defaults to an empty
	 *                         array.
	 *
	 * @return string The content of the plugin template file.
	 */
	public function get_plugin_template(string $filename, array $params = []): string
	{
		$this->get_logger()->info('Starte den Pufferungsprozess, um eine Plugin-Template-Datei zu laden und ihren Inhalt zurückzugeben.', [
			'class'    => __CLASS__,
			'method'   => __METHOD__,
			'filename' => $filename,
		]);

		// Starte die Ausgabe-Pufferung. Alle nachfolgenden 'echo'- oder 'print'-Aufrufe
		// werden nicht direkt ausgegeben, sondern in einen internen Puffer geschrieben.
		ob_start();

		// Lade die Template-Datei, was dazu führt, dass ihr Inhalt in den Puffer geschrieben wird.
		// Die load_plugin_template()-Methode wird die Datei suchen, die Parameter extrahieren
		// und sie dann über 'include' einbinden.
		try {
			$this->load_plugin_template($filename, $params);
			$this->get_logger()->debug('Template-Inhalt erfolgreich in den Puffer geladen.');
		} catch (\Throwable $e) {
			$this->get_logger()->error('Fehler beim Laden der Template-Datei.', [
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);
			// Leere den Puffer und beende die Pufferung, ohne den Inhalt zurückzugeben.
			ob_end_clean();
			return '';
		}

		// Holen Sie sich den Inhalt des Puffers und beenden Sie die Pufferung.
		// Der Puffer wird geleert und sein Inhalt als String zurückgegeben.
		$template_content = ob_get_clean();

		$this->get_logger()->info('Template-Inhalt erfolgreich aus dem Puffer abgerufen und zurückgegeben.');

		return $template_content;
	}
}