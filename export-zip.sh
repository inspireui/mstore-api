#!/bin/bash
set -e

# Use this command to export the plugin instead of zipping it on macos. Because
# it will cause “Incompatible Archive” issue when uploading plugins to
# WordPress.
zip -r mstore-api.zip mstore-api/

echo "Export Plugin Done !!!"