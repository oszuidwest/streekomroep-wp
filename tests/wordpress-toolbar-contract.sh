#!/bin/sh

# Checks the private TinyMCE toolbar contract.
set -eu

wordpress_image=${WORDPRESS_TEST_IMAGE:-wordpress:latest}

docker run --rm --pull=always --entrypoint sh "$wordpress_image" -eu -c '
plugin=/usr/src/wordpress/wp-includes/js/tinymce/plugins/wordpress/plugin.js

if [ ! -f "$plugin" ]; then
    echo "WordPress TinyMCE plugin source not found: $plugin" >&2
    exit 1
fi

require_contract() {
    pattern=$1
    description=$2

    if ! grep -Eq "$pattern" "$plugin"; then
        echo "WordPress no longer provides the TinyMCE contract: $description" >&2
        exit 1
    fi
}

require_contract "editor[.]wp[.]_createToolbar[[:space:]]*=" "editor.wp._createToolbar"
require_contract "editor[.]fire.*wptoolbar" "wptoolbar event"
require_contract "currentSelection[[:space:]]*=[[:space:]]*args[.]selection[[:space:]]*[|][|][[:space:]]*args[.]element" "custom toolbar selection"
require_contract "toolbar[.]bottom[[:space:]]*=[[:space:]]*bottom" "bottom toolbar positioning"

echo "OK: WordPress provides the TinyMCE floating-toolbar contract"
'
