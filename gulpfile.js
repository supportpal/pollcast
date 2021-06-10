var gulp = require('gulp'),
    gutil = require('gulp-util'),
    uglify = require('gulp-uglify'),
    rename = require("gulp-rename"),
    webpack = require("webpack");

var outputDir = __dirname + '/public/js';
var webpackConfig = {
    entry: './resources/assets/js/pollcast.js',
    devtool: 'inline-source-map',
    mode: 'development',
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        "plugins": [
                            ['@babel/plugin-transform-runtime', {debug: true}]
                        ]
                    }
                }
            }
        ]
    },
    output: {
        path: outputDir,
        filename: 'pollcast.js',
        library: 'Pollcast',
        libraryTarget: 'umd'
    }
};

gulp.task('build', gulp.series(
    function (cb) {
        webpack(webpackConfig, function(err, stats) {
            if(err) throw new gutil.PluginError("webpack:build", err);
            gutil.log("[webpack:build]", stats.toString({
                colors: true
            }));
            cb();
        });
    },
    function () {
        return gulp.src('public/js/pollcast.js')
            .pipe(uglify())
            .pipe(rename('pollcast.min.js'))
            .pipe(gulp.dest(outputDir));
    }
));
