<?php
/**
 * PDF of a request, made on the server: every page is drawn with GD + FreeType in the bundled Inter font
 * (SIL Open Font License, Greek included), then the pages are wrapped as JPEG images in a minimal PDF.
 * The same file is attached to the team's email and downloaded by the visitor and from the admin.
 */
defined( 'ABSPATH' ) || exit;

const SMI_PDF_SCALE = 2.2; // pixels per point, ≈ 160 dpi

/** The logo on the PDF: the one chosen in the settings, else the Smartup logo that comes with the plugin. */
function smi_logo_file( $s = null ) {
	$s  = $s ? $s : smi_get_settings();
	$id = (int) $s['pdf']['logo_id'];
	$f  = $id ? (string) get_attached_file( $id ) : '';
	return ( $f && is_readable( $f ) ) ? $f : SMI_DIR . 'assets/logo-smartup.png';
}

function smi_logo_url( $s = null, $size = 'full' ) {
	$s   = $s ? $s : smi_get_settings();
	$id  = (int) $s['pdf']['logo_id'];
	$src = $id ? wp_get_attachment_image_src( $id, $size ) : false;
	return $src ? (string) $src[0] : SMI_URL . 'assets/logo-smartup.png';
}

function smi_pdf_font() {
	return SMI_DIR . 'assets/fonts/Inter.ttf';
}

/** GD with FreeType and JPEG, and the font file: without them the browser makes the PDF instead. */
function smi_pdf_available() {
	return function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' ) && function_exists( 'imagejpeg' ) && function_exists( 'imagecreatetruecolor' ) && is_readable( smi_pdf_font() );
}

/** Everything the PDF shows, in the language the visitor used. */
function smi_pdf_doc( array $r, $lang = null ) {
	$s    = smi_get_settings();
	$lang = $lang ? $lang : $r['lang'];
	$t    = function ( $k ) use ( $s, $lang ) {
		return smi_tr( $s['texts'][ $k ], $lang );
	};
	$snap     = smi_snapshot_lang( $r, $lang );
	$sections = array( array( 'title' => $t( 'pdf_service' ), 'rows' => array( array( $t( 'pdf_need' ), isset( $snap['service'] ) ? $snap['service'] : '' ) ) ) );
	foreach ( isset( $snap['sections'] ) ? (array) $snap['sections'] : array() as $sec ) {
		$sections[] = array( 'title' => (string) $sec['title'], 'rows' => (array) $sec['rows'] );
	}
	$contact = array(
		array( $t( 'f_name' ), $r['name'] ),
		array( $t( 'f_email' ), $r['email'] ),
		array( $t( 'f_phone' ), $r['phone'] ),
		array( $t( 'f_company' ), $r['company'] ),
		array( $t( 'pdf_via' ), $t( 'phone' === $r['pref'] ? 'pref_phone' : 'pref_email' ) ),
	);
	$sections[] = array(
		'title' => $t( 'pdf_contact' ),
		'rows'  => array_values( array_filter( $contact, function ( $x ) { return '' !== (string) $x[1]; } ) ),
	);
	$footer = smi_tr( $s['pdf']['footer'], $lang );
	return array(
		'lang'     => $lang,
		'title'    => $t( 'pdf_title' ),
		'ref'      => $r['ref'],
		'date'     => get_date_from_gmt( $r['created_at'], 'd/m/Y' ),
		'sections' => $sections,
		'company'  => $s['pdf']['company'],
		'footer'   => '' !== $footer ? $footer : $t( 'reply_time' ),
		'accent'   => $s['look']['accent'],
		'logo'     => smi_logo_file( $s ),
		'filename' => smi_pdf_filename( $t( 'pdf_file' ), $r['ref'] ),
	);
}

function smi_pdf_filename( $base, $ref ) {
	$name = trim( preg_replace( '/[^A-Za-z0-9_-]+/', '-', ( '' !== $base ? $base : 'request' ) . '-' . $ref ), '-' );
	return ( '' !== $name ? substr( $name, 0, 60 ) : 'request' ) . '.pdf';
}

