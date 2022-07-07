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



Sprint 3:

Plan a Career - Explore Careers - Landing
    - Basic styling, typography, etc.
    - CUSTOM - full-width hero image, with edge fade gradient
    - CUSTOM - centered but over-full-width silhouettes background
    - COMPONENT - 1/4 card?
    - COMPONENT - Featured Resources 1/2 cards
    - COMPONENT - Additional Topics 1/4 cards

Plan a Career - Explore Careers - A-Z search
    - Basic styling, typography, etc.
    - COMPONENT - a-z index

Plan a Career - Explore Careers - Search Results
    - no wireframe

Plan a Career - Explore Careers - Career Profile
Plan a Career - Explore Careers - Types of Employment in BC
Plan a Career - Explore Careers - Is Self-employment for You
Plan a Career - Explore Careers - B.C. Occupational Regulators
Plan a Career - Explore Careers - Definitions
Plan a Career - Resources For




https://hive.aved.gov.bc.ca/jira/browse/WR-66   EPIC        - 
https://hive.aved.gov.bc.ca/jira/browse/WR-160  USER STORY  - not linked to Epic?
https://hive.aved.gov.bc.ca/jira/browse/WR-283  DEVTASK     - link to epic? User Story? wot?

