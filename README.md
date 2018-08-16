# Script to automatically use a different php executable per project

## First things first

This project was heavily inspired by the shims provided by [rbenv](https://github.com/rbenv/rbenv) and [nodenv](https://github.com/nodenv/nodenv) and copies a bunch of code from those projects.

But this is by no means a php variant of those 2 projects. It won't install php versions for example.  
This only takes the shim functionality and allows you to specify in a special file `.php-auto-version` which specific php executable it should use per project, whenever `php` is executed. This also works when not explicitly calling the `php` command, but also works implicitly when a `.php` file is executable or when a `.phar` file gets executed for example.

This was written for macOS (tested using 10.13.6), tested with `GNU bash, version 3.2.57(1)-release (x86_64-apple-darwin17)`, but might work on other POSIX-compliant shells.

## Why?

Well, I work on multiple projects which use different php versions. You can pretty easily install multiple PHP versions using all kinds of methods, here are some for macOS: [MacPorts](https://www.macports.org/), [Homebrew](https://brew.sh/), [php-osx](https://php-osx.liip.ch/), [XAMPP](https://www.apachefriends.org/index.html), and various others...

This *shim* script doesn't really care what you use, it should work with everything as long as you have multiple php cli executables available which you can execute.

I don't want to think or remember for every project what specific version of PHP I should use on the command line, so I wanted something that used the correct php version automatically based on some configuration file in the project.

There are various solutions available to use multiple php versions inside your webserver. Where you can easily tell per project which php version should be used.  
But I couldn't find an easy solution yet for the php command on the cli to automatically have it use a specific php version per project.  
That's where this script comes in, to provide a solution for this problem.

## How?

This script is simply called `php` and is supposed to replace your normal `php` executable(s). Therefore, you have to add it in a directory which is defined at the beginning of your `$PATH` variable so when you run `php` on the command line, this script is the first one which gets found and used.

For example, I have a `bin` directory in my home directory in which I've put this script and made it executable. I've then manipulated my `$PATH` variable through my `~/.bash_profile` config file, so the `~/bin` directory is searched first for when I execute a command:

```bash
cd ~/bin/
wget https://raw.githubusercontent.com/hostep/php-cli-auto-version/master/php
chmod u+x php

# then inside my .bash_profile file, I've added this:
export PATH="$HOME/bin:$PATH"
# now, make sure you restart your shell or source the changed .bash_profile file, so the new $PATH variable is getting used
```

Next up is adding a special file `.php-auto-version` to various projects in which we set the path to the specific php executable we want to use. You can just specify the executable filename if the executables are in your `$PATH` or you can use the absolute filename. Both work perfectly fine.  
The script will search up in the directory tree until it finds a `.php-auto-version` file, so you can be inside a subdirectory of a project and it will still pick the correct version.  
If no `.php-auto-version` file can be found, it will fall back to the default `php` executable which it tries to find in the rest of your `$PATH`.

### Some examples

```bash
# I have 5 test projects, each needing a different php version:
$ find -s . -type f
./project-running-php-55/.php-auto-version
./project-running-php-56/.php-auto-version
./project-running-php-70/.php-auto-version
./project-running-php-71/.php-auto-version
./project-running-php-72/.php-auto-version

# Here is the contents of those .php-auto-version files of each of these projects
$ find -s . -type f -exec cat {} \;
php55
php56
/opt/local/bin/php70
/usr/bin/php #built-in php from macOS, currently version 7.1.x
php72

# Notice above that you can both define an absolute path or just a simple filename when that particular command is available in your $PATH

# Now go into each of these directories and check the php version in use:
$ cd project-running-php-55 && php --version | head -n 1 | cut -d ' ' -f 1 -f 2 && cd ..
PHP 5.5.38

$ cd project-running-php-56 && php --version | head -n 1 | cut -d ' ' -f 1 -f 2 && cd ..
PHP 5.6.37

$ cd project-running-php-70 && php --version | head -n 1 | cut -d ' ' -f 1 -f 2 && cd ..
PHP 7.0.31

$ cd project-running-php-71 && php --version | head -n 1 | cut -d ' ' -f 1 -f 2 && cd ..
PHP 7.1.16

$ cd project-running-php-72 && php --version | head -n 1 | cut -d ' ' -f 1 -f 2 && cd ..
PHP 7.2.8

# This also works with composer for example:
$ cd project-running-php-71 && composer diagnose | grep 'PHP version' && cd ..
PHP version: 7.1.16

$ cd project-running-php-72 && composer diagnose | grep 'PHP version' && cd ..
PHP version: 7.2.8

# You don't even have to be inside a directory, if you execute a .php file inside one of those directories, but you yourself are outside of that directory, it will also pick up the correct php version:
$ echo "<?php echo phpversion();" > project-running-php-55/test.php && php project-running-php-55/test.php
5.5.38

$ echo "<?php echo phpversion();" > project-running-php-56/test.php && php project-running-php-56/test.php
5.6.37

# Now, let's make an executable php file and call it from outside the directory
$ echo '#!/usr/bin/env php' > project-running-php-70/test.php && echo "<?php echo phpversion();" >> project-running-php-70/test.php && chmod u+x project-running-php-70/test.php && project-running-php-70/test.php
7.0.31
```

## Watch out: still experimental

Since this has not really been tested in the wild and my bash scripting skills aren't that good, you should still consider this script highly experimental.  
However, a lot of code was taken from [rbenv](https://github.com/rbenv/rbenv) and [nodenv](https://github.com/nodenv/nodenv), so some of the code in this script is probably very stable. But I've added some things in there which might not be so stable, so please be aware of this.  
And of course, feel free to open an issue or a pull request when you see something which can be improved or fixed.

Be aware, I don't want to make this into a [phpenv](https://github.com/phpenv/phpenv) project, because that already exists (I just found this after writing this script, typical 😛). I just want a simple script which does just what I need it to do.
