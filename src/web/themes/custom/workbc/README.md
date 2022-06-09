# WorkBC theme

[Bootstrap 5](https://www.drupal.org/project/bootstrap5) subtheme.

## Development.

### CSS compilation.

install [sass](https://sass-lang.com/install).
To compile, run from subtheme directory: `sass scss/style.scss css/style.css`

If you get an error looking like `error scss/style.scss (Line 55 of <rootpath>/src/web/themes/contrib/bootstrap5/dist/bootstrap/5.1.3/scss/mixins/_utilities.scss: Invalid CSS after "...ass}: #{$value}": expected "{", was ";")`
it means your sass compiler is out of date. 
If you happen to be compiling through Ruby Sass, a `gem update sass` should solve the issue, otherwise update your sass installation.
`sass -version` should tell you more.

