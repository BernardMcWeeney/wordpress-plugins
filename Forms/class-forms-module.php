<?php
/**
 * Forms module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Forms module.
 */
class Module {
	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Mailer.
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 * @param Mailer     $mailer Mailer.
	 */
	public function __construct( Repository $repository, Mailer $mailer ) {
		$this->repository = $repository;
		$this->mailer     = $mailer;
	}

	/**
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$repository = new Repository();
		$repository->install_defaults();

		$mailer = new Mailer();

		( new Rest( $repository, $mailer ) )->init();
		( new Blocks( $repository ) )->init();

		if ( is_admin() ) {
			( new Admin( $repository ) )->init();
		}
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		( new Repository() )->install_defaults();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Submissions are never stored, and saved form definitions remain available.
	}
}
