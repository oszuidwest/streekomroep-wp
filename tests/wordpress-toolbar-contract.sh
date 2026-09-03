#!/bin/sh

# Checks the bundled WordPress TinyMCE toolbar contract.
set -eu

plugin="$(dirname "$0")/../vendor/roots/wordpress-no-content/wp-includes/js/tinymce/plugins/wordpress/plugin.js"

if [ ! -f "$plugin" ]; then
    echo "WordPress TinyMCE plugin source not found (run composer install): $plugin" >&2
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
