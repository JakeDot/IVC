const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));

pkg.overrides['brace-expansion'] = "2.1.7";
pkg.pnpm.overrides['brace-expansion'] = "2.1.7";

fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2));
