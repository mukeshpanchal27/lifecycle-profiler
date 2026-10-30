#!/usr/bin/env bash
# Installs the Excimer PHP extension in the wp-env WordPress container.
# Runs from the afterStart script in .wp-env.excimer.json.
set -euo pipefail

EXCIMER_VERSION="1.2.6"
INI_FILE="/usr/local/etc/php/conf.d/docker-php-ext-excimer.ini"

if php -m | grep -qi '^excimer$'; then
	echo "Excimer is already installed."
	exit 0
fi

# The pecl channel lookup fails in this image, so install from the release file.
curl -sSL -o /tmp/excimer.tgz "https://pecl.php.net/get/excimer-${EXCIMER_VERSION}.tgz"
sudo pecl install /tmp/excimer.tgz > /tmp/excimer-install.log 2>&1
echo "extension=excimer.so" | sudo tee "$INI_FILE" > /dev/null
sudo apache2ctl graceful

php -m | grep -qi '^excimer$'
echo "Excimer ${EXCIMER_VERSION} is installed."
