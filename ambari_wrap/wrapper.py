#!/usr/bin/env python2

import subprocess

def execute(configurations={}, parameters={}, host_name=None):
    command="echo '  fds titi fds'"
    popen = subprocess.Popen(command, shell = True, stdin = None, stdout = subprocess.PIPE, stderr = None)
    (out, err) = popen.communicate()
    out = out.rstrip()
    if popen.returncode == 0:
        return("OK", out)
    elif popen.returncode == 1:
        return("WARN", out)
    elif popen.returncode == 2:
        return("CRITICAL", out)
    else:
        return("UNKNOWN", out)

if __name__ == '__main__':
  print execute()
