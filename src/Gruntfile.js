module.exports = function(grunt) {

    grunt.initConfig({
        'dart-sass': {
          target: {
            options: {
              outputStyle: 'compressed',
              sourceMap: true
            },
            files: {
              'web/themes/custom/workbc/css/style.css': 'web/themes/custom/workbc/scss/style.scss'
            }
          }
        },
        watch: {
          src: {
            files: ['web/themes/custom/workbc/scss/**/*.scss'],
            tasks: ['dart-sass'],
          },
        },
      });

    grunt.loadNpmTasks('grunt-dart-sass');
    grunt.loadNpmTasks('grunt-contrib-watch');
};