<?php
/**
 * Plugin orchestrator.
 *
 * @package FANOS\Core
 */

declare( strict_types=1 );

namespace FANOS\Core;

use FANOS\Core\Analytics\Analytics;
use FANOS\Core\Forms\FormHandler;
use FANOS\Core\Library\QueryFilter;
use FANOS\Core\Newsletter\NewsletterSubscriber;
use FANOS\Core\PostTypes\PostTypes;
use FANOS\Core\Seo\Seo;
use FANOS\Core\Taxonomies\Taxonomies;

/**
 * Wires the plugin modules together. Kept deliberately thin so each module can be
 * unit-tested in isolation.
 */
final class Plugin {

	/**
	 * Modules that expose a register() method and hook themselves into WordPress.
	 *
	 * @var array<int, object>
	 */
	private array $modules = array();

	public function __construct() {
		$this->modules = array(
			new PostTypes(),
			new Taxonomies(),
			new QueryFilter(),
			new FormHandler(),
			new NewsletterSubscriber(),
			new Analytics(),
			new Seo(),
		);
	}

	/**
	 * Register every module.
	 */
	public function register(): void {
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}
	}

	/**
	 * Expose the loaded modules (used by tests and integrations).
	 *
	 * @return array<int, object>
	 */
	public function modules(): array {
		return $this->modules;
	}
}
