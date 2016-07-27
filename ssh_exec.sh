#!/usr/bin/env bash

usage() {
  echo './ssh_exec.sh <SSH_RESOURCE> <LOCAL_SCRIPT_FILE> [ ... script_args ]'
}

if (( $# < 2 )); then
  usage
  exit 3
fi

ssh_res=$1
script=$2
args=${@:3}

if [ ! -f $script ]; then
  echo 'ERROR: 2nd argument must be a script'
  exit 2
fi
read shebang < $script

declare -A shellcmd;

shellcmd=([bash]='bash -s -' [php]='php --' [python]='python -' [python2]='python2 -' [python3]='python3 -');

shell='bash'
index=1
# If SHEBANG is #!/usr/bin/env <SHELL>
if [[ $shebang =~ \#!/usr/bin/env ]]; then
  shell=(${shebang// / }) # split shebang by space
  shell=${shell[1]} # take second parameter (the shell)
  # First line is a shebang, remove it
  index=2
fi
if [ -z "${shellcmd[$shell]}" ]; then
  echo 'ERROR: Unsupported SHELL'
  exit 2
fi
# print file (without shebang) and pipe it to ssh
tail -n +$index $script | ssh -o "StrictHostkeyChecking no" $ssh_res ${shellcmd[$shell]} $args
