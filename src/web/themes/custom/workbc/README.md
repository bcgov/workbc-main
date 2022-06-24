# WorkBC theme
[Bootstrap 5](https://www.drupal.org/project/bootstrap5) subtheme.

## Fonts.
B.C. Government digital services are expected to make use of BCSans.
See https://developer.gov.bc.ca/Typography for more details.

## Development.

### CSS compilation.
The php Docker container has Yarn, Grunt, and grunt-dart-sass set up for compilation.
You will need to either `make install` or `yarn install` from `src/` to install all of the dependencies first.

Once dependencies are in place
`yarn run grunt dart-sass` will compile everything starting with the style.scss, and
`yarn run grunt watch` will start a watch on all .scss files, and compile on detecting changes.

There are also shortcuts in the toplevel Makefile to these commands -
`make compilescss` and
`make watchscss` respectively.
