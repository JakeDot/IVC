import re

with open("public/assets/js/webrtc.js", "r") as f:
    text = f.read()

# Strip IIFE
text = text.replace("(() => {\n    'use strict';\n", "'use strict';\n\n")
text = text.rsplit("})();", 1)[0]
lines = [line[4:] if line.startswith('    ') else line for line in text.split('\n')]

func_regex = re.compile(r'^(async )?function (\w+)\s*\(')

current_func = None
func_lines = []
brace_count = 0
in_func = False

missing_funcs = ['sendIrcCommand', 'performIrcServiceCommands']

out = []
for line in lines:
    match = func_regex.match(line)
    if match and not in_func:
        current_func = match.group(2)
        in_func = True
        brace_count = line.count('{') - line.count('}')
        func_lines.append(line)
    elif in_func:
        brace_count += line.count('{') - line.count('}')
        func_lines.append(line)
        if brace_count == 0:
            if current_func in missing_funcs:
                out.append('\n'.join(func_lines))
            func_lines = []
            in_func = False
            current_func = None

with open("public/assets/js/ivc.webrtc.js", "a") as f:
    f.write("\n\n" + "\n\n".join(out))
