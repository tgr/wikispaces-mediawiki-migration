<?php

namespace MediaWiki\Extension\WikispacesMigration;


/**
 * Convert Wikispaces markup into MediaWiki markup.
 */
class WikitextConverter {

	/**
	 * Convert Wikispaces markup into MediaWiki markup.
	 * @param string $text
	 * @return string
	 */
	public function convertToMediaWiki( $text ) {
		$replacements = [
			// bold and italic
			'#\*\*#' => "'''",
			'#//\*\*#' => "'''''",
			'#\*\*//#' => "'''''",
			'#//(.*)//#' => "''\$1''",
			// underlined
			'#__(.*)__#' => '<u>$1</u>',
			// monospaced
			'#{{(.*)}}#' => '<code>$1</code>',
			// raw
			'#``(.*)``#' => '<nowiki>$1</nowiki>',
			// ???
			'#\[\[toc\]\]#' => '',
			// file link
			'#\[\[file#' => '[[:File',
			// table
			'#(\|\|.+)(?=\n{2})#Us' => [ $this, 'convertTable' ],
			// code block
			'#\[\[code format="(.+)"\]\](.+)\[\[code\]\]#Us' => '<syntaxhighlight lang=$1>$2</syntaxhighlight>',
			'#\[\[code\]\](.+)\[\[code\]\]#Us' => '<syntaxhighlight>$1</syntaxhighlight>',
			// images
			'#\[\[image:(.*?)\]\]#' => [ $this, 'convertImage' ],
		];
		foreach ( $replacements as $pattern => $replacement ) {
			if ( !is_string( $replacement ) ) {
				$text = preg_replace_callback( $pattern, $replacement, $text );
			} else {
				$text = preg_replace( $pattern, $replacement, $text );
			}
		}
		return $text;
	}

	/**
	 * Scan Wikispaces markup and return a list of files and images used in it.
	 * @param string $text
	 * @return string[] List of filenames.
	 */
	public function extractFiles( $text ) {
		$files = [];
		preg_match_all( '#\[\[(?:image|file):(.*?)\]\]#', $text, $matches );
		foreach ( $matches[1] as $markup ) {
			list( $name, $args, $isExternal ) = $this->parseFileMarkup( $markup );
			if ( !$isExternal ) {
				$files[] = $name;
			}
		}
		return $files;
	}

	/**
	 * Create wikitext for a top-level (section opening) comment.
	 * @param string $subject Topic title
	 * @param string $body Body as Wikispaces markup
	 * @param string $userName MediaWiki username
	 * @param int $timestamp
	 * @return string
	 */
	public function convertTopComment( $subject, $body, $userName, $timestamp ) {
		$subject = wfEscapeWikiText( $subject );
		return "\n== $subject ==\n" . $this->convertToMediaWiki( $body )
			. $this->makeSignature( $userName, $timestamp );
	}

	/**
	 * Create wikitext for a followup comment.
	 * @param string $subject Topic title
	 * @param string $body Body as Wikispaces markup
	 * @param string $userName MediaWiki username
	 * @param int $timestamp
	 * @return string
	 */
	public function convertComment( $body, $userName, $timestamp ) {
		return "\n\n" . $this->convertToMediaWiki( $body )
			. $this->makeSignature( $userName, $timestamp );
	}

	/**
	 * Convert Wikispaces tags to categories.
	 * @param string[] $tags
	 * @return string
	 */
	public function convertTags( array $tags ) {
		$categoryFragments = array_map( function ( $tag ) {
			// TODO i18n, titleficaton
			return "[[Category:$tag]]";
		}, $tags );
		return "\n" . implode( '', $categoryFragments ) . '';
	}

	protected function convertImage( $matches ) {
		list( $name, $options, $isExternal ) = $this->parseFileMarkup( $matches[1] );
		$options += [
			'width' => '',
			'height' => '',
			'align' => '',
			'caption' => '',
			'link' => '',
		];
		if ( $isExternal ) {
			// MediaWiki does not allow external images. We could save and upload them to
			// turn them into internal images, use the limited $wgAllowExternalImages
			// syntax, or just link to them. We go with the last one here.
			// TODO make the other options configurable
			$caption = $options['caption'] ?: 'External image';
			return "[$name $caption]";
		}
		$mediaWikiOptionsStr = '';
		if ( !empty( $options['width'] ) && empty( $options['height'] ) ) {
			$mediaWikiOptionsStr .= '|' . $options['width'] . 'px';
		} elseif ( !empty( $options['height'] ) && empty( $options['width'] ) ) {
			$mediaWikiOptionsStr .= '|x' . $options['height'] . 'px';
		} elseif ( !empty( $options['width'] ) && !empty( $options['height'] ) ) {
			$mediaWikiOptionsStr .= '|' . $options['width'] . 'x' . $options['height'] . 'px';
		}
		if ( !empty( $options['align'] ) ) {
			$mediaWikiOptionsStr .= '|' . $options['align'];
		}
		if ( !empty( $options['link'] ) ) {
			$mediaWikiOptionsStr .= '|link=' . $options['link'];
		}
		if ( !empty( $options['caption'] ) ) {
			$mediaWikiOptionsStr .= '|' . $options['caption'];
		}
		return "[[File:{$name}{$mediaWikiOptionsStr}]]";
	}

	protected function convertTable( $matches ) {
		$table = "{|\n";
		$lines = explode( "\n", $matches[1] );
		foreach ( $lines as $k => $line ) {
			if ( $k > 0 ) {
				$table .= "|-\n";
			}
			$line = '|' . trim( $line, '|' );
			// Use bold to convert headers
			$line = preg_replace( '/~([^|]*)/', '\'\'\'$1\'\'\'', $line );
			$table .= $line . "\n";
		}
		return $table . "|}\n";
	}

	protected function parseFileMarkup( $markup ) {
		preg_match_all( '#(\S+)(\s*)#', $markup, $matches, PREG_SET_ORDER );
		$inName = true;
		$name = '';
		$args = [];
		foreach ( $matches as $i => list( $_, $word, $whitespace ) ) {
			if ( ( $inName || $i === 0 ) && ( strpos( $word, '=' ) === false ) ) {
				$name .= $word . $whitespace;
			} else {
				$inName = false;
				$argParts = explode( '=', $word, 2 );
				$args[$argParts[0]] = $argParts[1] ?? true;
			}
		}
		$isExternal = (bool)preg_match( '#https?://#', $name );
		return [ trim( $name ), $args, $isExternal ];
	}

	/**
	 * Create a wikitext signature.
	 * @param string $userName MediaWiki username
	 * @param int $timestamp
	 * @return string
	 */
	protected function makeSignature( $userName, $timestamp ) {
		// TODO i18n
		// TODO is this needed at all? We could probably just rely on PST.
		return " --[[User:$userName|$userName]] ([[User talk:$userName|talk]]) "
		  . wfTimestamp( TS_DB, $timestamp );

	}

}
