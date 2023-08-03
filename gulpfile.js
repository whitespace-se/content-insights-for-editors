// Include gulp
var gulp = require('gulp');

// Include Our Plugins
var concat = require('gulp-concat');
var cssnano = require('gulp-cssnano');
var rename = require('gulp-rename');
var autoprefixer = require('gulp-autoprefixer');
var plumber = require('gulp-plumber');


// Concatenate & Minify JS
gulp.task('scripts-dist', function() {
    gulp.src([
            'source/js/**/*.js',
        ])
        .pipe(concat('broken-link-detector.dev.js'))
        .pipe(gulp.dest('dist/js'))
        .pipe(rename('broken-link-detector.min.js'))
        .pipe(gulp.dest('dist/js'));

    gulp.src([
            'source/mce/**/*.js',
        ])
        .pipe(concat('mce-broken-link-detector.dev.js'))
        .pipe(gulp.dest('dist/js'))
        .pipe(rename('mce-broken-link-detector.min.js'))
        .pipe(gulp.dest('dist/js'));
});

// Watch Files For Changes
gulp.task('watch', function() {
    gulp.watch(['source/js/**/*.js', 'source/mce/**/*.js'], ['scripts-dist']);
});

// Default Task
gulp.task('default', ['scripts-dist', 'watch']);

