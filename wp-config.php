<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'IAD_Competition_Project_wordpress' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'StudentA' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'dVDl?L/%MO]|cJ71_0oI4-xN=GWijSLd*#Fdpu1eb:U)<VD7POrP}7CB7@T6z>AZ' );
define( 'SECURE_AUTH_KEY',  ',S}sW@0Sz(#4Q2t@exy+r1/WHBX8+q1l@^hO9d`nSA|3d:KtStzOlK7]+5WyFmw0' );
define( 'LOGGED_IN_KEY',    'zYUYMR]T6WqL]LEub{<Fv8/wgBYh5w@-*79-vnC56RQ6Q,RujL/ekh3f@l^Wsh1j' );
define( 'NONCE_KEY',        'eavPw!DC8)=rQt#%1DvblXv>[1LxtL/}]%]IOu[!sxn*.02 m]dx~vbvKfdN`X;Q' );
define( 'AUTH_SALT',        '&f5._2B00XKG|v0=e,~*}:j%NwV+QgerA,^r9Dfye[tq>oU[gN|R<Wzvg{sYHPYU' );
define( 'SECURE_AUTH_SALT', 'Yf+Rju^QzW<]KE_/Oq%JyOKeP$Gqo4%I_3*B%R*[wgPjBS9h?^l)CISDVyjm2PA#' );
define( 'LOGGED_IN_SALT',   '4@n&?v*{3<62(Vv%og8)/8fpZu}xfYxeH,[-;}zD%RLl0t6m{$l]3?c_&HPs3&Ej' );
define( 'NONCE_SALT',       '?*yTK<&n1# Y:`6@@OT[l(F$].9zGNt:pQc-p7Hx}Acfp`l2b4+$;t!vz:I1p>-U' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wcm_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

define('FS_METHOD','direct');
