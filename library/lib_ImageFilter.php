<?php
	use App\Library\Request;

	ini_set('memory_limit', '128M');

//	error_reporting(E_ALL);
//	ini_set('display_errors', 1);

	// http://php.net/manual/en/function.imagefilter.php
	//
	// Alternative: http://phpimageworkshop.com/
	//
	$src 	   = Request::query('image', '');
	$filter    = Request::query('filter', '');
	$arg       = Request::query('arg', '');
	$com	   = Request::query('compression', '');
	$phpfilter = IMG_FILTER_BRIGHTNESS;

	// Path traversal protection
	if ($src === '' || strpos($src, '..') !== false || $src[0] === '/') {
		http_response_code(400);
		die('Invalid image path.');
	}

	// Load
	$source = imagecreatefromjpeg($src);
		
	if ($filter=="sharpen") {
		
		imagesharpen_precise( $source );

	/*
							$sharpenMatrix = array (
											array (-1,-1,-1),
											array (-1,16,-1),
											array (-1,-1,-1),
											);

							$divisor = 8;
							$offset = 0;

							imageconvolution ($canvas, $sharpenMatrix, $divisor, $offset);
	*/	
	} else {
		
		// first: only support this
		if ($filter=="brightness") {
			$phpfilter = IMG_FILTER_BRIGHTNESS;
		}
		
		// Apply filter
		imagefilter($source, $phpfilter, ($arg=="-" ? -12 : 12) );
	}

	// Output
	imagejpeg($source, $src, $com);

	die;

		
	function imagesharpen_precise($image)
	{
		$height = imagesy($image);
		$width  = imagesx($image);
		$rs = array();
		$gs = array();
		$bs = array();
		for ($y = 0; $y < $height; ++$y)
		{
			for ($x = 0; $x < $width; ++$x)
			{
				$rgb = imagecolorat($image, $x, $y);
				$rs[$y][$x] = $rgb >> 0x10;
				$gs[$y][$x] = $rgb >> 0x08 & 0xFF;
				$bs[$y][$x] = $rgb         & 0xFF;
			}
		}
		$height--;
		$width--;
		for ($y = 1; $y < $height; ++$y)
		{
			$rd = $rs[$y][0];
			$gd = $gs[$y][0];
			$bd = $bs[$y][0];
			$yd = $y - 1;
			$yi = $y + 1;
			for ($x = 1; $x < $width; ++$x)
			{
				$r = -($rs[$yd][$x] + $rs[$yi][$x] + $rd + $rs[$y][$x + 1]) / 4;
				$g = -($gs[$yd][$x] + $gs[$yi][$x] + $gd + $gs[$y][$x + 1]) / 4;
				$b = -($bs[$yd][$x] + $bs[$yi][$x] + $bd + $bs[$y][$x + 1]) / 4;
				$r += 2 * $rd = $rs[$y][$x];
				$g += 2 * $gd = $gs[$y][$x];
				$b += 2 * $bd = $bs[$y][$x];
				if ($r < 0) $r = 0;
				elseif ($r > 255) $r = 255;
				if ($g < 0) $g = 0;
				elseif ($g > 255) $g = 255;
				if ($b < 0) $b = 0;
				elseif ($b > 255) $b = 255;
				imagesetpixel($image, $x, $y, $r << 0x10 | $g << 0x08 | $b);
			}           
		}
	}
?>