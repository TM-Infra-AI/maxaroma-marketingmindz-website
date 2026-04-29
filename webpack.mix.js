const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js([
	//'resources/js/jquery-3.4.1.min.js',
    'resources/js/jquery.selectbox-0.2.min.js',
	'resources/js/common.js',
	'resources/js/smooth-scroll.js',
	'resources/js/menu-new.js',
	'resources/js/slick.js',
	'resources/js/modal.js',
], 'public/js/all.js').
		autoload({
			jquery: ['$', 'window.jQuery', 'jQuery','jquery'],
		});
mix.js('resources/js/home.js','public/js/home.js').autoload({jquery: ['$', 'window.jQuery', 'jQuery','jquery'],});
mix.js('resources/js/register.js','public/js/register.js').autoload({jquery: ['$', 'window.jQuery', 'jQuery','jquery'],});		

mix.styles([
	'resources/css/common.css',
	'resources/css/slick.css',
	'resources/css/header-footer.css',
], 'public/css/all.css');

mix.styles('resources/css/pages/*.css','public/css');

/*mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        //
    ]);
*/
