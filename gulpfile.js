var gulp            = require( 'gulp' );
var sass            = require( "gulp-sass" );
var sourcemaps      = require( 'gulp-sourcemaps' );
var postcss         = require( 'gulp-postcss' );
var autoprefixer    = require( 'autoprefixer' );
var objectFitImages = require( 'postcss-object-fit-images' );

/**
 * Compile with gulp-ruby-sass + source maps.
 */
gulp.task(
	'compile-sass',
	function () {
		return gulp.src( './assets/sass/**/*.scss' )
		  .pipe( sourcemaps.init() )
		  .pipe( sass().on( 'error', sass.logError ) )
		  .pipe( postcss( [ objectFitImages, autoprefixer() ] ) )
		  .pipe( sourcemaps.write('./maps') )
		  .pipe( gulp.dest( './assets/css' ) );
	}
);

gulp.task(
	'serve',
	function() {
		gulp.watch( "assets/sass/**/*.scss", gulp.series( 'compile-sass' ) );
	}
);

gulp.task(
	'sass',
	function() {
		gulp.watch( "assets/sass/**/*.scss", gulp.series( 'compile-sass' ) );
	}
);


gulp.task( 'default', gulp.series( 'serve' ) );
