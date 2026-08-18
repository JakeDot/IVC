with open("public/assets/js/webrtc.js", "r") as f:
    orig = f.read()

import re
func_regex = re.compile(r'^(async )?function (\w+)\s*\(')

orig_funcs = []
for line in orig.split('\n'):
    if line.startswith('    function ') or line.startswith('    async function '):
        match = func_regex.match(line[4:])
        if match:
            orig_funcs.append(match.group(2))

with open("public/assets/js/ivc.core.js", "r") as f: core = f.read()
with open("public/assets/js/ivc.theme.js", "r") as f: theme = f.read()
with open("public/assets/js/ivc.ui.js", "r") as f: ui = f.read()
with open("public/assets/js/ivc.webrtc.js", "r") as f: webrtc = f.read()
with open("public/assets/js/ivc.app.js", "r") as f: app = f.read()

all_new = core + theme + ui + webrtc + app
missing = []
for f in orig_funcs:
    if f'function {f}(' not in all_new:
        missing.append(f)

print("Missing:", missing)
