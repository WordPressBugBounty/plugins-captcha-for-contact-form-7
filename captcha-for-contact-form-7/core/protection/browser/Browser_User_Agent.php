<?php
// Desktop and mobile browser patterns
$browser_names   = array(
	'Opera',
	'Opera Mini',
	'Edge',
	'Chrome',
	'Safari',
	'Firefox',
	'MSIE',
	'Trident',
	'Netscape',
	'Konqueror',
	'Gecko',
	'Android Browser',
	'Chrome Mobile',
	'Edge Mobile',
	'Firefox Mobile',
	'Opera Mobile',
	'Opera Mini',
	'Safari Mobile',
);
$browser_regexes = array(
	'/(Opera|OPR)\/([\d\.]+)/',
	'/(Opera Mini)\/([\d\.]+)/',
	'/(Edg|EdgA|EdgiOS|Edge)\/([\d\.]+)/',
	'/(Chrome)\/([\d\.]+)/',
	'/(Version\/[\d\.]+ )?(Safari)\/([\d\.]+)/',
	'/(Firefox)\/([\d\.]+)/',
	'/(MSIE|Trident)(?:.*? rv:([\d\.]+))?/',
	'/(Trident)\/([\d\.]+)/',
	'/(Netscape)\/([\d\.]+)/',
	'/(Konqueror)\/([\d\.]+)/',
	'/rv:([\d\.]+)\) Gecko\/\d{8} Firefox\/\d{8}/',
	'/(Android) ([\d\.]+)(?:.*?Chrome\/([\d\.]+))?/',
	'/(Chrome)\/([\d\.]+) Mobile/',
	'/(EdgA|EdgiOS)\/([\d\.]+)/',
	'/(Firefox)\/([\d\.]+) Mobile/',
	'/(Opera|OPR)\/([\d\.]+)(?:.*?Version\/([\d\.]+))? Mobile/',
	'/(Opera Mini)\/([\d\.]+)/',
	'/(Version\/[\d\.]+ )?(Mobile\/[\w]+ )?(Safari)\/([\d\.]+)/'
);

// Platform and device type patterns
$platform_names      = array( 'Windows', 'Macintosh', 'Linux', 'Android', 'iOS', 'iPadOS' );
$platform_regexes    = array(
	'/Windows NT ([\d\.]+)/',
	'/(Mac OS X [\d_\.]+)/',
	'/Linux/',
	'/Android ([\d\.]+)/',
	'/iPhone(?:.*?CPU(?: iPhone)? OS ([\d_\.]+))?/',
	'/iPad(?:.*?CPU(?: iPhone)? OS ([\d_\.]+))?/',
	'/iPod(?:.*?CPU(?: iPhone)? OS ([\d_\.]+))?/'
);
$device_type_names   = array( 'Desktop', 'Tablet', 'Phone' );
$device_type_regexes = array( '/(Windows|Macintosh|Linux)/', '/(iPad|Android).*?Mobile/', '/(iPhone|iPod|Android)/' );

