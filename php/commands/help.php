<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>]
	 */
	function __invoke( $args, $assoc_args ) {
		$command = self::find_subcommand( $args );

		if ( $command ) {
			self::show_help( $command );
			exit;
		}

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}
	}

	private static function find_subcommand( $args ) {
		$command = \WP_CLI::$root;

		while ( !empty( $args ) && $command && $command->has_subcommands() ) {
			$command = $command->find_subcommand( $args );
		}

		return $command;
	}

	private static function show_help( $command ) {
		$out = self::get_initial_markdown( $command );

		$out .= $command->get_extra_markdown();

		// section headers
		$out = preg_replace( '/^## ([A-Z ]+)/m', '%9\1%n', $out );

		// old-style options
		$out = preg_replace( '/\n\* `(.+)`([^\n]*):\n\n/', "\n\t\\1\\2\n\t\t", $out );

		$out = str_replace( "\t", '  ', $out );

		self::pass_through_pager( WP_CLI::colorize( $out ) );
	}

	private static function launch_pager( $pager, $fd ) {
		// launch pager
		$descriptorspec = array(
			0 => $fd,
			1 => STDOUT,
			2 => array( 'pipe', 'w' )
		);

		$r = proc_close( proc_open( $pager, $descriptorspec, $pipes ) );

		if ( 127 == $r ) {
			return false;
		}

		if ( $r ) {
			fwrite( STDERR, stream_get_contents( $pipes[1] ) );
			exit( $r );
		}

		return true;
	}

	private static function pass_through_pager( $out ) {
		// convert string to file handle
		$fd = fopen( "php://temp", "r+" );
		fputs( $fd, $out );
		rewind( $fd );

		$pagers = array( 'less -r', 'more -r' );
		foreach ( $pagers as $pager ) {
			if ( self::launch_pager( $pager, $fd ) )
				return;
		}

		// no pager found
		echo $out;
	}

	private static function find( $candidates, $callback ) {
		foreach ( $candidates as $candidate ) {
			if ( call_user_func( $callback, $candidate ) )
				return $candidate;
		}

		return false;
	}

	private static function get_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = array(
			'name' => $name,
			'shortdesc' => $command->get_shortdesc(),
		);

		$binding['synopsis'] = "$name " . $command->get_synopsis();

		if ( $command->has_subcommands() ) {
			$binding['has-subcommands']['subcommands'] = self::render_subcommands( $command );
		}

		return Utils\mustache_render( 'man.mustache', $binding );
	}

	private static function render_subcommands( $command ) {
		$subcommands = array();
		foreach ( $command->get_subcommands() as $subcommand ) {
			 $subcommands[ $subcommand->get_name() ] = $subcommand->get_shortdesc();
		}

		$max_len = self::get_max_len( array_keys( $subcommands ) );

		$lines = array();
		foreach ( $subcommands as $name => $desc ) {
			$lines[] = str_pad( $name, $max_len ) . "\t\t\t" . $desc;
		}

		return $lines;
	}

	private static function get_max_len( $strings ) {
		$max_len = 0;
		foreach ( $strings as $str ) {
			$len = strlen( $str );
			if ( $len > $max_len )
				$max_len = $len;
		}

		return $max_len;
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

