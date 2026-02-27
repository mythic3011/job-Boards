#!/bin/sh
set -e

exec /usr/local/bin/crowdsec -c /etc/crowdsec/config.yaml