/* ---------- Drawing ---------- */

/** Greek capitals lose their accents (ΥΠΗΡΕΣΙΑ, not ΥΠΗΡΕΣΊΑ); other scripts are simply uppercased. */
function smi_pdf_upper( $text, $lang ) {
	$u = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
	return 'el' === $lang ? strtr( $u, array( 'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω', 'ΐ' => 'Ϊ', 'ΰ' => 'Ϋ' ) ) : $u;
}

class SMI_PDF_Canvas {
	public $img;
	private $colors = array();

	public function __construct( $w, $h ) {
		$this->img = imagecreatetruecolor( $w, $h );
		imagealphablending( $this->img, true );
		imagefill( $this->img, 0, 0, imagecolorallocate( $this->img, 255, 255, 255 ) );
	}

	public function color( $hex ) {
		if ( ! isset( $this->colors[ $hex ] ) ) {
			$n                    = hexdec( ltrim( $hex, '#' ) );
			$this->colors[ $hex ] = imagecolorallocate( $this->img, ( $n >> 16 ) & 255, ( $n >> 8 ) & 255, $n & 255 );
		}
		return $this->colors[ $hex ];
	}
}

class SMI_PDF_Renderer {
	const PT_W   = 595.28;
	const PT_H   = 841.89;
	const M      = 46;
	const TOP    = 46;
	const BOTTOM = 56;
	const LINE   = 13.5;
	const FIRST  = 16;

	private $font;
	private $pages = array();
	/** @var SMI_PDF_Canvas */
	private $page;
	private $y = 0;

	public function __construct() {
		$this->font = smi_pdf_font();
	}

	private function px( $pt ) {
		return (int) round( $pt * SMI_PDF_SCALE );
	}

	/** GD sizes are in points at 96 dpi; this gives text $pt points tall on our page. */
	private function gd_size( $pt ) {
		return $pt * SMI_PDF_SCALE * 72 / 96;
	}

	public function width( $text, $pt ) {
		if ( '' === $text ) {
			return 0;
		}
		$b = imagettfbbox( $this->gd_size( $pt ), 0, $this->font, $text );
		return ( $b[2] - $b[0] ) / SMI_PDF_SCALE;
	}

	/** $bold: drawn twice, a hair apart (the bundled face is the regular weight). */
	private function text( $text, $x, $y, $pt, $hex, $bold = false, $align = 'left' ) {
		if ( '' === $text ) {
			return;
		}
		if ( 'right' === $align ) {
			$x -= $this->width( $text, $pt );
		}
		$c = $this->page->color( $hex );
		imagettftext( $this->page->img, $this->gd_size( $pt ), 0, $this->px( $x ), $this->px( $y ), $c, $this->font, $text );
		if ( $bold ) {
			imagettftext( $this->page->img, $this->gd_size( $pt ), 0, $this->px( $x ) + 1, $this->px( $y ), $c, $this->font, $text );
		}
	}

	private function rect( $x, $y, $w, $h, $hex ) {
		imagefilledrectangle( $this->page->img, $this->px( $x ), $this->px( $y ), $this->px( $x + $w ) - 1, max( $this->px( $y ), $this->px( $y + $h ) - 1 ), $this->page->color( $hex ) );
	}

	private function new_page() {
		$this->page    = new SMI_PDF_Canvas( $this->px( self::PT_W ), $this->px( self::PT_H ) );
		$this->pages[] = $this->page;
		$this->y       = self::TOP;
	}

	private function ensure( $space ) {
		if ( $this->y + $space > self::PT_H - self::BOTTOM ) {
			$this->new_page();
		}
	}

