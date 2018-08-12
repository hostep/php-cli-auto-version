#!/usr/bin/env bash
set -e
# set -x # debugging only
unset CDPATH

PHP_CLI_AUTO_VERSION_FLAG_FILENAME='.php-version'

# TODO: try to figure something out when users prefer to use the current working directory to be searched first for the .php-version file instead of the directory in which the file you call is used

# absolute path of current script
PHP_CLI_AUTO_VERSION_SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# figure out directory of script which is being called
PHP_CLI_AUTO_VERSION_DIR=""
program="${0##*/}"

if [ "$program" = "php" ]; then
  for arg; do
    if [ "$SKIP_LOOP_AGAIN" = "1" ]; then
      SKIP_LOOP_AGAIN="0"
      continue
    fi

    case "$arg" in
    -r* | -- ) break ;; # flag -r is used to execute inline code, so just skip searching for files in args, '--' is too provide extra args at the end, which also shouldn't manipulate php version, so stop searching further when encountering those
    -c=* | -F=* | -t=* | -z=* ) continue ;;  # flags -c, -F, -t and -z can provide file paths which shouldn't be taken in consideration for what php version to use, so skip those, version with '='
    -c* | -F* | -t* | -z* ) # version without '=' but with spaces
      SKIP_LOOP_AGAIN="1" # next argument is the path, so make sure we skip the loop twice
      continue ;;
    */* ) # matches a file path
      if [ -f "$arg" ]; then
        PHP_CLI_AUTO_VERSION_DIR="${arg%/*}"
        break
      fi
      ;;
    esac
  done
fi

# create absolute path from relative path
if [ -z "${PHP_CLI_AUTO_VERSION_DIR}" ]; then
  PHP_CLI_AUTO_VERSION_DIR="$PWD"
else
  cd "$PHP_CLI_AUTO_VERSION_DIR" 2>/dev/null || abort "cannot change working directory to \`$PHP_CLI_AUTO_VERSION_DIR'"
  PHP_CLI_AUTO_VERSION_DIR="$PWD"
  cd "$OLDPWD"
fi

# try to find the file '.php-version' in the current directory or one of the above
PHP_CLI_AUTO_VERSION_FILE=""
find_local_version_file() {
    local root="$1"
    while ! [[ "$root" =~ ^//[^/]*$ ]]; do
        if [ -e "$root/$PHP_CLI_AUTO_VERSION_FLAG_FILENAME" ]; then
            PHP_CLI_AUTO_VERSION_FILE="${root}/$PHP_CLI_AUTO_VERSION_FLAG_FILENAME"
            return 0
        fi
        [ -n "$root" ] || break
        root="${root%/*}"
    done
    return 1
}
find_local_version_file "$PHP_CLI_AUTO_VERSION_DIR" || {
    [ "$PHP_CLI_AUTO_VERSION_DIR" != "$PWD" ] && find_local_version_file "$PWD"
}

# extract php binary to use from the '.php-version' file when found
PHP_CLI_AUTO_VERSION_BINARY=""
if [ -e "$PHP_CLI_AUTO_VERSION_FILE" ]; then
  # Read the first non-whitespace word from the specified version file.
  # Be careful not to load it whole in case there's something crazy in it.
  IFS="${IFS}"$'\r'
  words=( $(cut -b 1-1024 "$PHP_CLI_AUTO_VERSION_FILE") )
  version="${words[0]}"

  if [ -n "$version" ]; then
    PHP_CLI_AUTO_VERSION_BINARY="$version"
  fi
fi

# if not found, use 'system' as a temporary binary name
if [ -z "$PHP_CLI_AUTO_VERSION_BINARY" ]; then
  PHP_CLI_AUTO_VERSION_BINARY="system"
fi

# figure out absolute path to the binary when we can find it in the PATH
# if 'system' is used, try to find a normal 'php' executable in the PATH
remove_from_path() {
  local path_to_remove="$1"
  local path_before
  local result=":${PATH//\~/$HOME}:"
  while [ "$path_before" != "$result" ]; do
    path_before="$result"
    result="${result//:$path_to_remove:/:}"
  done
  result="${result%:}"
  echo "${result#:}"
}

PHP_CLI_AUTO_VERSION_BINARY_PATH=""
if [ "$PHP_CLI_AUTO_VERSION_BINARY" = "system" ]; then
    # manipulate path so this script itself isn't found in the path
    PATH="$(remove_from_path "$PHP_CLI_AUTO_VERSION_SCRIPT_DIR")"
    PHP_CLI_AUTO_VERSION_BINARY_PATH="$(command -v php || true)"
elif [ -n "$PHP_CLI_AUTO_VERSION_BINARY" ]; then
    PHP_CLI_AUTO_VERSION_BINARY_PATH="$(command -v "$PHP_CLI_AUTO_VERSION_BINARY" || true)"
fi

# if not found an executable, show an error
if [ ! -x "$PHP_CLI_AUTO_VERSION_BINARY_PATH" ]; then
  echo "php-auto-version: we can't find the '$PHP_CLI_AUTO_VERSION_BINARY' binary in your PATH" >&2
  exit 1
fi

# execute the command through the found executable
exec "$PHP_CLI_AUTO_VERSION_BINARY_PATH" "$@"
