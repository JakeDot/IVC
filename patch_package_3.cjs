const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));

pkg.overrides['argparse'] = "2.0.1";
pkg.pnpm.overrides['argparse'] = "2.0.1";

fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2));
