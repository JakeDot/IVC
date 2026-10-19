const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));

pkg.devDependencies['jest'] = '^30.0.0';
pkg.devDependencies['@types/jest'] = '^30.0.0';

pkg.overrides = pkg.overrides || {};
pkg.overrides['eslint'] = {
  "js-yaml": "4.3.2"
};
pkg.overrides['@istanbuljs/load-nyc-config'] = {
  "js-yaml": "3.15.2"
};
pkg.overrides['handlebars'] = "4.7.10";
pkg.overrides['proxy-addr'] = "2.0.8";
pkg.overrides['qs'] = "6.16.1";
pkg.overrides['source-map-js'] = "1.2.2";
pkg.overrides['undici'] = "8.12.3";
pkg.overrides['sprintf-js'] = "1.1.3";
pkg.overrides['minimatch'] = "^9.0.7";

pkg.pnpm = pkg.pnpm || {};
pkg.pnpm.overrides = {
  "eslint>js-yaml": "4.3.2",
  "@istanbuljs/load-nyc-config>js-yaml": "3.15.2",
  "handlebars": "4.7.10",
  "proxy-addr": "2.0.8",
  "qs": "6.16.1",
  "source-map-js": "1.2.2",
  "undici": "8.12.3",
  "sprintf-js": "1.1.3",
  "minimatch": "^9.0.7"
};

fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2));