	/** Lines that fit $max points; long words are broken by character. */
	public function wrap( $text, $pt, $max ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $para ) {
			$words = preg_split( '/\s+/u', trim( $para ), -1, PREG_SPLIT_NO_EMPTY );
			if ( ! $words ) {
				$out[] = '';
				continue;
			}
			$line = '';
			foreach ( $words as $w ) {
				$test = '' === $line ? $w : $line . ' ' . $w;
				if ( $this->width( $test, $pt ) <= $max ) {
					$line = $test;
					continue;
				}
				if ( '' !== $line ) {
					$out[] = $line;
				}
				if ( $this->width( $w, $pt ) <= $max ) {
					$line = $w;
					continue;
				}
				$part = '';
				foreach ( preg_split( '//u', $w, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
					if ( '' !== $part && $this->width( $part . $ch, $pt ) > $max ) {
						$out[] = $part;
						$part  = $ch;
					} else {
						$part .= $ch;
					}
				}
				$line = $part;
			}
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	private function logo( $file ) {
		if ( ! $file || ! is_readable( $file ) || filesize( $file ) > 5 * MB_IN_BYTES ) {
			return null;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$img  = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $img ? $img : null;
	}

	/** Returns one GD image per page. */
	public function render( array $doc ) {
		$this->pages = array();
		$this->new_page();
		$right  = self::PT_W - self::M;
		$accent = preg_match( '/^#[0-9a-f]{6}$/i', isset( $doc['accent'] ) ? $doc['accent'] : '' ) ? $doc['accent'] : '#46e9d4';
		$lang   = isset( $doc['lang'] ) ? $doc['lang'] : '';
		$co     = isset( $doc['company'] ) ? (array) $doc['company'] : array();
		$y      = $this->y;
		$top    = $y;

		// Header: logo (or company name) left, title/ref/date right.
		$logo = $this->logo( isset( $doc['logo'] ) ? $doc['logo'] : '' );
		if ( $logo ) {
			$lw = imagesx( $logo );
			$lh = imagesy( $logo );
			$h  = 34;
			$w  = $lw / $lh * $h;
			if ( $w > 170 ) {
				$w = 170;
				$h = $w * $lh / $lw;
			}
			imagecopyresampled( $this->page->img, $logo, $this->px( self::M ), $this->px( $y ), 0, 0, $this->px( $w ), $this->px( $h ), $lw, $lh );
			smi_gd_free( $logo );
			$y += $h + 10;
		} elseif ( ! empty( $co['name'] ) ) {
			$this->text( $co['name'], self::M, $y + 16, 17, '#101828', true );
			$y += 26;
		}
		$lines = array_filter( array(
			( $logo && ! empty( $co['name'] ) ) ? $co['name'] : '',
			isset( $co['address'] ) ? $co['address'] : '',
			implode( ' · ', array_filter( array( isset( $co['phone'] ) ? $co['phone'] : '', isset( $co['email'] ) ? $co['email'] : '' ) ) ),
			isset( $co['website'] ) ? $co['website'] : '',
			isset( $co['vat'] ) ? $co['vat'] : '',
		), 'strlen' );
		foreach ( $lines as $l ) {
			$this->text( $l, self::M, $y + 8, 8.5, '#667085' );
			$y += 12;
		}
		$this->text( isset( $doc['title'] ) ? $doc['title'] : '', $right, $top + 15, 16, '#101828', true, 'right' );
		$this->text( isset( $doc['ref'] ) ? $doc['ref'] : '', $right, $top + 31, 9.5, '#667085', false, 'right' );
		$this->text( isset( $doc['date'] ) ? $doc['date'] : '', $right, $top + 45, 9.5, '#667085', false, 'right' );

		$y = max( $y, $top + 52 ) + 10;
		$this->rect( self::M, $y, self::PT_W - 2 * self::M, 2.2, $accent );
		$this->y = $y + 26;

		$label_w   = ( self::PT_W - 2 * self::M ) * 0.38;
		$value_x   = self::M + $label_w + 12;
		$value_w   = $right - $value_x;
		$two_lines = self::FIRST + self::LINE + 12;

		foreach ( isset( $doc['sections'] ) ? (array) $doc['sections'] : array() as $sec ) {
			$rows = isset( $sec['rows'] ) ? (array) $sec['rows'] : array();
			if ( ! $rows ) {
				continue;
			}
			$first = $this->row_lines( $rows[0], $label_w, $value_w );
			// A section title never stays alone at the bottom of a page: it moves with (the start of) its first row.
			$this->ensure( 8 + min( count( $first[2] ) * self::LINE + 12, $two_lines ) );
			$this->text( smi_pdf_upper( (string) $sec['title'], $lang ), self::M, $this->y, 8, '#667085', true );
			$this->y += 8;
			foreach ( $rows as $row ) {
				list( $l, $v, $all ) = $this->row_lines( $row, $label_w, $value_w );
				$n = count( $all );
				$h = $n * self::LINE + 12;
				// Short rows stay in one piece; a long text continues on the next page line by line.
				$this->ensure( $h <= ( self::PT_H - self::TOP - self::BOTTOM ) / 3 ? $h : $two_lines );
				$ty = $this->y + self::FIRST;
				for ( $i = 0; $i < $n; $i++ ) {
					if ( $ty > self::PT_H - self::BOTTOM ) {
						$this->new_page();
						$ty = $this->y + self::FIRST - 4;
					}
					if ( isset( $l[ $i ] ) ) {
						$this->text( $l[ $i ], self::M, $ty, 9.5, '#667085' );
					}
					if ( isset( $v[ $i ] ) ) {
						$this->text( $v[ $i ], $value_x, $ty, 9.5, '#101828', true );
					}
					$ty += self::LINE;
				}
				$this->y = $ty - self::LINE + 9.5;
				$this->rect( self::M, $this->y, self::PT_W - 2 * self::M, 0.7, '#eaecf0' );
			}
			$this->y += 24;
		}

		// Footer on every page.
		$total = count( $this->pages );
		foreach ( $this->pages as $i => $p ) {
			$this->page = $p;
			$this->rect( self::M, self::PT_H - 40, self::PT_W - 2 * self::M, 0.7, '#eaecf0' );
			$this->text( isset( $doc['footer'] ) ? (string) $doc['footer'] : '', self::M, self::PT_H - 26, 8, '#98a2b3' );
			$this->text( ( $i + 1 ) . ' / ' . $total, $right, self::PT_H - 26, 8, '#98a2b3', false, 'right' );
		}
		return wp_list_pluck( $this->pages, 'img' );
	}

	/** [ label lines, value lines, the longer of the two ]. */
	private function row_lines( $row, $label_w, $value_w ) {
		$l = $this->wrap( isset( $row[0] ) ? (string) $row[0] : '', 9.5, $label_w );
		$v = $this->wrap( isset( $row[1] ) && '' !== (string) $row[1] ? (string) $row[1] : '—', 9.5, $value_w );
		return array( $l, $v, count( $l ) >= count( $v ) ? $l : $v );
	}
}

/* ---------- Minimal PDF writer: one JPEG image per page ---------- */

/**
 * Frees a GD image. Since PHP 8.0 images are objects freed on their own and imagedestroy() does nothing; PHP 8.5
 * deprecates it, and the notice would land inside the PDF on servers that display errors.
 */
function smi_gd_free( $img ) {
	if ( PHP_VERSION_ID < 80000 ) {
		imagedestroy( $img );
	}
}

/** PDF text string for any Unicode title: UTF-16BE in hex. */
function smi_pdf_text( $s ) {
	$u = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $s, 'UTF-16BE', 'UTF-8' ) : $s;
	return '<FEFF' . strtoupper( bin2hex( $u ) ) . '>';
}

function smi_pdf_write( array $images, $title ) {
	$pw      = SMI_PDF_Renderer::PT_W;
	$ph      = SMI_PDF_Renderer::PT_H;
	$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
	$offsets = array();
	$obj     = function ( $n, $body ) use ( &$out, &$offsets ) {
		$offsets[ $n ] = strlen( $out );
		$out          .= $n . " 0 obj\n" . $body . "\nendobj\n";
	};
	$n    = count( $images );
	$kids = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$kids[] = ( 4 + $i * 3 ) . ' 0 R';
	}
	$obj( 1, '<< /Type /Catalog /Pages 2 0 R >>' );
	$obj( 2, '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>' );
	$obj( 3, '<< /Title ' . smi_pdf_text( $title ) . ' /Producer (SmartInquiry) >>' );
	foreach ( array_values( $images ) as $i => $img ) {
		ob_start();
		imagejpeg( $img, null, 88 );
		$jpeg = ob_get_clean();
		$w    = imagesx( $img );
		$h    = imagesy( $img );
		smi_gd_free( $img );
		$page    = 4 + $i * 3;
		$content = 'q ' . $pw . ' 0 0 ' . $ph . ' 0 0 cm /Im0 Do Q';
		$obj( $page, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pw . ' ' . $ph . '] /Resources << /XObject << /Im0 ' . ( $page + 2 ) . ' 0 R >> >> /Contents ' . ( $page + 1 ) . ' 0 R >>' );
		$obj( $page + 1, '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream" );
		$obj( $page + 2, '<< /Type /XObject /Subtype /Image /Width ' . $w . ' /Height ' . $h . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $jpeg ) . " >>\nstream\n" . $jpeg . "\nendstream" );
	}
	$total = 4 + $n * 3;
	$xref  = strlen( $out );
	$out  .= "xref\n0 " . $total . "\n0000000000 65535 f \n";
	for ( $k = 1; $k < $total; $k++ ) {
		$out .= sprintf( "%010d 00000 n \n", $offsets[ $k ] );
	}
	$out .= "trailer\n<< /Size " . $total . " /Root 1 0 R /Info 3 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
	return $out;
}

/** The PDF bytes for a document, or '' when the server can't draw it. */
function smi_pdf_bytes( array $doc ) {
	if ( ! smi_pdf_available() ) {
		return '';
	}
	try {
		$pages = ( new SMI_PDF_Renderer() )->render( $doc );
		return smi_pdf_write( $pages, trim( $doc['title'] . ' ' . $doc['ref'] ) );
	} catch ( \Throwable $e ) {
		return '';
	}
}

/** Sends a PDF to the browser as a download and stops. */
function smi_pdf_send( $bytes, $filename ) {
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'Content-Length: ' . strlen( $bytes ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

/* ---------- Visitor download: a signed link, valid for a few hours after sending ---------- */

function smi_pdf_sig( $id, $exp ) {
	return hash_hmac( 'sha256', 'pdf|' . (int) $id . '|' . (int) $exp, wp_salt( 'auth' ) );
}

function smi_pdf_link( $id ) {
	$exp = time() + 6 * HOUR_IN_SECONDS;
	return add_query_arg( array( 'id' => (int) $id, 'exp' => $exp, 'sig' => smi_pdf_sig( $id, $exp ) ), rest_url( 'smartinquiry/v1/pdf' ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'smartinquiry/v1', '/pdf', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$id  = absint( $req->get_param( 'id' ) );
			$exp = absint( $req->get_param( 'exp' ) );
			$sig = (string) $req->get_param( 'sig' );
			if ( ! $id || $exp < time() || ! hash_equals( smi_pdf_sig( $id, $exp ), $sig ) ) {
				return new WP_REST_Response( array( 'ok' => false, 'code' => 'expired' ), 403 );
			}
			$r     = smi_get_request( $id );
			$bytes = $r ? smi_pdf_bytes( smi_pdf_doc( $r ) ) : '';
			if ( '' === $bytes ) {
				return new WP_REST_Response( array( 'ok' => false, 'code' => 'unavailable' ), 404 );
			}
			smi_pdf_send( $bytes, smi_pdf_doc( $r )['filename'] );
		},
	) );
} );
